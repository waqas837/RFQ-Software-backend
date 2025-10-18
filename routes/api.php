<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemTemplateController;
use App\Http\Controllers\Api\RfqController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NegotiationController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\CurrencyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1'); // 5 registrations per minute
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1'); // 10 login attempts per minute
Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1'); // 5 verifications per minute
Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1'); // 3 resends per minute
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1'); // 3 password resets per minute
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1'); // 5 password resets per minute
Route::post('/profile/verify-email-update', [UserController::class, 'verifyEmailUpdate']);
Route::post('/check-status', [AuthController::class, 'checkStatus']);

// Public template downloads
Route::get('/rfqs/template/{type}', [RfqController::class, 'downloadTemplate']);

// Supplier registration routes (public)
Route::post('/supplier-register', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'registerFromInvitation']);
Route::get('/supplier-invitation/validate', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'validateInvitation']);
Route::post('/check-user-exists', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'checkUserExists']);

// Developer registration routes
Route::post('/developer/register', [DeveloperController::class, 'register'])->middleware('throttle:3,1'); // 3 developer registrations per minute
Route::post('/developer/verify-email', [DeveloperController::class, 'verifyEmail'])->middleware('throttle:5,1'); // 5 verifications per minute
Route::post('/developer/resend-verification', [DeveloperController::class, 'resendVerification'])->middleware('throttle:2,1'); // 2 resends per minute

// Public file downloads - outside protected routes
Route::get('/downloads/{filename}', function ($filename) {
    // Sanitize filename
    $filename = basename($filename);
    
    // Check exports directory first
    $exportPath = storage_path('app/exports/' . $filename);
    if (file_exists($exportPath)) {
        return response()->file($exportPath, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
    
    // Check Filestoupload directory for sample files
    $filePath = base_path('Filestoupload/' . $filename);
    if (file_exists($filePath)) {
        return response()->file($filePath, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
    
    return response()->json(['error' => 'File not found: ' . $filename], 404);
})->name('downloads');

// Serve attachment files from storage
Route::get('/attachments/{path}', function ($path) {
    // Try public storage first (where files are actually stored)
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        // Fallback to regular storage
        $filePath = storage_path('app/' . $path);
        
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found: ' . $path], 404);
        }
    }
    
    $filename = basename($filePath);
    $mimeType = mime_content_type($filePath);
    
    return response()->download($filePath, $filename, [
        'Content-Type' => $mimeType
    ]);
})->where('path', '.*');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1'); // 5 password changes per minute

    // Get users for invitations (Buyers and Admins)
    Route::get('/users/for-invitations', [UserController::class, 'getUsersForInvitations']);
    
    // User profile routes (authenticated users)
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/request-email-update', [UserController::class, 'requestEmailUpdate'])->middleware('throttle:3,1'); // 3 email update requests per minute
    Route::get('/users/others', [UserController::class, 'getOtherUsers']);
    Route::get('/users/{id}/profile', [UserController::class, 'getUserProfile']);

    // User management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::get('/companies', [UserController::class, 'companies']);
        
        // Company management (Admin only)
        Route::apiResource('companies', CompanyController::class);
        Route::patch('/companies/{company}/status', [CompanyController::class, 'updateStatus']);
        
        // Admin-specific routes
        Route::get('/admin/pending-suppliers', [AdminController::class, 'getPendingSuppliers']);
        Route::post('/admin/suppliers/{id}/approve', [AdminController::class, 'approveSupplier']);
        Route::post('/admin/suppliers/{id}/reject', [AdminController::class, 'rejectSupplier']);
        Route::get('/admin/system-stats', [AdminController::class, 'getSystemStats']);
        Route::get('/admin/recent-registrations', [AdminController::class, 'getRecentRegistrations']);
        Route::post('/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
        
        // Email Template Management (Admin only)
        // Specific routes must come BEFORE apiResource to avoid conflicts
        Route::get('/email-templates/types', [EmailTemplateController::class, 'getTypes']);
        Route::get('/email-templates/slug/{slug}', [EmailTemplateController::class, 'getBySlug']);
        Route::get('/email-templates/type/{type}/default', [EmailTemplateController::class, 'getDefaultByType']);
        Route::get('/email-templates/placeholders/{type}', [EmailTemplateController::class, 'getPlaceholders']);
        Route::post('/email-templates/{id}/version', [EmailTemplateController::class, 'createVersion']);
        Route::post('/email-templates/{id}/preview', [EmailTemplateController::class, 'preview']);
        Route::apiResource('email-templates', EmailTemplateController::class);
    });

    // Category management (Admin only)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/categories/active', [CategoryController::class, 'active']);
        Route::get('/categories/roots', [CategoryController::class, 'roots']);
    });

    // Item management - specific routes must come BEFORE apiResource
    Route::get('/items/templates', [ItemController::class, 'templates']);
    Route::get('/items/field-types', [ItemController::class, 'fieldTypes']);
    Route::post('/items/bulk-import', [ItemController::class, 'bulkImport']);
    Route::post('/items/bulk-export', [ItemController::class, 'bulkExport']);
    Route::get('/categories', [ItemController::class, 'categories']);
    
    // Item file attachments (with rate limiting)
    Route::post('/items/{id}/attachments', [ItemController::class, 'uploadAttachment'])->middleware('throttle:10,1'); // 10 uploads per minute
    Route::get('/items/{id}/attachments', [ItemController::class, 'getAttachments']);
    Route::delete('/items/{itemId}/attachments/{attachmentId}', [ItemController::class, 'deleteAttachment'])->middleware('throttle:20,1'); // 20 deletions per minute
    Route::put('/items/{itemId}/attachments/{attachmentId}/primary', [ItemController::class, 'setPrimaryAttachment'])->middleware('throttle:30,1'); // 30 updates per minute
    
    Route::apiResource('items', ItemController::class);
    
        // Item template management - specific routes must come BEFORE apiResource
        Route::get('/item-templates/categories', [ItemTemplateController::class, 'categories']);
        Route::apiResource('item-templates', ItemTemplateController::class);

    // RFQ management - specific routes must come BEFORE apiResource
    Route::post('/rfqs/import', [RfqController::class, 'import']);
    Route::get('/rfqs/test-auth', [RfqController::class, 'testAuth']);
    Route::get('/rfqs/workflow-stats', [RfqController::class, 'getWorkflowStats']);
    Route::apiResource('rfqs', RfqController::class);
    Route::post('/rfqs/{rfq}/publish', [RfqController::class, 'publish']);
    Route::post('/rfqs/{rfq}/close', [RfqController::class, 'close']);
    Route::post('/rfqs/{rfq}/cancel', [RfqController::class, 'cancel']);
    Route::get('/rfqs/{rfq}/workflow-transitions', [RfqController::class, 'getWorkflowTransitions']);
    Route::post('/rfqs/{rfq}/transition-status', [RfqController::class, 'transitionStatus']);

    // Supplier management
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/{supplier}/approve', [SupplierController::class, 'approve']);
    Route::post('/suppliers/{supplier}/reject', [SupplierController::class, 'reject']);

    // Bid management
    Route::apiResource('bids', BidController::class);
    Route::post('/bids/{bid}/submit', [BidController::class, 'submit']);
    Route::post('/bids/{bid}/evaluate', [BidController::class, 'evaluate']);
    Route::post('/bids/{bid}/award', [BidController::class, 'award']);

    // Purchase Order management
    Route::get('/purchase-orders/export', [PurchaseOrderController::class, 'export']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
    Route::post('/purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm']);
    Route::post('/purchase-orders/{purchaseOrder}/confirm-delivery', [PurchaseOrderController::class, 'confirmDelivery']);
    Route::post('/purchase-orders/create-from-negotiation/{negotiationId}', [PurchaseOrderController::class, 'createFromNegotiation']);
    Route::get('/debug/negotiation/{id}', function($id) {
        $negotiation = \App\Models\Negotiation::with(['messages'])->find($id);
        $lastMessage = $negotiation->messages()->orderBy('created_at', 'desc')->first();
        return response()->json([
            'negotiation' => [
                'id' => $negotiation->id,
                'status' => $negotiation->status,
                'closed_at' => $negotiation->closed_at,
                'updated_at' => $negotiation->updated_at
            ],
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'message_type' => $lastMessage->message_type,
                'offer_status' => $lastMessage->offer_status,
                'created_at' => $lastMessage->created_at
            ] : null
        ]);
    });
    
    // Debug PO visibility for suppliers
    Route::get('/debug/po-visibility', function(Request $request) {
        $user = $request->user();
        $supplierCompany = $user->companies->first();
        
        // Get all POs
        $allPOs = \App\Models\PurchaseOrder::with(['supplierCompany', 'buyerCompany'])->get();
        
        // Get POs filtered for this supplier
        $filteredPOs = \App\Models\PurchaseOrder::with(['supplierCompany', 'buyerCompany'])
            ->when($supplierCompany, function ($query) use ($supplierCompany) {
                $query->where('supplier_company_id', $supplierCompany->id);
            })
            ->get();
            
        return response()->json([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'supplier_company_id' => $supplierCompany ? $supplierCompany->id : 'none',
            'all_pos_count' => $allPOs->count(),
            'filtered_pos_count' => $filteredPOs->count(),
            'all_pos' => $allPOs->map(function($po) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_company_id' => $po->supplier_company_id,
                    'buyer_company_id' => $po->buyer_company_id,
                    'status' => $po->status
                ];
            }),
            'filtered_pos' => $filteredPOs->map(function($po) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_company_id' => $po->supplier_company_id,
                    'buyer_company_id' => $po->buyer_company_id,
                    'status' => $po->status
                ];
            })
        ]);
    });
    
    // PO Modification routes
    Route::post('/purchase-orders/{purchaseOrder}/modify', [PurchaseOrderController::class, 'modify']);
    Route::post('/purchase-orders/{purchaseOrder}/modifications/{modification}/approve', [PurchaseOrderController::class, 'approveModification']);
    Route::post('/purchase-orders/{purchaseOrder}/modifications/{modification}/reject', [PurchaseOrderController::class, 'rejectModification']);
    
    // PO Tracking routes
    Route::get('/purchase-orders/{purchaseOrder}/status-history', [PurchaseOrderController::class, 'statusHistory']);
    Route::get('/purchase-orders/{purchaseOrder}/modifications', [PurchaseOrderController::class, 'modifications']);

    // Reports and Analytics
    Route::get('/reports/dashboard', [ReportsController::class, 'dashboard']);
    Route::get('/reports/rfq-analysis', [ReportsController::class, 'rfqAnalysis']);
    Route::get('/reports/supplier-performance', [ReportsController::class, 'supplierPerformance']);
    Route::post('/reports/export', [ReportsController::class, 'export']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications/recent', [NotificationController::class, 'recent']);
    Route::get('/notifications/stats', [NotificationController::class, 'stats']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{id}/mark-unread', [NotificationController::class, 'markAsUnread']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Currency management
    Route::get('/currencies', [CurrencyController::class, 'getSupportedCurrencies']);
    Route::get('/currencies/conversion-data', [CurrencyController::class, 'getConversionData']);
    Route::post('/currencies/convert', [CurrencyController::class, 'convertAmount']);
    Route::get('/currencies/symbols', [CurrencyController::class, 'getCurrencySymbols']);
    Route::post('/currencies/convert-negotiation', [CurrencyController::class, 'convertNegotiationAmount']);
    
    // Currency management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/currencies/rates', [CurrencyController::class, 'getExchangeRates']);
        Route::post('/currencies/rates', [CurrencyController::class, 'updateExchangeRates']);
    });

    // Negotiation management
    Route::get('/negotiations', [NegotiationController::class, 'index']);
    Route::get('/negotiations/{id}', [NegotiationController::class, 'show']);
    Route::post('/negotiations', [NegotiationController::class, 'store']);
    Route::post('/negotiations/{id}/messages', [NegotiationController::class, 'sendMessage']);
    Route::post('/negotiations/{id}/attachments', [NegotiationController::class, 'uploadAttachment'])->middleware('throttle:10,1'); // 10 uploads per minute
    Route::post('/negotiations/{id}/close', [NegotiationController::class, 'close']);
    Route::post('/negotiations/{id}/cancel', [NegotiationController::class, 'cancel']);
    Route::delete('/negotiations/{id}', [NegotiationController::class, 'destroy']);
    Route::get('/negotiations/stats/overview', [NegotiationController::class, 'getStats']);
    
    // Developer dashboard
    Route::get('/developer/dashboard', [DeveloperController::class, 'dashboard']);
    
    // API Key management
    Route::apiResource('api-keys', ApiKeyController::class);
    Route::get('/api-keys/usage/statistics', [ApiKeyController::class, 'usage']);
});

// Public API routes (protected by API key authentication)
Route::middleware(['api.key', 'api.usage'])->group(function () {
    // RFQ endpoints
    Route::get('/public/rfqs', [RfqController::class, 'index']);
    Route::get('/public/rfqs/{id}', [RfqController::class, 'show']);
    
    // Bid endpoints
    Route::get('/public/bids', [BidController::class, 'index']);
    Route::get('/public/bids/{id}', [BidController::class, 'show']);
    
    // Purchase Order endpoints
    Route::get('/public/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/public/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    
    // Company endpoints
    Route::get('/public/companies', [CompanyController::class, 'index']);
    Route::get('/public/companies/{id}', [CompanyController::class, 'show']);
    
    // Category endpoints
    Route::get('/public/categories', [CategoryController::class, 'index']);
    Route::get('/public/categories/{id}', [CategoryController::class, 'show']);
    
    // Item endpoints
    Route::get('/public/items', [ItemController::class, 'index']);
    Route::get('/public/items/{id}', [ItemController::class, 'show']);
});
