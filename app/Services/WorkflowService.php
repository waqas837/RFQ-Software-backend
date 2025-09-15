<?php

namespace App\Services;

use App\Models\Rfq;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\EmailService;

class WorkflowService
{
    /**
     * RFQ status workflow definition
     */
    const STATUS_WORKFLOW = [
        'draft' => [
            'next_states' => ['published', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'published' => [
            'next_states' => ['bidding_open', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'bidding_open' => [
            'next_states' => ['bidding_closed', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'bidding_closed' => [
            'next_states' => ['under_evaluation', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'under_evaluation' => [
            'next_states' => ['awarded', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'awarded' => [
            'next_states' => ['completed', 'cancelled'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'completed' => [
            'next_states' => [],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
        'cancelled' => [
            'next_states' => ['draft'],
            'allowed_roles' => ['buyer', 'admin'],
            'requires_approval' => false,
        ],
    ];

    /**
     * Check if a status transition is allowed
     */
    public static function canTransitionTo(Rfq $rfq, string $newStatus, User $user): bool
    {
        $currentStatus = $rfq->status;
        
        // Admin can always transition any RFQ to any status (except same status)
        if ($user->isAdmin()) {
            return $newStatus !== $currentStatus;
        }
        
        // Check if the transition is allowed
        if (!isset(self::STATUS_WORKFLOW[$currentStatus])) {
            return false;
        }

        $workflow = self::STATUS_WORKFLOW[$currentStatus];
        
        // Check if the new status is in the allowed next states
        if (!in_array($newStatus, $workflow['next_states'])) {
            return false;
        }

        // Check if the user has the required role
        $userRole = $user->role;
        if (!$userRole && $user->roles && $user->roles->count() > 0) {
            $userRole = $user->roles->first()->name;
        }
        
        if (!in_array($userRole, $workflow['allowed_roles'])) {
            return false;
        }

        // Check if approval is required and if the user has approval rights
        if ($workflow['requires_approval'] && !self::hasApprovalRights($user, $rfq)) {
            return false;
        }

        return true;
    }

    /**
     * Get available next statuses for an RFQ
     */
    public static function getAvailableTransitions(Rfq $rfq, User $user): array
    {
        $currentStatus = $rfq->status;
        
        if (!isset(self::STATUS_WORKFLOW[$currentStatus])) {
            return [];
        }

        $workflow = self::STATUS_WORKFLOW[$currentStatus];
        $availableTransitions = [];
        
        // Debug logging
        Log::info("Getting transitions for RFQ {$rfq->id} with status {$currentStatus}", [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_roles' => $user->roles->pluck('name'),
            'workflow' => $workflow
        ]);

        // Admin gets all available transitions PLUS ability to jump to any status
        if ($user->isAdmin()) {
            // First add normal workflow transitions
            foreach ($workflow['next_states'] as $nextStatus) {
                $availableTransitions[] = [
                    'status' => $nextStatus,
                    'label' => self::getStatusLabel($nextStatus),
                    'description' => self::getStatusDescription($nextStatus),
                    'requires_approval' => $workflow['requires_approval'],
                ];
            }
            
            // Admin can also jump to any other status (except current)
            $allStatuses = ['draft', 'published', 'bidding_open', 'bidding_closed', 'under_evaluation', 'awarded', 'completed', 'cancelled'];
            foreach ($allStatuses as $status) {
                if ($status !== $currentStatus && !in_array($status, $workflow['next_states'])) {
                    $availableTransitions[] = [
                        'status' => $status,
                        'label' => self::getStatusLabel($status) . ' (Admin Override)',
                        'description' => self::getStatusDescription($status) . ' - Admin can override normal workflow',
                        'requires_approval' => false,
                    ];
                }
            }
            return $availableTransitions;
        }

        // Regular users follow normal workflow rules
        foreach ($workflow['next_states'] as $nextStatus) {
            $availableTransitions[] = [
                'status' => $nextStatus,
                'label' => self::getStatusLabel($nextStatus),
                'description' => self::getStatusDescription($nextStatus),
                'requires_approval' => $workflow['requires_approval'],
            ];
        }

        return $availableTransitions;
    }

    /**
     * Transition RFQ to a new status
     */
    public static function transitionTo(Rfq $rfq, string $newStatus, User $user, array $metadata = []): bool
    {
        try {
            DB::beginTransaction();

            // Check if transition is allowed
            if (!self::canTransitionTo($rfq, $newStatus, $user)) {
                throw new \Exception("Status transition from {$rfq->status} to {$newStatus} is not allowed");
            }

            $oldStatus = $rfq->status;
            
            // Update RFQ status
            $rfq->status = $newStatus;
            
            // Handle status-specific logic
            self::handleStatusTransition($rfq, $newStatus, $user, $metadata);
            
            $rfq->save();

            // Log the status change
            self::logStatusChange($rfq, $oldStatus, $newStatus, $user, $metadata);

            // Send notifications
            self::sendStatusChangeNotifications($rfq, $oldStatus, $newStatus, $user);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to transition RFQ {$rfq->id} to status {$newStatus}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle status-specific logic
     */
    private static function handleStatusTransition(Rfq $rfq, string $newStatus, User $user, array $metadata): void
    {
        switch ($newStatus) {
            case 'published':
                // Set bidding deadline if not set
                if (!$rfq->bid_deadline) {
                    $rfq->bid_deadline = now()->addDays(14); // Default 2 weeks
                }
                break;

            case 'bidding_open':
                // Send invitations to suppliers
                self::sendSupplierInvitations($rfq);
                break;

            case 'bidding_closed':
                // Close bidding and notify suppliers
                self::closeBidding($rfq);
                break;

            case 'under_evaluation':
                // Start evaluation process
                self::startEvaluation($rfq);
                break;

            case 'awarded':
                // Award the RFQ to selected supplier
                if (isset($metadata['awarded_supplier_id'])) {
                    self::awardToSupplier($rfq, $metadata['awarded_supplier_id']);
                }
                break;

            case 'completed':
                // Mark RFQ as completed
                self::completeRfq($rfq);
                break;

            case 'cancelled':
                // Cancel RFQ and notify all parties
                self::cancelRfq($rfq, $metadata['cancellation_reason'] ?? 'No reason provided');
                break;
        }
    }

    /**
     * Send invitations to suppliers when bidding opens
     */
    private static function sendSupplierInvitations(Rfq $rfq): void
    {
        try {
            $suppliers = $rfq->suppliers;
            $buyer = $rfq->creator;

            if ($suppliers->count() > 0) {
                EmailService::sendRfqInvitation($rfq, $suppliers, $buyer);
                Log::info("RFQ invitations sent for RFQ {$rfq->id}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send RFQ invitations: " . $e->getMessage());
        }
    }

    /**
     * Close bidding and notify suppliers
     */
    private static function closeBidding(Rfq $rfq): void
    {
        try {
            // Notify suppliers that bidding is closed
            $suppliers = $rfq->suppliers;
            foreach ($suppliers as $supplier) {
                EmailService::sendGeneralNotification(
                    $supplier->users->first(),
                    "Bidding for RFQ '{$rfq->title}' has been closed. No more bids will be accepted.",
                    "Evaluation will begin shortly."
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to close bidding: " . $e->getMessage());
        }
    }

    /**
     * Start evaluation process
     */
    private static function startEvaluation(Rfq $rfq): void
    {
        try {
            // Create evaluation record
            // This could be extended to create evaluation criteria, assign evaluators, etc.
            Log::info("Evaluation started for RFQ {$rfq->id}");
        } catch (\Exception $e) {
            Log::error("Failed to start evaluation: " . $e->getMessage());
        }
    }

    /**
     * Award RFQ to selected supplier
     */
    private static function awardToSupplier(Rfq $rfq, int $supplierId): void
    {
        try {
            $supplier = Company::find($supplierId);
            if ($supplier) {
                // Update RFQ with awarded supplier
                $rfq->awarded_supplier_id = $supplierId;
                $rfq->awarded_at = now();
                $rfq->save();

                // Notify awarded supplier
                $supplierUser = $supplier->users->first();
                if ($supplierUser) {
                    EmailService::sendGeneralNotification(
                        $supplierUser,
                        "Congratulations! Your company has been awarded the RFQ '{$rfq->title}'.",
                        "Please review the award details and proceed with order fulfillment."
                    );
                }

                // Notify other suppliers
                $otherSuppliers = $rfq->suppliers()->where('id', '!=', $supplierId)->get();
                foreach ($otherSuppliers as $otherSupplier) {
                    $otherUser = $otherSupplier->users->first();
                    if ($otherUser) {
                        EmailService::sendGeneralNotification(
                            $otherUser,
                            "The RFQ '{$rfq->title}' has been awarded to another supplier.",
                            "Thank you for your participation in this procurement process."
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to award RFQ: " . $e->getMessage());
        }
    }

    /**
     * Complete RFQ
     */
    private static function completeRfq(Rfq $rfq): void
    {
        try {
            $rfq->completed_at = now();
            $rfq->save();

            Log::info("RFQ {$rfq->id} marked as completed");
        } catch (\Exception $e) {
            Log::error("Failed to complete RFQ: " . $e->getMessage());
        }
    }

    /**
     * Cancel RFQ
     */
    private static function cancelRfq(Rfq $rfq, string $reason): void
    {
        try {
            $rfq->cancellation_reason = $reason;
            $rfq->cancelled_at = now();
            $rfq->save();

            // Notify all suppliers
            $suppliers = $rfq->suppliers;
            foreach ($suppliers as $supplier) {
                $supplierUser = $supplier->users->first();
                if ($supplierUser) {
                    EmailService::sendGeneralNotification(
                        $supplierUser,
                        "The RFQ '{$rfq->title}' has been cancelled.",
                        "Reason: {$reason}"
                    );
                }
            }

            Log::info("RFQ {$rfq->id} cancelled: {$reason}");
        } catch (\Exception $e) {
            Log::error("Failed to cancel RFQ: " . $e->getMessage());
        }
    }

    /**
     * Check if user has approval rights
     */
    private static function hasApprovalRights(User $user, Rfq $rfq): bool
    {
        // Admin always has approval rights
        if ($user->role === 'admin') {
            return true;
        }

        // Buyer can approve their own RFQs if they have approval rights
        if ($user->role === 'buyer' && $user->id === $rfq->created_by) {
            // Check if user has approval permissions (this could be extended with a permissions system)
            return true;
        }

        return false;
    }

    /**
     * Log status change
     */
    private static function logStatusChange(Rfq $rfq, string $oldStatus, string $newStatus, User $user, array $metadata): void
    {
        // This could be extended to log to an activity log table
        Log::info("RFQ {$rfq->id} status changed from {$oldStatus} to {$newStatus} by user {$user->id}", [
            'rfq_id' => $rfq->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Send status change notifications
     */
    private static function sendStatusChangeNotifications(Rfq $rfq, string $oldStatus, string $newStatus, User $user): void
    {
        try {
            // Notify creator about status change
            if ($rfq->creator && $rfq->creator->id !== $user->id) {
                EmailService::sendStatusChangeNotification(
                    $rfq->creator,
                    $rfq,
                    $oldStatus,
                    $newStatus,
                    $user
                );
            }

            // Notify suppliers about important status changes
            if (in_array($newStatus, ['bidding_open', 'bidding_closed', 'awarded', 'cancelled'])) {
                $suppliers = $rfq->suppliers;
                foreach ($suppliers as $supplier) {
                    $supplierUser = $supplier->users->first();
                    if ($supplierUser) {
                        EmailService::sendGeneralNotification(
                            $supplierUser,
                            "RFQ '{$rfq->title}' status has changed to " . self::getStatusLabel($newStatus),
                            "Please review the updated status and take any required actions."
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to send status change notifications: " . $e->getMessage());
        }
    }

    /**
     * Get human-readable status label
     */
    public static function getStatusLabel(string $status): string
    {
        $labels = [
            'draft' => 'Draft',
            'published' => 'Published',
            'bidding_open' => 'Bidding Open',
            'bidding_closed' => 'Bidding Closed',
            'under_evaluation' => 'Under Evaluation',
            'awarded' => 'Awarded',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Get status description
     */
    public static function getStatusDescription(string $status): string
    {
        $descriptions = [
            'draft' => 'RFQ is in draft mode and can be edited',
            'published' => 'RFQ is published and visible to suppliers',
            'bidding_open' => 'Suppliers can submit bids',
            'bidding_closed' => 'Bidding period has ended',
            'under_evaluation' => 'Bids are being evaluated',
            'awarded' => 'RFQ has been awarded to a supplier',
            'completed' => 'RFQ process is complete',
            'cancelled' => 'RFQ has been cancelled',
        ];

        return $descriptions[$status] ?? 'No description available';
    }

    /**
     * Get workflow statistics for a user
     */
    public static function getWorkflowStats(User $user): array
    {
        $query = Rfq::query();

        if ($user->role === 'buyer') {
            $query->where('created_by', $user->id);
        }

        $stats = [
            'total' => $query->count(),
            'draft' => $query->where('status', 'draft')->count(),
            'published' => $query->where('status', 'published')->count(),
            'bidding_open' => $query->where('status', 'bidding_open')->count(),
            'bidding_closed' => $query->where('status', 'bidding_closed')->count(),
            'under_evaluation' => $query->where('status', 'under_evaluation')->count(),
            'awarded' => $query->where('status', 'awarded')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'cancelled' => $query->where('status', 'cancelled')->count(),
        ];

        return $stats;
    }
}
