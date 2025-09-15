<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Get pending supplier approvals.
     */
    public function getPendingSuppliers(Request $request)
    {
        try {
            $suppliers = User::with(['roles', 'companies'])
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'supplier');
                })
                ->where('status', 'pending')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching pending suppliers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending suppliers'
            ], 500);
        }
    }

    /**
     * Approve a supplier.
     */
    public function approveSupplier(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::with(['roles', 'companies'])->findOrFail($id);

            if (!$user->hasRole('supplier')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a supplier'
                ], 400);
            }

            if ($user->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier is not pending approval'
                ], 400);
            }

            // Update user status
            $user->update(['status' => 'active']);

            // Update company status
            if ($user->companies->count() > 0) {
                $user->companies->first()->update(['status' => 'active']);
            }

            // Send approval notification email
            $this->sendApprovalNotification($user, $request->notes);

            Log::info("Supplier approved: {$user->email} by admin ID: " . auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Supplier approved successfully',
                'data' => $user->load('roles', 'companies')
            ]);

        } catch (\Exception $e) {
            Log::error('Error approving supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve supplier'
            ], 500);
        }
    }

    /**
     * Reject a supplier.
     */
    public function rejectSupplier(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::with(['roles', 'companies'])->findOrFail($id);

            if (!$user->hasRole('supplier')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a supplier'
                ], 400);
            }

            if ($user->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier is not pending approval'
                ], 400);
            }

            // Update user status
            $user->update(['status' => 'inactive']);

            // Update company status
            if ($user->companies->count() > 0) {
                $user->companies->first()->update(['status' => 'inactive']);
            }

            // Send rejection notification email
            $this->sendRejectionNotification($user, $request->reason);

            Log::info("Supplier rejected: {$user->email} by admin ID: " . auth()->id() . " Reason: " . $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Supplier rejected successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error rejecting supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject supplier'
            ], 500);
        }
    }

    /**
     * Get system statistics for admin dashboard.
     */
    public function getSystemStats(Request $request)
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_companies' => Company::count(),
                'pending_suppliers' => User::whereHas('roles', function ($query) {
                    $query->where('name', 'supplier');
                })->where('status', 'pending')->count(),
                'active_suppliers' => User::whereHas('roles', function ($query) {
                    $query->where('name', 'supplier');
                })->where('status', 'active')->count(),
                'active_buyers' => User::whereHas('roles', function ($query) {
                    $query->where('name', 'buyer');
                })->where('status', 'active')->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching system stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system statistics'
            ], 500);
        }
    }

    /**
     * Get recent user registrations.
     */
    public function getRecentRegistrations(Request $request)
    {
        try {
            $users = User::with(['roles', 'companies'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching recent registrations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent registrations'
            ], 500);
        }
    }

    /**
     * Update user status (activate/deactivate).
     */
    public function updateUserStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,inactive',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($id);

            // Don't allow admin to deactivate themselves
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update your own status'
                ], 400);
            }

            $user->update(['status' => $request->status]);

            Log::info("User status updated: {$user->email} to {$request->status} by admin ID: " . $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user->load('roles', 'companies')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }

    /**
     * Send approval notification email.
     */
    private function sendApprovalNotification($user, $notes = null)
    {
        // For now, we'll just log the notification. In production, you'd send an email
        Log::info("Approval notification sent to {$user->email}. Notes: " . ($notes ?? 'None'));
        
        // TODO: Implement actual email sending
        // Mail::to($user->email)->send(new SupplierApprovedMail($user, $notes));
    }

    /**
     * Send rejection notification email.
     */
    private function sendRejectionNotification($user, $reason)
    {
        // For now, we'll just log the notification. In production, you'd send an email
        Log::info("Rejection notification sent to {$user->email}. Reason: {$reason}");
        
        // TODO: Implement actual email sending
        // Mail::to($user->email)->send(new SupplierRejectedMail($user, $reason));
    }
}
