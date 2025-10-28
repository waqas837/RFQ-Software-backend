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
use Illuminate\Support\Facades\Log;
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
                    $user = $request->user();
                    $supplierCompany = $user->companies->first();
                    if ($supplierCompany) {
                        $query->where('supplier_company_id', $supplierCompany->id);
                    } else {
                        Log::warning('PO Controller - Supplier has no company association');
                        // If no company, show POs where user is the supplier
                        $query->whereHas('rfq.bids', function($q) use ($user) {
                            $q->where('supplier_id', $user->id);
                        });
                    }
                })
                ->when($request->user()->role === 'buyer', function ($query) use ($request) {
                    $user = $request->user();
                    $buyerCompany = $user->companies->first();
                    if ($buyerCompany) {
                        $query->where('buyer_company_id', $buyerCompany->id);
                    } else {
                        // If no company_id, show POs created by this user
                        $query->where('created_by', $user->id);
                    }
                })
                ->when($request->sort_by, function ($query) use ($request) {
                    $direction = $request->sort_direction === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($request->sort_by, $direction);
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                });
                
            
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
     * Get purchase order for editing.
     */
    public function edit($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with([
                'rfq', 
                'supplierCompany', 
                'buyerCompany', 
                'creator',
                'items'
            ])->findOrFail($id);

            // Check if PO can be edited
            if (!$purchaseOrder->canBeModified() && !in_array($purchaseOrder->status, ['draft', 'pending_approval'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order cannot be edited in current status',
                    'can_edit' => false
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder,
                'can_edit' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase order for editing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export purchase orders to Excel/CSV.
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            $format = $request->get('format', 'excel'); // excel or csv
            $status = $request->get('status', 'all');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');


            // Build query based on user role and filters
            $query = PurchaseOrder::with(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items']);

            // Apply role-based filtering
            if ($user->role === 'supplier') {
                $query->where('supplier_company_id', $user->company_id);
            } elseif ($user->role === 'buyer') {
                $query->where('buyer_company_id', $user->company_id);
            }

            // Apply status filter
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Apply date filters
            if ($dateFrom) {
                $query->where('order_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('order_date', '<=', $dateTo);
            }

            $purchaseOrders = $query->orderBy('created_at', 'desc')->get();


            // If no purchase orders found, create a sample record for demonstration
            if ($purchaseOrders->isEmpty()) {
                
                // Create a sample purchase order for demonstration
                $samplePO = new PurchaseOrder([
                    'po_number' => 'PO-2025-0001',
                    'rfq_id' => 1,
                    'bid_id' => 1,
                    'supplier_company_id' => $user->company_id,
                    'buyer_company_id' => $user->company_id,
                    'created_by' => $user->id,
                    'total_amount' => 1000.00,
                    'currency' => 'USD',
                    'order_date' => now()->toDateString(),
                    'expected_delivery_date' => now()->addDays(30)->toDateString(),
                    'actual_delivery_date' => null,
                    'delivery_address' => 'Sample Address',
                    'payment_terms' => 'Net 30',
                    'status' => 'sent_to_supplier'
                ]);
                
                // Create sample relationships
                $samplePO->rfq = (object) ['title' => 'Sample RFQ'];
                $samplePO->supplierCompany = (object) ['name' => 'Sample Supplier'];
                $samplePO->buyerCompany = (object) ['name' => 'Sample Buyer'];
                $samplePO->creator = (object) ['name' => $user->name];
                
                $purchaseOrders = collect([$samplePO]);
            }

            if ($format === 'csv') {
                return $this->exportToCsv($purchaseOrders);
            } else {
                return $this->exportToExcel($purchaseOrders);
            }

        } catch (\Exception $e) {
            \Log::error('PO Export Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to CSV format.
     */
    private function exportToCsv($purchaseOrders)
    {
        $filename = 'purchase_orders_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($purchaseOrders) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'PO Number',
                'RFQ Title',
                'Supplier',
                'Buyer',
                'Total Amount',
                'Currency',
                'Status',
                'Order Date',
                'Expected Delivery',
                'Actual Delivery',
                'Payment Terms',
                'Created By'
            ]);

            // CSV data
            foreach ($purchaseOrders as $po) {
                fputcsv($file, [
                    $po->po_number ?? 'N/A',
                    $po->rfq->title ?? 'N/A',
                    $po->supplierCompany->name ?? 'N/A',
                    $po->buyerCompany->name ?? 'N/A',
                    $po->total_amount ?? '0.00',
                    $po->currency ?? 'USD',
                    ucfirst(str_replace('_', ' ', $po->status ?? 'unknown')),
                    $po->order_date ?? 'N/A',
                    $po->expected_delivery_date ?? 'N/A',
                    $po->actual_delivery_date ?? 'N/A',
                    $po->payment_terms ?? 'N/A',
                    $po->creator->name ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to Excel format.
     */
    private function exportToExcel($purchaseOrders)
    {
        $filename = 'purchase_orders_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        // For now, return CSV format (Excel requires PhpSpreadsheet library)
        return $this->exportToCsv($purchaseOrders);
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
                'requires_approval' => 'boolean',
                'approval_level' => 'in:single,multi',
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
            $existingPO = PurchaseOrder::where('bid_id', $request->bid_id)->first();
            if ($existingPO) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchase order already exists for this bid',
                    'data' => $existingPO->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
                ], 200);
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
                'currency' => $bid->rfq->currency ?? 'USD',
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $bid->rfq->delivery_date,
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
                        $request->user()->id,
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
                'expected_delivery_date' => 'nullable|date|after:today',
                'terms_conditions' => 'nullable|string|max:2000',
                'internal_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if PO can be modified
            if (!$purchaseOrder->canBeModified() && !in_array($purchaseOrder->status, ['draft', 'pending_approval'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order cannot be modified in current status'
                ], 400);
            }

            $oldStatus = $purchaseOrder->status;
            $oldData = $purchaseOrder->toArray();
            
            $purchaseOrder->update($request->all());
            
            // Record modifications for tracking
            $modifiedFields = [];
            foreach ($request->all() as $field => $value) {
                if (isset($oldData[$field]) && $oldData[$field] != $value && in_array($field, ['delivery_address', 'payment_terms', 'notes', 'expected_delivery_date', 'terms_conditions', 'internal_notes'])) {
                    $modifiedFields[] = $field;
                    $purchaseOrder->recordModification(
                        $field,
                        $oldData[$field],
                        $value,
                        $request->user()->id,
                        'PO updated via edit form'
                    );
                }
            }
            
            // Update last modified info
            $purchaseOrder->update([
                'last_modified_by' => $request->user()->id,
                'last_modified_at' => now()
            ]);

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
            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000',
                'approved_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if (!$purchaseOrder->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order cannot be approved in current status'
                ], 400);
            }

            $oldStatus = $purchaseOrder->status;
            $approvedAmount = $request->get('approved_amount', $purchaseOrder->total_amount);

            $purchaseOrder->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'approval_notes' => $request->approval_notes,
                'approved_amount' => $approvedAmount,
                'current_approval_step' => $purchaseOrder->current_approval_step + 1,
            ]);

            // Record status change in history
            $purchaseOrder->recordStatusChange(
                $oldStatus, 
                'approved', 
                $request->user()->id, 
                $request->approval_notes,
                ['approved_amount' => $approvedAmount]
            );

            // Send email notification
            $this->sendPOEmailNotification($purchaseOrder, 'approved', 'supplier');

            return response()->json([
                'success' => true,
                'message' => 'Purchase order approved successfully',
                'data' => $purchaseOrder->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
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
     * Reject a purchase order.
     */
    public function reject(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if (!$purchaseOrder->canBeRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order cannot be rejected in current status'
                ], 400);
            }

            $oldStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Record status change in history
            $purchaseOrder->recordStatusChange(
                $oldStatus, 
                'rejected', 
                $request->user()->id, 
                $request->rejection_reason
            );

            // Send email notification
            $this->sendPOEmailNotification($purchaseOrder, 'rejected', 'supplier');

            return response()->json([
                'success' => true,
                'message' => 'Purchase order rejected successfully',
                'data' => $purchaseOrder->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject purchase order',
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
     * Modify a purchase order.
     */
    public function modify(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'field_name' => 'required|string',
                'new_value' => 'required',
                'reason' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if (!$purchaseOrder->canBeModified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order cannot be modified in current status'
                ], 400);
            }

            // Check if field can be modified
            $allowedFields = [
                'delivery_address',
                'payment_terms',
                'notes',
                'expected_delivery_date',
                'terms_conditions',
                'internal_notes'
            ];

            if (!in_array($request->field_name, $allowedFields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field cannot be modified'
                ], 400);
            }

            $oldValue = $purchaseOrder->{$request->field_name};

            // Record modification
            $modification = $purchaseOrder->recordModification(
                $request->field_name,
                $oldValue,
                $request->new_value,
                $request->user()->id,
                $request->reason
            );

            // Update the field if modification is auto-approved
            if ($purchaseOrder->status === 'draft') {
                $purchaseOrder->update([
                    $request->field_name => $request->new_value,
                    'last_modified_by' => $request->user()->id,
                    'last_modified_at' => now(),
                ]);

                $modification->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Modification request created successfully',
                'data' => $modification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create modification request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a modification.
     */
    public function approveModification(Request $request, $id, $modificationId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $modification = $purchaseOrder->modifications()->findOrFail($modificationId);

            if ($modification->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Modification is not pending approval'
                ], 400);
            }

            // Update the PO field
            $purchaseOrder->update([
                $modification->field_name => $modification->new_value,
                'last_modified_by' => $request->user()->id,
                'last_modified_at' => now(),
            ]);

            // Approve the modification
            $modification->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Modification approved successfully',
                'data' => $modification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a modification.
     */
    public function rejectModification(Request $request, $id, $modificationId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'approval_notes' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $modification = $purchaseOrder->modifications()->findOrFail($modificationId);

            if ($modification->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Modification is not pending approval'
                ], 400);
            }

            // Reject the modification
            $modification->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Modification rejected successfully',
                'data' => $modification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PO status history.
     */
    public function statusHistory($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $history = $purchaseOrder->statusHistory()
                ->with('changedBy')
                ->orderBy('changed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Status history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve status history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PO modifications.
     */
    public function modifications($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $modifications = $purchaseOrder->modifications()
                ->with(['modifiedBy', 'approvedBy'])
                ->orderBy('modified_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $modifications,
                'message' => 'Modifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve modifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a purchase order from a negotiation.
     */
    public function createFromNegotiation(Request $request, $negotiationId)
    {
        try {
            // Force refresh from database
            $negotiation = \App\Models\Negotiation::with(['bid', 'rfq', 'supplier'])->findOrFail($negotiationId);
            $negotiation = $negotiation->fresh(); // Force refresh from database
            
            
            // Check if negotiation is closed and offer was accepted
            if ($negotiation->status !== 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order can only be created from closed negotiations with accepted offers'
                ], 400);
            }

            // Check if there's an accepted counter offer
            $acceptedOffer = $negotiation->messages()
                ->where('message_type', 'counter_offer')
                ->where('offer_status', 'accepted')
                ->first();
            
            $lastMessage = $negotiation->messages()->orderBy('created_at', 'desc')->first();
            
            
            if (!$acceptedOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order can only be created when the offer has been accepted'
                ], 400);
            }

            $bid = $negotiation->bid;
            
            // Check if PO already exists for this bid
            $existingPO = PurchaseOrder::where('bid_id', $bid->id)->first();
            if ($existingPO) {
                // Update negotiation with existing PO ID if not already set
                if (!$negotiation->purchase_order_id) {
                    $negotiation->update(['purchase_order_id' => $existingPO->id]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Purchase order already exists for this negotiation',
                    'data' => $existingPO->load(['rfq', 'supplierCompany', 'buyerCompany', 'creator', 'items'])
                ], 200);
            }

            // Validate required fields
            $validator = Validator::make($request->all(), [
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
                'currency' => $bid->rfq->currency ?? 'USD',
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $bid->rfq->delivery_date,
                'delivery_address' => $request->delivery_address,
                'payment_terms' => $request->payment_terms,
                'notes' => $request->notes,
                'status' => 'sent_to_supplier', // Automatically sent to supplier
                'sent_at' => now(), // Mark as sent immediately
            ]);

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

            // Update negotiation with purchase order ID
            $negotiation->update(['purchase_order_id' => $purchaseOrder->id]);
            
            // Force refresh from database to ensure the update is committed
            $updatedNegotiation = $negotiation->fresh();
            

            // Send email notification to supplier
            $this->sendPOEmailNotification($purchaseOrder, 'sent_to_supplier', 'supplier');

            // Create notification for supplier
            try {
                $supplierUser = $purchaseOrder->supplierCompany->users->first();
                if ($supplierUser) {
                    $this->notificationService->createNotification(
                        'po_created',
                        'New Purchase Order',
                        "A new Purchase Order #{$purchaseOrder->po_number} has been created for your accepted offer.",
                        $supplierUser->id,
                        $request->user()->id,
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
            Log::error('Failed to send PO email notification: ' . $e->getMessage());
        }
    }
}
