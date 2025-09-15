<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Rfq;
use App\Models\Bid;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationEmail;

class NotificationService
{
    /**
     * Create and send a notification.
     */
    public function createNotification(
        string $type,
        string $title,
        string $message,
        int $userId,
        ?int $relatedUserId = null,
        ?int $relatedEntityId = null,
        ?string $relatedEntityType = null,
        ?array $data = null,
        bool $sendEmail = true
    ): Notification {
        $notification = Notification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'user_id' => $userId,
            'related_user_id' => $relatedUserId,
            'related_entity_id' => $relatedEntityId,
            'related_entity_type' => $relatedEntityType,
            'data' => $data,
        ]);

        // Send email notification if requested
        if ($sendEmail) {
            $this->sendEmailNotification($notification);
        }

        return $notification;
    }

    /**
     * Send email notification.
     */
    public function sendEmailNotification(Notification $notification): void
    {
        try {
            $user = $notification->user;
            
            // For testing, send all emails to test email
            $testEmail = env('TEST_EMAIL', 'waqaskhanbughlani1124@gmail.com');
            Mail::to($testEmail)->send(new NotificationEmail($notification));
            
            $notification->markEmailAsSent();
        } catch (\Exception $e) {
            Log::error('Failed to send notification email: ' . $e->getMessage());
        }
    }

    /**
     * Notify suppliers about new RFQ.
     */
    public function notifySuppliersAboutNewRfq(Rfq $rfq): void
    {
        $suppliers = $rfq->suppliers;
        
        foreach ($suppliers as $supplier) {
            $user = $supplier->users->first();
            if ($user) {
                $this->createNotification(
                    Notification::TYPE_RFQ_CREATED,
                    'New RFQ Available',
                    "A new RFQ '{$rfq->title}' has been published and is available for bidding.",
                    $user->id,
                    $rfq->creator->id,
                    $rfq->id,
                    'Rfq',
                    ['rfq_title' => $rfq->title, 'rfq_id' => $rfq->id]
                );
            }
        }
    }

    /**
     * Notify buyer about new bid submission.
     */
    public function notifyBuyerAboutNewBid(Bid $bid): void
    {
        $rfq = $bid->rfq;
        $buyer = $rfq->creator;
        $supplier = $bid->supplier;

        $this->createNotification(
            Notification::TYPE_BID_SUBMITTED,
            'New Bid Submitted',
            "A new bid has been submitted for RFQ '{$rfq->title}' by {$supplier->name}.",
            $buyer->id,
            $supplier->users->first()?->id,
            $bid->id,
            'Bid',
            [
                'rfq_title' => $rfq->title,
                'bid_id' => $bid->id,
                'supplier_name' => $supplier->name,
                'bid_amount' => $bid->total_amount
            ]
        );
    }

    /**
     * Notify supplier about bid award.
     */
    public function notifySupplierAboutBidAward(Bid $bid): void
    {
        $rfq = $bid->rfq;
        $supplier = $bid->supplier;
        $supplierUser = $supplier->users->first();

        if ($supplierUser) {
            $this->createNotification(
                Notification::TYPE_BID_AWARDED,
                'Bid Awarded!',
                "Congratulations! Your bid for RFQ '{$rfq->title}' has been awarded.",
                $supplierUser->id,
                $rfq->creator->id,
                $bid->id,
                'Bid',
                [
                    'rfq_title' => $rfq->title,
                    'bid_id' => $bid->id,
                    'bid_amount' => $bid->total_amount
                ]
            );
        }
    }

    /**
     * Notify supplier about bid rejection.
     */
    public function notifySupplierAboutBidRejection(Bid $bid): void
    {
        $rfq = $bid->rfq;
        $supplier = $bid->supplier;
        $supplierUser = $supplier->users->first();

        if ($supplierUser) {
            $this->createNotification(
                Notification::TYPE_BID_REJECTED,
                'Bid Not Selected',
                "Your bid for RFQ '{$rfq->title}' was not selected this time.",
                $supplierUser->id,
                $rfq->creator->id,
                $bid->id,
                'Bid',
                [
                    'rfq_title' => $rfq->title,
                    'bid_id' => $bid->id
                ]
            );
        }
    }

    /**
     * Notify supplier about new Purchase Order.
     */
    public function notifySupplierAboutNewPO(PurchaseOrder $purchaseOrder): void
    {
        $supplier = $purchaseOrder->supplierCompany;
        $supplierUser = $supplier->users->first();

        if ($supplierUser) {
            $this->createNotification(
                Notification::TYPE_PO_CREATED,
                'New Purchase Order',
                "A new Purchase Order #{$purchaseOrder->po_number} has been created for your awarded bid.",
                $supplierUser->id,
                $purchaseOrder->buyerCompany->users->first()?->id,
                $purchaseOrder->id,
                'PurchaseOrder',
                [
                    'po_number' => $purchaseOrder->po_number,
                    'po_id' => $purchaseOrder->id,
                    'rfq_title' => $purchaseOrder->rfq->title ?? 'N/A'
                ]
            );
        }
    }

    /**
     * Notify buyer about PO status update.
     */
    public function notifyBuyerAboutPOStatusUpdate(PurchaseOrder $purchaseOrder, string $status): void
    {
        $buyer = $purchaseOrder->buyerCompany->users->first();
        $supplier = $purchaseOrder->supplierCompany;

        if ($buyer) {
            $statusMessages = [
                'sent_to_supplier' => 'Purchase Order has been sent to supplier',
                'in_progress' => 'Purchase Order is now in progress',
                'delivered' => 'Purchase Order has been delivered',
                'completed' => 'Purchase Order has been completed'
            ];

            $this->createNotification(
                Notification::TYPE_PO_SENT,
                'PO Status Update',
                "Purchase Order #{$purchaseOrder->po_number} - " . ($statusMessages[$status] ?? $status),
                $buyer->id,
                $supplier->users->first()?->id,
                $purchaseOrder->id,
                'PurchaseOrder',
                [
                    'po_number' => $purchaseOrder->po_number,
                    'po_id' => $purchaseOrder->id,
                    'status' => $status,
                    'supplier_name' => $supplier->name
                ]
            );
        }
    }

    /**
     * Notify admin about new user registration.
     */
    public function notifyAdminAboutNewUser(User $user): void
    {
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->createNotification(
                Notification::TYPE_USER_REGISTERED,
                'New User Registration',
                "A new {$user->role} user '{$user->name}' has registered and needs approval.",
                $admin->id,
                $user->id,
                $user->id,
                'User',
                [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role
                ]
            );
        }
    }

    /**
     * Notify user about supplier approval.
     */
    public function notifyUserAboutSupplierApproval(User $user): void
    {
        $this->createNotification(
            Notification::TYPE_SUPPLIER_APPROVED,
            'Account Approved',
            'Your supplier account has been approved. You can now access all features.',
            $user->id,
            null,
            $user->id,
            'User',
            [
                'user_name' => $user->name,
                'approval_date' => now()->toDateString()
            ]
        );
    }

    /**
     * Get unread notifications count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark all notifications as read for user.
     */
    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get recent notifications for user.
     */
    public function getRecentNotifications(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('user_id', $userId)
            ->with(['relatedUser'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}