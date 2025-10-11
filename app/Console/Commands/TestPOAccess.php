<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use App\Models\User;

class TestPOAccess extends Command
{
    protected $signature = 'test:po-access';
    protected $description = 'Test PO access for supplier users';

    public function handle()
    {
        $this->info('=== Testing PO Access ===');
        
        // Get the supplier user (ID 5 - waqassupplier)
        $supplierUser = User::find(5);
        if (!$supplierUser) {
            $this->error('Supplier user not found');
            return;
        }
        
        $this->info("Testing for user: {$supplierUser->name} (ID: {$supplierUser->id}, Role: {$supplierUser->role})");
        
        // Check user's companies
        $companies = $supplierUser->companies;
        $this->info("User companies: " . $companies->count());
        foreach ($companies as $company) {
            $this->info("  - Company: {$company->name} (ID: {$company->id})");
        }
        
        // Test PO filtering logic
        $this->info("\n=== Testing PO Filtering ===");
        
        // Simulate the controller logic
        $query = PurchaseOrder::query();
        
        if ($supplierUser->role === 'supplier') {
            $supplierCompany = $supplierUser->companies->first();
            if ($supplierCompany) {
                $this->info("Filtering by supplier_company_id: {$supplierCompany->id}");
                $query->where('supplier_company_id', $supplierCompany->id);
            } else {
                $this->error("Supplier has no company association");
            }
        }
        
        $pos = $query->get();
        $this->info("POs found: " . $pos->count());
        
        foreach ($pos as $po) {
            $this->info("  - PO: {$po->po_number} (Supplier Company ID: {$po->supplier_company_id})");
        }
        
        // Check if there are any POs with supplier_company_id = 5
        $this->info("\n=== Direct Database Check ===");
        $posWithCompany5 = PurchaseOrder::where('supplier_company_id', 5)->get();
        $this->info("POs with supplier_company_id = 5: " . $posWithCompany5->count());
        
        foreach ($posWithCompany5 as $po) {
            $this->info("  - PO: {$po->po_number} (Status: {$po->status})");
        }
    }
}
