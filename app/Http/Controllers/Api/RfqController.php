<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Models\Item;
use App\Models\Company;
use App\Services\EmailService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RfqController extends Controller
{
    /**
     * Get all RFQs with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('roles');
            
            
            $query = Rfq::query();
            
            // Apply role-based filtering
            if ($user->roles->pluck('name')->contains('buyer')) {
                $query->where('created_by', $user->id);
            } elseif ($user->roles->pluck('name')->contains('supplier')) {
                $query->whereIn('status', ['published', 'bidding_open']);
            }
            // Admin sees all RFQs (no filter)
            
            $rfqs = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);


            // Load relationships after pagination
            $rfqs->load(['company', 'creator', 'bids']);

            return response()->json([
                'success' => true,
                'data' => $rfqs,
                'message' => 'RFQs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve RFQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific RFQ.
     */
    public function show($id)
    {
        $rfq = Rfq::with(['company', 'creator', 'items.item', 'suppliers', 'bids'])->findOrFail($id);

        // Add workflow information
        $rfq->available_transitions = $rfq->getAvailableTransitions(request()->user());
        $rfq->workflow_stats = $rfq->getWorkflowStats();

        return response()->json([
            'success' => true,
            'data' => $rfq
        ]);
    }

    /**
     * Create a new RFQ.
     */
    public function store(Request $request)
    {
        // Check if user has permission to create RFQs (buyers and admins)
        if (!$request->user()->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create RFQs'
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'currency' => 'required|string|size:3',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'delivery_deadline' => 'required|date|after:today',
            'bidding_deadline' => 'required|date|before:delivery_deadline',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.specifications' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:companies,id',
            'invited_emails' => 'nullable|array',
            'invited_emails.*' => 'email|max:255',
            'invited_user_ids' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB max per file
            'invited_user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check all required fields including currency selection.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get category from request
        $category = \App\Models\Category::find($request->category_id);
        
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid category selected'
            ], 422);
        }

        // Generate unique reference number
        $year = date('Y');
        $lastRfq = Rfq::where('reference_number', 'like', "RFQ-{$year}-%")
            ->orderBy('reference_number', 'desc')
            ->first();
        
        $nextNumber = 1;
        if ($lastRfq) {
            $lastNumber = (int) substr($lastRfq->reference_number, -4);
            $nextNumber = $lastNumber + 1;
        }
        
        $referenceNumber = 'RFQ-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        // Handle file uploads
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('rfq-attachments', $filename, 'public');
                $attachmentPaths[] = $path;
            }
        }

        $rfq = Rfq::create([
            'title' => $request->title,
            'reference_number' => $referenceNumber,
            'description' => $request->description,
            'category_id' => $category->id,
            'company_id' => $request->user()->companies->first()->id,
            'created_by' => $request->user()->id,
            'status' => 'draft',
            'currency' => $request->currency,
            'budget_min' => $request->budget_min,
            'budget_max' => $request->budget_max,
            'delivery_date' => $request->delivery_deadline,
            'bid_deadline' => $request->bidding_deadline,
            'terms_conditions' => $request->terms_conditions,
            'attachments' => $attachmentPaths,
        ]);

        // Add items to RFQ
        foreach ($request->items as $item) {
            // Get the item details from the items table
            $itemRecord = \App\Models\Item::find($item['item_id']);
            $itemName = $itemRecord ? $itemRecord->name : 'Unknown Item';
            $itemDescription = $itemRecord ? $itemRecord->description : null;
            $unitOfMeasure = $itemRecord ? $itemRecord->unit_of_measure : 'pcs';
            
            $rfq->items()->create([
                'item_id' => $item['item_id'],
                'item_name' => $itemName,
                'item_description' => $itemDescription,
                'quantity' => $item['quantity'],
                'unit_of_measure' => $unitOfMeasure,
                'specifications' => $item['specifications'] ?? null,
                'custom_fields' => null,
                'estimated_price' => null,
                'currency' => 'USD',
                'delivery_date' => null,
                'sort_order' => 0,
            ]);
        }

        // Add suppliers if provided
        if ($request->supplier_ids) {
            $rfq->suppliers()->attach($request->supplier_ids);
        }

        // Send notifications to invited people
        $this->sendInvitationNotifications($rfq, $request->invited_user_ids ?? [], $request->invited_emails ?? []);

        return response()->json([
            'success' => true,
            'message' => 'RFQ created successfully',
            'data' => $rfq->load(['company', 'creator', 'items.item', 'suppliers'])
        ], 201);
    }

    /**
     * Test authentication endpoint
     */
    public function testAuth(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Authentication working',
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'user_roles' => $request->user()->roles->pluck('name')->toArray()
        ]);
    }

    /**
     * Import RFQs from Excel file
     */
    public function import(Request $request)
    {
        // Debug: Log the request details
        Log::info('Import request received', [
            'user_id' => $request->user() ? $request->user()->id : 'no user',
            'authenticated' => $request->user() ? 'yes' : 'no',
            'has_file' => $request->hasFile('file'),
            'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : 'no file',
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        // Check if user is authenticated
        if (!$request->user()) {
            Log::error('Import failed: User not authenticated');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Check if user has permission to create RFQs (buyers and admins)
        if (!$request->user()->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
            Log::error('Import failed: User does not have permission', [
                'user_id' => $request->user()->id,
                'roles' => $request->user()->roles->pluck('name')->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to import RFQs'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ], [
            'file.required' => 'Please select a file to import',
            'file.file' => 'The uploaded file is not valid',
            'file.mimes' => 'File must be in Excel (.xlsx, .xls) or CSV format',
            'file.max' => 'File size must be less than 10MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $company = $user->companies->first();
            
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must be associated with a company'
                ], 400);
            }

            $file = $request->file('file');
            $import = new \App\Imports\RfqImport($user, $company);
            
            // Use the custom import method until Laravel Excel is installed
            $result = $import->import($file);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'RFQs imported successfully',
                    'data' => [
                        'imported_count' => $result['imported_count'],
                        'file_name' => $file->getClientOriginalName(),
                        'errors' => $result['errors']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('RFQ import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Update an RFQ.
     */
    public function update(Request $request, $id)
    {
        $rfq = Rfq::findOrFail($id);

        // Check if user has permission to update RFQs (buyers and admins)
        if (!$request->user()->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update RFQs'
            ], 403);
        }

        // Only allow updates if RFQ is in draft status
        if ($rfq->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update RFQ that is not in draft status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'delivery_deadline' => 'sometimes|date|after:today',
            'bidding_deadline' => 'sometimes|date|before:delivery_deadline',
            'terms_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'title', 'description', 'budget_min', 'budget_max', 'terms_conditions'
        ]);
        
        // Map frontend field names to database field names
        if ($request->has('delivery_deadline')) {
            $updateData['delivery_date'] = $request->delivery_deadline;
        }
        if ($request->has('bidding_deadline')) {
            $updateData['bid_deadline'] = $request->bidding_deadline;
        }
        
        $rfq->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'RFQ updated successfully',
            'data' => $rfq->load(['company', 'creator', 'items.item', 'suppliers'])
        ]);
    }

    /**
     * Delete an RFQ.
     */
    public function destroy(Request $request, $id)
    {
        $rfq = Rfq::findOrFail($id);

        // Check if user has permission to delete RFQs (buyers and admins)
        if (!$request->user()->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete RFQs'
            ], 403);
        }

        // Allow deletion of RFQs in any status for testing

        $rfq->delete();

        return response()->json([
            'success' => true,
            'message' => 'RFQ deleted successfully'
        ]);
    }

    /**
     * Publish an RFQ.
     */
    public function publish(Request $request, $id)
    {
        $rfq = Rfq::findOrFail($id);

        // Check if user has permission to publish RFQs (buyers and admins)
        if (!$request->user()->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to publish RFQs'
            ], 403);
        }

        if ($rfq->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft RFQs can be published'
            ], 400);
        }

        $rfq->update(['status' => 'published']);

        // Send email notifications to suppliers
        try {
            $suppliers = $rfq->suppliers;
            $buyer = $rfq->creator;
            
            if ($suppliers->count() > 0) {
                EmailService::sendRfqInvitation($rfq, $suppliers, $buyer);
            }
        } catch (\Exception $e) {
            Log::error('Error sending RFQ invitation emails: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'RFQ published successfully',
            'data' => $rfq
        ]);
    }

    /**
     * Close an RFQ.
     */
    public function close($id)
    {
        $rfq = Rfq::findOrFail($id);

        if (!in_array($rfq->status, ['published', 'bidding_open'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only published or open RFQs can be closed'
            ], 400);
        }

        $rfq->update(['status' => 'bidding_closed']);

        return response()->json([
            'success' => true,
            'message' => 'RFQ closed successfully',
            'data' => $rfq
        ]);
    }

    /**
     * Get available workflow transitions for an RFQ.
     */
    public function getWorkflowTransitions($id)
    {
        try {
            $rfq = Rfq::findOrFail($id);
            $user = request()->user();

            // Debug logging
            Log::info("Getting workflow transitions for RFQ {$id}", [
                'rfq_status' => $rfq->status,
                'user_id' => $user->id,
                'user_role' => $user->roles->pluck('name')->first(),
                'user_roles' => $user->roles->pluck('name')->toArray()
            ]);

            $transitions = WorkflowService::getAvailableTransitions($rfq, $user);

            Log::info("Available transitions", [
                'transitions_count' => count($transitions),
                'transitions' => $transitions
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_status' => $rfq->status,
                    'current_status_label' => WorkflowService::getStatusLabel($rfq->status),
                    'available_transitions' => $transitions,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting workflow transitions: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow transitions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transition RFQ to a new status.
     */
        public function transitionStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_status' => 'required|string|in:draft,published,bidding_open,bidding_closed,under_evaluation,awarded,completed,cancelled',
                'metadata' => 'nullable|array',
                'cancellation_reason' => 'required_if:new_status,cancelled|string',
                'awarded_supplier_id' => 'required_if:new_status,awarded|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rfq = Rfq::findOrFail($id);
            $user = request()->user();
            $newStatus = $request->new_status;
            $metadata = $request->metadata ?? [];

            // Add specific metadata based on status
            if ($newStatus === 'cancelled') {
                $metadata['cancellation_reason'] = $request->cancellation_reason;
                $metadata['cancelled_by'] = $user->id;
            } elseif ($newStatus === 'awarded') {
                $metadata['awarded_supplier_id'] = $request->awarded_supplier_id;
                $metadata['awarded_by'] = $user->id;
                
                // Verify that the supplier has actually submitted a bid
                $hasBid = \App\Models\Bid::where('rfq_id', $rfq->id)
                    ->where('supplier_company_id', $request->awarded_supplier_id)
                    ->where('status', 'submitted')
                    ->exists();
                    
                if (!$hasBid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected supplier has not submitted a bid for this RFQ',
                        'errors' => ['awarded_supplier_id' => ['Selected supplier has not submitted a bid']]
                    ], 422);
                }
            }

            // Attempt to transition
            if ($rfq->transitionTo($newStatus, $user, $metadata)) {
                return response()->json([
                    'success' => true,
                    'message' => "RFQ status changed to " . WorkflowService::getStatusLabel($newStatus),
                    'data' => [
                        'new_status' => $newStatus,
                        'new_status_label' => WorkflowService::getStatusLabel($newStatus),
                        'available_transitions' => $rfq->getAvailableTransitions($user),
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to transition RFQ status'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transition RFQ status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow statistics for the current user.
     */
    public function getWorkflowStats()
    {
        try {
            $user = request()->user();
            $stats = WorkflowService::getWorkflowStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send invitation notifications to users and external emails
     */
    private function sendInvitationNotifications($rfq, $invitedUserIds, $invitedEmails)
    {
        try {
            // Send notifications to existing users
            if (!empty($invitedUserIds)) {
                $users = \App\Models\User::whereIn('id', $invitedUserIds)->get();
                
                foreach ($users as $user) {
                    // Create notification record
                    \App\Models\Notification::create([
                        'user_id' => $user->id,
                        'type' => 'rfq_invitation',
                        'title' => 'New RFQ Invitation',
                        'message' => "You have been invited to participate in RFQ: {$rfq->title}",
                        'data' => json_encode([
                            'rfq_id' => $rfq->id,
                            'rfq_title' => $rfq->title,
                            'rfq_reference' => $rfq->reference_number,
                            'bid_deadline' => $rfq->bid_deadline,
                            'delivery_date' => $rfq->delivery_date
                        ]),
                        'is_read' => false
                    ]);

                    // Send email notification (if email is configured)
                    if (config('mail.default') !== 'log') {
                        Mail::to($user->email)->send(new \App\Mail\RfqInvitationMail($rfq, $user));
                    }
                }
            }

            // Send email notifications to external emails
            if (!empty($invitedEmails)) {
                foreach ($invitedEmails as $email) {
                    // Send email notification (if email is configured)
                    if (config('mail.default') !== 'log') {
                        Mail::to($email)->send(new \App\Mail\RfqInvitationMail($rfq, null, $email));
                    }
                }
            }

            Log::info("RFQ invitation notifications sent", [
                'rfq_id' => $rfq->id,
                'rfq_title' => $rfq->title,
                'invited_users' => count($invitedUserIds),
                'invited_emails' => count($invitedEmails)
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send RFQ invitation notifications", [
                'rfq_id' => $rfq->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
