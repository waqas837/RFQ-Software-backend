<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Negotiation;
use App\Models\NegotiationMessage;
use App\Models\NegotiationAttachment;
use App\Models\Bid;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\NegotiationMessageSent;
use App\Events\NegotiationStatusChanged;

class NegotiationController extends Controller
{
    /**
     * Get all negotiations for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $negotiations = Negotiation::with([
                'rfq', 
                'bid', 
                'initiator.companies', 
                'supplier.companies', 
                'latestMessage',
                'messages' => function($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->forUser($user->id)
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->rfq_id, function ($query, $rfqId) {
                $query->where('rfq_id', $rfqId);
            })
            ->orderBy('last_activity_at', 'desc')
            ->paginate($request->per_page ?? 15);

            // Add unread messages count and primary company for each negotiation
            $negotiations->getCollection()->transform(function ($negotiation) use ($user) {
                $negotiation->unread_messages_count = $negotiation->getUnreadMessagesCount($user->id);
                
                // Add primary company information
                if ($negotiation->initiator) {
                    $negotiation->initiator->primary_company = $negotiation->initiator->primaryCompany();
                }
                if ($negotiation->supplier) {
                    $negotiation->supplier->primary_company = $negotiation->supplier->primaryCompany();
                }
                
                return $negotiation;
            });

            return response()->json([
                'success' => true,
                'data' => $negotiations,
                'message' => 'Negotiations retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching negotiations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve negotiations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific negotiation with messages.
     */
    public function show($id)
    {
        try {
            $negotiation = Negotiation::with([
                'rfq:id,title,reference_number,currency',
                'bid:id,rfq_id,total_amount,currency,submitted_by',
                'initiator:id,name,email',
                'initiator.company:id,name',
                'supplier:id,name,email',
                'supplier.company:id,name',
                'messages' => function($query) {
                    $query->orderBy('created_at', 'asc')
                          ->with(['sender:id,name', 'attachments:id,message_id,filename,original_name,mime_type,file_size']);
                },
                'attachments:id,negotiation_id,filename,original_name,mime_type,file_size'
            ])->findOrFail($id);

            // The purchase_order_id is now directly available from the negotiation model
            // No need to query separately since it's a direct column


            // Mark messages as read for the current user
            $negotiation->markMessagesAsRead(request()->user()->id);

            return response()->json([
                'success' => true,
                'data' => $negotiation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Negotiation not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Start a new negotiation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bid_id' => 'required|exists:bids,id',
            'initial_message' => 'required|string|max:1000',
            'counter_offer_data' => 'nullable|array',
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
            $bid = Bid::with(['rfq', 'supplier', 'supplierCompany'])->findOrFail($request->bid_id);

            // Check if user has permission to start negotiation
            if ($user->role !== 'buyer' && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can start negotiations'
                ], 403);
            }

            // Check if bid belongs to user's RFQ
            if ($bid->rfq->created_by !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only negotiate on your own RFQs'
                ], 403);
            }

            // Check if negotiation already exists for this bid
            $existingNegotiation = Negotiation::where('bid_id', $request->bid_id)->first();
            if ($existingNegotiation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Negotiation already exists for this bid'
                ], 400);
            }

            $negotiation = Negotiation::create([
                'rfq_id' => $bid->rfq_id,
                'bid_id' => $bid->id,
                'initiated_by' => $user->id,
                'supplier_id' => $bid->submitted_by,
                'status' => 'active',
                'initial_message' => $request->initial_message,
                'counter_offer_data' => $request->counter_offer_data,
                'last_activity_at' => now(),
            ]);

            // Create initial message
            NegotiationMessage::create([
                'negotiation_id' => $negotiation->id,
                'sender_id' => $user->id,
                'message' => $request->initial_message,
                'message_type' => 'text',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Negotiation started successfully',
                'data' => $negotiation->load(['rfq', 'bid', 'initiator', 'supplier'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating negotiation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start negotiation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message in a negotiation.
     */
    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'message_type' => 'required|in:text,counter_offer,acceptance,rejection',
            'offer_data' => 'nullable|array',
            'offer_status' => 'nullable|in:accepted,rejected,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $negotiation = Negotiation::findOrFail($id);
            $user = $request->user();

            // Check if user is part of this negotiation
            if ($negotiation->initiated_by !== $user->id && $negotiation->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to send messages in this negotiation'
                ], 403);
            }

            // Allow messages even after rejection/closure for continued discussion

            $message = NegotiationMessage::create([
                'negotiation_id' => $negotiation->id,
                'sender_id' => $user->id,
                'message' => $request->message,
                'message_type' => $request->message_type,
                'offer_data' => $request->offer_data,
                'offer_status' => $request->offer_status,
                'is_read' => false,
            ]);

            // Update negotiation last activity
            $negotiation->updateLastActivity();

            // Handle acceptance/rejection logic
            if ($request->message_type === 'acceptance') {
                \Log::info('Acceptance message received, closing negotiation', [
                    'negotiation_id' => $negotiation->id,
                    'old_status' => $negotiation->status
                ]);
                
                $negotiation->update([
                    'status' => 'closed',
                    'closed_at' => now()
                ]);
                
                // Update the most recent pending counter offer to 'accepted' status
                NegotiationMessage::where('negotiation_id', $negotiation->id)
                    ->where('message_type', 'counter_offer')
                    ->whereNull('offer_status')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                    ->update(['offer_status' => 'accepted']);
                
                \Log::info('Negotiation closed successfully', [
                    'negotiation_id' => $negotiation->id,
                    'new_status' => $negotiation->fresh()->status
                ]);
                
                // Broadcast status change
                broadcast(new NegotiationStatusChanged($negotiation, 'active', 'closed'))->toOthers();
            }
            
            // Handle rejection logic
            if ($request->message_type === 'rejection') {
                // Update the most recent pending counter offer to 'rejected' status
                NegotiationMessage::where('negotiation_id', $negotiation->id)
                    ->where('message_type', 'counter_offer')
                    ->whereNull('offer_status')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                    ->update(['offer_status' => 'rejected']);
            }
            
            // Handle withdrawal/cancellation logic
            if ($request->offer_status === 'cancelled') {
                // Update the most recent counter offer to 'cancelled' status
                NegotiationMessage::where('negotiation_id', $negotiation->id)
                    ->where('message_type', 'counter_offer')
                    ->whereNull('offer_status')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                    ->update(['offer_status' => 'cancelled']);
            }

            // Handle reopening negotiation for new counter offers
            if ($request->message_type === 'counter_offer' && $negotiation->status === 'closed') {
                \Log::info('New counter offer received, reopening negotiation', [
                    'negotiation_id' => $negotiation->id,
                    'old_status' => $negotiation->status
                ]);
                
                $negotiation->update([
                    'status' => 'active',
                    'closed_at' => null
                ]);
                
                \Log::info('Negotiation reopened successfully', [
                    'negotiation_id' => $negotiation->id,
                    'new_status' => $negotiation->fresh()->status
                ]);
                
                // Broadcast status change
                broadcast(new NegotiationStatusChanged($negotiation, 'closed', 'active'))->toOthers();
            }

            // Broadcast the message to all participants
            broadcast(new NegotiationMessageSent($message))->toOthers();

            // Create notification for the other party
            $recipientId = ($negotiation->initiated_by === $user->id) ? $negotiation->supplier_id : $negotiation->initiated_by;
            
            \App\Models\Notification::create([
                'user_id' => $recipientId,
                'type' => 'negotiation_message',
                'title' => 'New Negotiation Message',
                'message' => "You have received a new message in negotiation for RFQ: {$negotiation->rfq->title}",
                'data' => json_encode([
                    'negotiation_id' => $negotiation->id,
                    'rfq_id' => $negotiation->rfq_id,
                    'rfq_title' => $negotiation->rfq->title,
                    'sender_name' => $user->name,
                    'message_preview' => substr($request->message, 0, 100)
                ]),
                'is_read' => false
            ]);

            // If it's an acceptance or rejection, close the negotiation
            if (in_array($request->message_type, ['acceptance', 'rejection'])) {
                $oldStatus = $negotiation->status;
                $negotiation->close();
                broadcast(new NegotiationStatusChanged($negotiation, $oldStatus, 'closed'))->toOthers();
            }

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $message->load('sender')
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload attachment to a negotiation.
     */
    public function uploadAttachment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'message_id' => 'nullable|exists:negotiation_messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $negotiation = Negotiation::findOrFail($id);
            $user = $request->user();

            // Check if user is part of this negotiation
            if ($negotiation->initiated_by !== $user->id && $negotiation->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to upload files to this negotiation'
                ], 403);
            }

            $file = $request->file('file');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('negotiation-attachments', $filename, 'public');

            $attachment = NegotiationAttachment::create([
                'negotiation_id' => $negotiation->id,
                'message_id' => $request->message_id,
                'uploaded_by' => $user->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $attachment
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading attachment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close a negotiation.
     */
    public function close($id)
    {
        try {
            $negotiation = Negotiation::findOrFail($id);
            $user = request()->user();

            // Check if user is part of this negotiation
            if ($negotiation->initiated_by !== $user->id && $negotiation->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to close this negotiation'
                ], 403);
            }

            $negotiation->close();

            return response()->json([
                'success' => true,
                'message' => 'Negotiation closed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close negotiation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a negotiation.
     */
    public function cancel($id)
    {
        try {
            $negotiation = Negotiation::findOrFail($id);
            $user = request()->user();

            // Check if user is part of this negotiation
            if ($negotiation->initiated_by !== $user->id && $negotiation->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to cancel this negotiation'
                ], 403);
            }

            $negotiation->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Negotiation cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel negotiation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get negotiation statistics for the user.
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            $stats = [
                'total_negotiations' => Negotiation::forUser($user->id)->count(),
                'active_negotiations' => Negotiation::forUser($user->id)->active()->count(),
                'closed_negotiations' => Negotiation::forUser($user->id)->where('status', 'closed')->count(),
                'cancelled_negotiations' => Negotiation::forUser($user->id)->where('status', 'cancelled')->count(),
                'unread_messages' => NegotiationMessage::whereHas('negotiation', function($query) use ($user) {
                    $query->forUser($user->id);
                })->where('sender_id', '!=', $user->id)->where('is_read', false)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get negotiation statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a negotiation.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $negotiation = Negotiation::where('id', $id)
                ->where(function($query) use ($user) {
                    $query->where('initiated_by', $user->id)
                          ->orWhere('supplier_id', $user->id);
                })
                ->first();

            if (!$negotiation) {
                // Check if negotiation exists at all
                $exists = Negotiation::where('id', $id)->exists();
                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Negotiation not found'
                    ], 404);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to delete this negotiation'
                    ], 403);
                }
            }

            // Delete related data
            $negotiation->messages()->delete();
            $negotiation->attachments()->delete();
            
            // Delete the negotiation
            $negotiation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Negotiation deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting negotiation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete negotiation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}