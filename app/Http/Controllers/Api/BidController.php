<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Rfq;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BidController extends Controller
{
    /**
     * Get all bids with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $bids = Bid::with(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem', 'submittedBy', 'purchaseOrder'])
                ->when($request->search, function ($query, $search) {
                    $query->whereHas('rfq', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%");
                    });
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($request->user()->hasRole('supplier'), function ($query) use ($request) {
                    $query->where('supplier_id', $request->user()->id);
                })
                ->when($request->user()->hasRole('buyer'), function ($query) use ($request) {
                    $query->whereHas('rfq', function ($q) use ($request) {
                        $q->where('created_by', $request->user()->id);
                    });
                })
                ->when($request->sort_by, function ($query) use ($request) {
                    $direction = $request->sort_direction === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($request->sort_by, $direction);
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $bids,
                'message' => 'Bids retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bids',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bid.
     */
    public function show($id)
    {
        $bid = Bid::with(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $bid
        ]);
    }

    /**
     * Create a new bid.
     */
    public function store(Request $request)
    {
        // Debug: Log the request data
        \Log::info('Bid submission request:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'rfq_id' => 'required|exists:rfqs,id',
            'delivery_time' => 'required|integer|min:1',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:rfq_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.specifications' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Debug: Log validation errors
            \Log::info('Validation failed:', $validator->errors()->toArray());
            
            // Debug: Check if RFQ items exist
            foreach ($request->items as $item) {
                $rfqItem = \App\Models\RfqItem::find($item['item_id']);
                \Log::info("RFQ Item {$item['item_id']} exists:", $rfqItem ? 'YES' : 'NO');
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if RFQ is open for bidding
        $rfq = Rfq::findOrFail($request->rfq_id);
        if (!in_array($rfq->status, ['published', 'bidding_open'])) {
            return response()->json([
                'success' => false,
                'message' => 'RFQ is not open for bidding'
            ], 400);
        }

        // Check if supplier can bid on this RFQ
        $user = $request->user();
        $supplierCompany = $user->companies->first();
        
        // For bidding_open RFQs, any supplier can bid
        if ($rfq->status === 'bidding_open') {
            // Allow any supplier to bid
        } else {
            // For published RFQs, check if supplier is invited
            if (!$rfq->suppliers()->where('supplier_company_id', $supplierCompany->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not invited to bid on this RFQ'
                ], 403);
            }
        }

        // Check if supplier already submitted a bid (only for non-draft bids)
        $existingBid = Bid::where('rfq_id', $request->rfq_id)
            ->where('supplier_company_id', $supplierCompany->id)
            ->where('status', '!=', 'draft')
            ->first();
            
        if ($existingBid) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted a bid for this RFQ'
            ], 400);
        }

        // Check if there's an existing draft bid to update
        $existingDraftBid = Bid::where('rfq_id', $request->rfq_id)
            ->where('supplier_company_id', $supplierCompany->id)
            ->where('status', 'draft')
            ->first();

        // Calculate total amount
        $totalAmount = collect($request->items)->sum('total_price');

        if ($existingDraftBid) {
            // Update existing draft bid
            $existingDraftBid->update([
                'total_amount' => $totalAmount,
                'delivery_time' => $request->delivery_time,
                'terms_conditions' => $request->terms_conditions,
            ]);
            $bid = $existingDraftBid;
        } else {
            // Create new bid
            $bid = Bid::create([
            'bid_number' => 'BID-' . date('Y') . '-' . str_pad(Bid::count() + 1, 4, '0', STR_PAD_LEFT),
            'rfq_id' => $request->rfq_id,
            'supplier_company_id' => $supplierCompany->id,
            'submitted_by' => $user->id,
            'supplier_id' => $user->id,
            'total_amount' => $totalAmount,
            'currency' => 'USD',
            'delivery_time' => $request->delivery_time,
            'terms_conditions' => $request->terms_conditions,
            'is_compliant' => true,
            'status' => 'draft',
            'submitted_at' => null,
            'is_active' => true,
            ]);
        }

        // Update items for both new and existing bids
        $bid->items()->delete(); // Delete existing items
        foreach ($request->items as $item) {
            // Get the RFQ item to get additional details
            $rfqItem = \App\Models\RfqItem::find($item['item_id']);
            $originalItem = \App\Models\Item::find($rfqItem->item_id);
            
            $bid->items()->create([
                'rfq_item_id' => $item['item_id'],
                'item_name' => $originalItem->name,
                'item_description' => $originalItem->description,
                'quantity' => $item['quantity'],
                'unit_of_measure' => $originalItem->unit_of_measure,
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'currency' => 'USD',
                'technical_specifications' => is_array($item['specifications']) ? json_encode($item['specifications']) : $item['specifications'],
                'is_available' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bid created successfully',
            'data' => $bid->load(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem'])
        ], 201);
    }

    /**
     * Update a bid.
     */
    public function update(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);

        // Only allow updates if bid is in draft status
        if ($bid->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update bid that is not in draft status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'delivery_time' => 'sometimes|integer|min:1',
            'terms_conditions' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.item_id' => 'required|exists:rfq_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.specifications' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $bid->update($request->except(['items']));

        // Update items if provided
        if ($request->has('items')) {
            $bid->items()->delete();
            foreach ($request->items as $item) {
                // Get the RFQ item to get additional details
                $rfqItem = \App\Models\RfqItem::find($item['item_id']);
                $originalItem = \App\Models\Item::find($rfqItem->item_id);
                
                $bid->items()->create([
                    'rfq_item_id' => $item['item_id'],
                    'item_name' => $originalItem->name,
                    'item_description' => $originalItem->description,
                    'quantity' => $item['quantity'],
                    'unit_of_measure' => $originalItem->unit_of_measure,
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'currency' => 'USD',
                    'technical_specifications' => is_array($item['specifications']) ? json_encode($item['specifications']) : $item['specifications'],
                    'is_available' => true,
                ]);
            }

            // Recalculate total amount
            $totalAmount = collect($request->items)->sum('total_price');
            $bid->update(['total_amount' => $totalAmount]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bid updated successfully',
            'data' => $bid->load(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem'])
        ]);
    }

    /**
     * Delete a bid.
     */
    public function destroy($id)
    {
        $bid = Bid::findOrFail($id);

        if ($bid->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bid that is not in draft status'
            ], 400);
        }

        $bid->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bid deleted successfully'
        ]);
    }

    /**
     * Submit a bid.
     */
    public function submit($id)
    {
        $bid = Bid::findOrFail($id);

        if ($bid->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft bids can be submitted'
            ], 400);
        }

        $bid->update([
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

        // Create notification for buyer
        try {
            $rfq = $bid->rfq;
            $buyer = $rfq->creator;
            
            Log::info('Creating bid notification', [
                'rfq_id' => $rfq->id,
                'buyer_id' => $buyer->id,
                'supplier_id' => $bid->supplier->id,
                'bid_id' => $bid->id
            ]);
            
            app(\App\Services\NotificationService::class)->createNotification(
                'bid_submitted',
                'New Bid Submitted',
                "A new bid has been submitted for RFQ: {$rfq->title}",
                $buyer->id,
                $bid->supplier->id,
                $bid->id,
                'bid'
            );
            
            Log::info('Bid notification created successfully');
        } catch (\Exception $e) {
            Log::error('Error creating bid notification: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        return response()->json([
            'success' => true,
            'message' => 'Bid submitted successfully',
            'data' => $bid
        ]);
    }

    /**
     * Evaluate a bid (submit scores and notes).
     */
    public function evaluate(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);

        if ($bid->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted bids can be evaluated'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'technical_score' => 'required|numeric|min:1|max:10',
            'commercial_score' => 'required|numeric|min:1|max:10',
            'delivery_score' => 'required|numeric|min:1|max:10',
            'evaluation_notes' => 'nullable|string',
            'total_score' => 'nullable|numeric|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update bid with evaluation data
        $updateData = [
            'technical_score' => $request->technical_score,
            'commercial_score' => $request->commercial_score,
            'delivery_score' => $request->delivery_score,
            'total_score' => $request->total_score ?? (($request->technical_score + $request->commercial_score + $request->delivery_score) / 3),
            'evaluation_notes' => $request->evaluation_notes,
            'evaluated_by' => $request->user()->id,
            'evaluated_at' => now(),
        ];

        $bid->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Bid evaluated successfully',
            'data' => $bid->load(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem'])
        ]);
    }

    /**
     * Award a bid.
     */
    public function award(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);

        if ($bid->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Only submitted bids can be awarded'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'evaluation_notes' => 'nullable|string',
            'technical_score' => 'nullable|numeric|min:1|max:10',
            'commercial_score' => 'nullable|numeric|min:1|max:10',
            'delivery_score' => 'nullable|numeric|min:1|max:10',
            'total_score' => 'nullable|numeric|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update bid with evaluation data
        $updateData = [
            'status' => 'awarded',
            'evaluated_by' => $request->user()->id,
            'evaluated_at' => now(),
        ];

        if ($request->has('evaluation_notes')) {
            $updateData['evaluation_notes'] = $request->evaluation_notes;
        }

        if ($request->has('technical_score')) {
            $updateData['technical_score'] = $request->technical_score;
        }

        if ($request->has('commercial_score')) {
            $updateData['commercial_score'] = $request->commercial_score;
        }

        if ($request->has('delivery_score')) {
            $updateData['delivery_score'] = $request->delivery_score;
        }

        if ($request->has('total_score')) {
            $updateData['total_score'] = $request->total_score;
        } elseif ($request->has('technical_score') && $request->has('commercial_score') && $request->has('delivery_score')) {
            $updateData['total_score'] = ($request->technical_score + $request->commercial_score + $request->delivery_score) / 3;
        }

        $bid->update($updateData);

        // Update RFQ status
        $bid->rfq->update(['status' => 'awarded']);

        return response()->json([
            'success' => true,
            'message' => 'Bid awarded successfully',
            'data' => $bid->load(['rfq', 'supplierCompany', 'supplier', 'items.rfqItem'])
        ]);
    }
}
