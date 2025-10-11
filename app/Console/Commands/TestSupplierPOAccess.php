<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use App\Models\User;

class TestSupplierPOAccess extends Command
{
    protected $signature = 'test:supplier-po-access {user_id}';
    protected $description = 'Test PO access for a specific supplier user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("=== Testing PO Access for User ID: {$userId} ===");
        
        // Get the user
        $user = User::with('companies')->find($userId);
        if (!$user) {
            $this->error("User not found");
            return;
        }
        
        $this->info("User: {$user->name} (Role: {$user->role})");
        
        // Check user's companies
        $companies = $user->companies;
        $this->info("Companies: " . $companies->count());
        foreach ($companies as $company) {
            $this->info("  - Company: {$company->name} (ID: {$company->id})");
        }
        
        // Test PO filtering logic
        $this->info("\n=== Testing PO Filtering ===");
        
        if ($user->role === 'supplier') {
            $supplierCompany = $user->companies->first();
            if ($supplierCompany) {
                $this->info("Filtering by supplier_company_id: {$supplierCompany->id}");
                
                $pos = PurchaseOrder::where('supplier_company_id', $supplierCompany->id)->get();
                $this->info("POs found: " . $pos->count());
                
                foreach ($pos as $po) {
                    $this->info("  - PO: {$po->po_number} (Status: {$po->status})");
                }
            } else {
                $this->error("Supplier has no company association");
            }
        } else {
            $this->info("User is not a supplier (role: {$user->role})");
        }
        
        // Check all POs in database
        $this->info("\n=== All POs in Database ===");
        $allPOs = PurchaseOrder::all();
        $this->info("Total POs: " . $allPOs->count());
        
        foreach ($allPOs as $po) {
            $this->info("  - PO: {$po->po_number} (Supplier Company ID: {$po->supplier_company_id}, Status: {$po->status})");
        }
    }
}
