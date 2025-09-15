<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Bid;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\PurchaseOrderNotification;
use App\Services\NotificationService;

class PurchaseOrderController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get all purchase orders with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            // Debug logging
            \Log::info('PO Controller - User ID: ' . $request->user()->id);
            \Log::info('PO Controller - User Role: ' . $request->user()->role);
            \Log::info('PO Controller - User Company ID: ' . $request->user()->company_id);
            
            $purchaseOrders = PurchaseOrder::with(['rfq', 'supplierCompany', 'buyerCompany', 'creator'])
                ->when($request->search, function ($query, $search) {
                    $query->where('po_number', 'like', "%{$search}%")
                          ->orWhereHas('rfq', function ($q) use ($search) {
                              $q->where('title', 'like', "%{$search}%");
                          });
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($request->user()->role === 'supplier', function ($query) use ($request) {
                    if ($request->user()->company_id) {
                        $query->where('supplier_company_id', $request->user()->company_id);
                    }
                })
                ->when($request->user()->role === 'buyer', function ($query) use ($request) {
                    \Log::info('PO Controller - Buyer role detected, applying filter');
                    if ($request->user()->company_id) {
                        \Log::info('PO Controller - Filtering by company_id: ' . $request->user()->company_id);
                        $query->where('buyer_company_id', $request->user()->company_id);
                    } else {
                        \Log::info('PO Controller - No company_id, filtering by created_by: ' . $request->user()->id);
                        // If no company_id, show POs created by this user
                        $query->where('created_by', $request->user()->id);
                    }
                })
                ->when($request->sort_by, function ($query) use ($request) {
                    $direction = $request->sort_direction === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($request->sort_by, $direction);
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                });
                
            // Debug: Log the SQL query
            \Log::info('PO Controller - SQL Query: ' . $purchaseOrders->toSql());
            \Log::info('PO Controller - Query Bindings: ' . json_encode($purchaseOrders->getBindings()));
            
            $purchaseOrders = $purchaseOrders->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrders,
                'message' => 'Purchase orders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific purchase order.
     */
    public function show($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder,
                'message' => 'Purchase order retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new purchase order from awarded bid.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bid_id' => 'required|exists:bids,id',
                'delivery_address' => 'required|string|max:500',
                'payment_terms' => 'required|string|max:255',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bid = Bid::with(['rfq', 'supplier'])->findOrFail($request->bid_id);

            // Check if bid is awarded
            if ($bid->status !== 'awarded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only awarded bids can be converted to purchase orders'
                ], 400);
            }

            // Check if PO already exists for this bid
            if (PurchaseOrder::where('bid_id', $request->bid_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order already exists for this bid'
                ], 400);
            }

            // Generate PO number
            $poNumber = 'PO-' . date('Y') . '-' . str_pad(PurchaseOrder::count() + 1, 4, '0', STR_PAD_LEFT);

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'rfq_id' => $bid->rfq_id,
                'bid_id' => $bid->id,
                'supplier_company_id' => $bid->supplier_company_id,
                'buyer_company_id' => $bid->rfq->company_id,
                'created_by' => $request->user()->id,
                'total_amount' => $bid->total_amount,
                'currency' => 'USD',
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $bid->rfq->delivery_deadline,
                'delivery_address' => $request->delivery_address,
                'payment_terms' => $request->payment_terms,
                'notes' => $request->notes,
                'status' => 'sent_to_supplier', // Automatically sent to supplier
                'sent_at' => now(), // Mark as sent immediately
            ]);

            // Send email notification to supplier
            $this->sendPOEmailNotification($purchaseOrder, 'sent_to_supplier', 'supplier');

            // Copy items from bid to PO
            foreach ($bid->items as $bidItem) {
                $purchaseOrder->items()->create([
                    'rfq_item_id' => $bidItem->rfq_item_id,
                    'item_name' => $bidItem->item_name,
                    'item_description' => $bidItem->item_description,
                    'quantity' => $bidItem->quantity,
                    'unit_price' => $bidItem->unit_price,
                    'total_price' => $bidItem->total_price,
                    'unit_of_measure' => $bidItem->unit_of_measure,
                    'specifications' => $bidItem->technical_specifications,
                    'notes' => $bidItem->notes,
                ]);
            }

            // Create notification for supplier
            try {
                $supplierUser = $purchaseOrder->supplierCompany->users->first();
                if ($supplierUser) {
                    $this->notificationService->createNotification(
                        'po_created',
                        'New Purchase Order',
                        "A new Purchase Order #{$purchaseOrder->po_number} has been created for your awarded bid.",
                        $supplierUser->id,
                        $purchaseOrder->creator->id,
                        $purchaseOrder->id,
                        'purchase_order'
                    );
                }
            } catch (\Exception $e) {
                Log::error('Error creating PO notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a purchase order.
     */
    public function update(Request $request, $id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Allow status updates for supplier workflow (sent_to_supplier -> in_progress -> delivered)
            $allowedStatusUpdates = ['sent_to_supplier', 'in_progress', 'delivered'];
            if (isset($request->status) && !in_array($request->status, $allowedStatusUpdates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status update'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'delivery_address' => 'sometimes|string|max:500',
                'payment_terms' => 'sometimes|string|max:255',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $purchaseOrder->status;
            $purchaseOrder->update($request->all());

            // Send email notification if status changed
            if (isset($request->status) && $request->status !== $oldStatus) {
                $this->sendPOEmailNotification($purchaseOrder, $request->status, 'supplier');
                $this->sendPOEmailNotification($purchaseOrder, $request->status, 'buyer');
                
                // Create notification for relevant user
                try {
                    $statusMessages = [
                        'in_progress' => 'Purchase Order is now in progress',
                        'delivered' => 'Purchase Order has been delivered'
                    ];
                    
                    $message = $statusMessages[$request->status] ?? "Purchase Order status changed to {$request->status}";
                    $recipientId = $request->status === 'in_progress' ? 
                        $purchaseOrder->buyerCompany->users->first()?->id : 
                        $purchaseOrder->supplierCompany->users->first()?->id;
                    
                    if ($recipientId) {
                        $this->notificationService->createNotification(
                            'po_status_update',
                            'PO Status Update',
                            "Purchase Order #{$purchaseOrder->po_number} - {$message}",
                            $recipientId,
                            null,
                            $purchaseOrder->id,
                            'purchase_order'
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating PO status notification: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a purchase order.
     */
    public function destroy($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Allow deletion of POs in any status for testing purposes

            $purchaseOrder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a purchase order.
     */
    public function approve(Request $request, $id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if ($purchaseOrder->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only purchase orders pending approval can be approved'
                ], 400);
            }

            $purchaseOrder->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order approved successfully',
                'data' => $purchaseOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send purchase order to supplier.
     */
    public function send($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if ($purchaseOrder->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved purchase orders can be sent'
                ], 400);
            }

            $purchaseOrder->update([
                'status' => 'sent_to_supplier',
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order sent successfully',
                'data' => $purchaseOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm purchase order by supplier.
     */
    public function confirm($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if ($purchaseOrder->status !== 'sent_to_supplier') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sent purchase orders can be confirmed'
                ], 400);
            }

            $purchaseOrder->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order confirmed successfully',
                'data' => $purchaseOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm delivery with photos/documents
     */
    public function confirmDelivery(Request $request, $id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if ($purchaseOrder->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only purchase orders in progress can be marked as delivered'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'delivery_notes' => 'nullable|string|max:1000',
                'delivery_photos' => 'nullable|array|max:5',
                'delivery_photos.*' => 'file|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per file
                'delivery_documents' => 'nullable|array|max:3',
                'delivery_documents.*' => 'file|mimes:pdf,doc,docx|max:10240', // 10MB max per file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle file uploads
            $deliveryPhotos = [];
            $deliveryDocuments = [];

            if ($request->hasFile('delivery_photos')) {
                foreach ($request->file('delivery_photos') as $photo) {
                    $path = $photo->store('delivery-photos', 'public');
                    $deliveryPhotos[] = $path;
                }
            }

            if ($request->hasFile('delivery_documents')) {
                foreach ($request->file('delivery_documents') as $document) {
                    $path = $document->store('delivery-documents', 'public');
                    $deliveryDocuments[] = $path;
                }
            }

            // Update PO with delivery confirmation
            $purchaseOrder->update([
                'status' => 'delivered',
                'actual_delivery_date' => now()->toDateString(),
                'delivery_notes' => $request->delivery_notes,
                'delivery_photos' => $deliveryPhotos,
                'delivery_documents' => $deliveryDocuments,
            ]);

            // Send email notification
            $this->sendPOEmailNotification($purchaseOrder, 'delivered', 'buyer');

            return response()->json([
                'success' => true,
                'message' => 'Delivery confirmed successfully',
                'data' => $purchaseOrder->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email notification for PO status changes
     */
    private function sendPOEmailNotification(PurchaseOrder $purchaseOrder, string $status, string $recipientType)
    {
        try {
            // Get recipient email based on type
            $recipientEmail = null;
            if ($recipientType === 'supplier') {
                $recipientEmail = $purchaseOrder->supplierCompany->email ?? null;
            } elseif ($recipientType === 'buyer') {
                $recipientEmail = $purchaseOrder->buyerCompany->email ?? null;
            }

            if ($recipientEmail) {
                Mail::to($recipientEmail)->send(new PurchaseOrderNotification($purchaseOrder, $status, $recipientType));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send PO email notification: ' . $e->getMessage());
        }
    }
}
