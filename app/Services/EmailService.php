<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\GenericEmail;
use App\Mail\RfqInvitation;
use App\Mail\BidConfirmation;

class EmailService
{
    /**
     * Send email using a template.
     */
    public static function sendTemplateEmail($templateSlug, $to, $data = [], $options = [])
    {
        try {
            
            // Get the template
            $template = EmailTemplate::getLatestBySlug($templateSlug);
            
            if (!$template) {
                Log::error("Email template not found: {$templateSlug}");
                return false;
            }


            // Replace placeholders
            $emailContent = $template->replacePlaceholders($data);
            
            
            // Send email
            return self::sendEmail($to, $emailContent['subject'], $emailContent['content'], $options);
            
        } catch (\Exception $e) {
            Log::error("Error sending template email: " . $e->getMessage(), [
                'template_slug' => $templateSlug,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send email using template by type.
     */
    public static function sendEmailByType($type, $to, $data = [], $options = [])
    {
        try {
            // Get default template for this type
            $template = EmailTemplate::getDefaultByType($type);
            
            if (!$template) {
                Log::error("Default email template not found for type: {$type}");
                return false;
            }

            // Replace placeholders
            $emailContent = $template->replacePlaceholders($data);
            
            // Send email
            return self::sendEmail($to, $emailContent['subject'], $emailContent['content'], $options);
            
        } catch (\Exception $e) {
            Log::error("Error sending email by type: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using template ID.
     */
    public static function sendEmailByTemplateId($templateId, $to, $data = [], $options = [])
    {
        try {
            // Get the template
            $template = EmailTemplate::find($templateId);
            
            if (!$template) {
                Log::error("Email template not found with ID: {$templateId}");
                return false;
            }

            // Replace placeholders
            $emailContent = $template->replacePlaceholders($data);
            
            // Send email
            return self::sendEmail($to, $emailContent['subject'], $emailContent['content'], $options);
            
        } catch (\Exception $e) {
            Log::error("Error sending email by template ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send RFQ cancellation notification to suppliers.
     */
    public static function sendRfqCancellation($rfq, $suppliers)
    {
        try {
            $buyer = $rfq->creator;
            $data = [
                'rfq_title' => $rfq->title,
                'rfq_description' => $rfq->description,
                'buyer_name' => $buyer->companies->first()->name ?? $buyer->name,
                'contact_email' => $buyer->email,
                'cancellation_reason' => 'RFQ has been cancelled by the buyer.',
            ];

            foreach ($suppliers as $supplier) {
                $data['supplier_name'] = $supplier->name;
                $data['supplier_email'] = $supplier->email;
                
                Mail::to($supplier->email)->send(new GenericEmail(
                    'RFQ Cancellation: ' . $rfq->title,
                    'rfq-cancellation',
                    $data
                ));
            }


            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send RFQ cancellation emails: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send RFQ invitation to suppliers.
     */
    public static function sendRfqInvitation($rfq, $suppliers, $buyer)
    {
        try {
            $data = [
                'rfq_title' => $rfq->title,
                'rfq_description' => $rfq->description,
                'deadline' => $rfq->deadline ? $rfq->deadline->format('F j, Y \a\t g:i A') : 'TBD',
                'buyer_name' => $buyer->companies->first()->name ?? $buyer->name,
                'rfq_link' => config('app.frontend_url') . '/rfqs/' . $rfq->id,
                'contact_email' => $buyer->email,
            ];

            $successCount = 0;
            foreach ($suppliers as $supplier) {
                $supplierData = array_merge($data, [
                    'supplier_name' => $supplier->companies->first()->name ?? $supplier->name,
                ]);

                // Use dedicated mailable for RFQ invitations
                try {
                    Mail::to($supplier->email)->send(new RfqInvitation($rfq, $supplier, $buyer, $supplierData));
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to send RFQ invitation to {$supplier->email}: " . $e->getMessage());
                }
            }

            return $successCount;
            
        } catch (\Exception $e) {
            Log::error("Error sending RFQ invitations: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send bid confirmation to supplier.
     */
    public static function sendBidConfirmation($bid, $supplier, $rfq)
    {
        try {
            $data = [
                'supplier_name' => $supplier->companies->first()->name ?? $supplier->name,
                'rfq_title' => $rfq->title,
                'bid_amount' => '$' . number_format($bid->total_amount, 2),
                'submission_date' => $bid->created_at ? $bid->created_at->format('F j, Y \a\t g:i A') : 'Unknown',
                'confirmation_number' => 'BID-' . str_pad($bid->id, 6, '0', STR_PAD_LEFT),
                'rfq_deadline' => $rfq->deadline ? $rfq->deadline->format('F j, Y \a\t g:i A') : 'TBD',
            ];

            // Use dedicated mailable for bid confirmations
            Mail::to($supplier->email)->send(new BidConfirmation($bid, $supplier, $rfq, $data));
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error sending bid confirmation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bid notification to buyer.
     */
    public static function sendBidNotification($bid, $supplier, $rfq, $buyer)
    {
        try {
            $data = [
                'supplier_name' => $supplier->companies->first()->name ?? $supplier->name,
                'rfq_title' => $rfq->title,
                'bid_amount' => '$' . number_format($bid->total_amount, 2),
                'submission_date' => $bid->created_at ? $bid->created_at->format('F j, Y \a\t g:i A') : 'Unknown',
                'buyer_name' => $buyer->companies->first()->name ?? $buyer->name,
                'bid_link' => config('app.frontend_url') . '/bids/' . $bid->id,
            ];

            return self::sendEmailByType('bid_submitted', $buyer->email, $data);
            
        } catch (\Exception $e) {
            Log::error("Error sending bid notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send deadline reminder to suppliers.
     */
    public static function sendDeadlineReminder($rfq, $suppliers, $buyer, $daysRemaining)
    {
        try {
            $data = [
                'rfq_title' => $rfq->title,
                'deadline' => $rfq->deadline ? $rfq->deadline->format('F j, Y \a\t g:i A') : 'TBD',
                'days_remaining' => $daysRemaining,
                'rfq_link' => config('app.frontend_url') . '/rfqs/' . $rfq->id,
                'buyer_name' => $buyer->companies->first()->name ?? $buyer->name,
            ];

            $successCount = 0;
            foreach ($suppliers as $supplier) {
                $supplierData = array_merge($data, [
                    'supplier_name' => $supplier->companies->first()->name ?? $supplier->name,
                ]);

                if (self::sendEmailByType('deadline_reminder', $supplier->email, $supplierData)) {
                    $successCount++;
                }
            }

            return $successCount;
            
        } catch (\Exception $e) {
            Log::error("Error sending deadline reminders: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send purchase order notification.
     */
    public static function sendPurchaseOrderNotification($purchaseOrder, $supplier, $rfq, $buyer)
    {
        try {
            $data = [
                'supplier_name' => $supplier->companies->first()->name ?? $supplier->name,
                'po_number' => $purchaseOrder->po_number,
                'po_amount' => '$' . number_format($purchaseOrder->total_amount, 2),
                'rfq_title' => $rfq->title,
                'delivery_date' => $purchaseOrder->delivery_date ? $purchaseOrder->delivery_date->format('F j, Y') : 'TBD',
                'buyer_name' => $buyer->companies->first()->name ?? $buyer->name,
                'po_link' => config('app.frontend_url') . '/purchase-orders/' . $purchaseOrder->id,
            ];

            return self::sendEmailByType('po_generated', $supplier->email, $data);
            
        } catch (\Exception $e) {
            Log::error("Error sending purchase order notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send status change notification.
     */
    public static function sendStatusChangeNotification($user, $rfq, $oldStatus, $newStatus, $changedBy)
    {
        try {
            $data = [
                'user_name' => $user->name,
                'rfq_title' => $rfq->title,
                'old_status' => ucfirst(str_replace('_', ' ', $oldStatus)),
                'new_status' => ucfirst(str_replace('_', ' ', $newStatus)),
                'change_date' => now()->format('F j, Y \a\t g:i A'),
                'changed_by' => $changedBy->name,
                'rfq_link' => config('app.frontend_url') . '/rfqs/' . $rfq->id,
            ];

            return self::sendEmailByType('status_change', $user->email, $data);
            
        } catch (\Exception $e) {
            Log::error("Error sending status change notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send general notification.
     */
    public static function sendGeneralNotification($user, $message, $actionRequired = null, $link = null)
    {
        try {
            $data = [
                'user_name' => $user->name,
                'message' => $message,
                'action_required' => $actionRequired,
                'link' => $link,
                'company_name' => $user->companies->first()->name ?? 'Your Company',
            ];

            return self::sendEmailByType('general_notification', $user->email, $data);
            
        } catch (\Exception $e) {
            Log::error("Error sending general notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using Laravel Mail.
     */
    private static function sendEmail($to, $subject, $content, $options = [])
    {
        try {
            
            // Use Laravel Mail to send the email with Gmail configuration
            Mail::mailer('gmail')->to($to)->send(new \App\Mail\GenericEmail($subject, $content, $options));
            
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error sending email: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Test email template with sample data.
     */
    public static function testTemplate($templateId, $testEmail, $sampleData = [])
    {
        try {
            $template = EmailTemplate::find($templateId);
            
            if (!$template) {
                return false;
            }

            // Replace placeholders
            $emailContent = $template->replacePlaceholders($sampleData);
            
            // Send test email
            return self::sendEmail($testEmail, $emailContent['subject'], $emailContent['content'], ['test' => true]);
            
        } catch (\Exception $e) {
            Log::error("Error testing template: " . $e->getMessage());
            return false;
        }
    }
}
