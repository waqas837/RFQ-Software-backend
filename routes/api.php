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
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/profile/verify-email-update', [UserController::class, 'verifyEmailUpdate']);
Route::post('/check-status', [AuthController::class, 'checkStatus']);

// Public template downloads
Route::get('/rfqs/template/{type}', [RfqController::class, 'downloadTemplate']);

// Supplier registration routes (public)
Route::post('/supplier-register', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'registerFromInvitation']);
Route::get('/supplier-invitation/validate', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'validateInvitation']);
Route::post('/check-user-exists', [App\Http\Controllers\Api\SupplierRegistrationController::class, 'checkUserExists']);

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
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Get users for invitations (Buyers and Admins)
    Route::get('/users/for-invitations', [UserController::class, 'getUsersForInvitations']);
    
    // User profile routes (authenticated users)
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/request-email-update', [UserController::class, 'requestEmailUpdate']);
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
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
    Route::post('/purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm']);
    Route::post('/purchase-orders/{purchaseOrder}/confirm-delivery', [PurchaseOrderController::class, 'confirmDelivery']);
    
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
    Route::get('/reports/cost-savings', [ReportsController::class, 'costSavings']);
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
    
    // Currency management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/currencies/rates', [CurrencyController::class, 'getExchangeRates']);
        Route::post('/currencies/rates', [CurrencyController::class, 'updateExchangeRates']);
    });
});
