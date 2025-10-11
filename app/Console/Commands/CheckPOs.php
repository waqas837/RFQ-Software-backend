<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrder;
use App\Models\User;

class CheckPOs extends Command
{
    protected $signature = 'check:pos';
    protected $description = 'Check PO details and company associations';

    public function handle()
    {
        $this->info('=== Purchase Orders Check ===');
        
        $pos = PurchaseOrder::with(['supplierCompany', 'buyerCompany'])->get();
        
        $this->info('Total POs: ' . $pos->count());
        
        foreach ($pos as $po) {
            $this->info("PO: {$po->po_number}");
            $this->info("  - Supplier Company ID: {$po->supplier_company_id}");
            $this->info("  - Buyer Company ID: {$po->buyer_company_id}");
            $this->info("  - Status: {$po->status}");
            $this->info("  - Supplier Company: " . ($po->supplierCompany ? $po->supplierCompany->name : 'NULL'));
            $this->info("  - Buyer Company: " . ($po->buyerCompany ? $po->buyerCompany->name : 'NULL'));
            $this->info('---');
        }
        
        $this->info('=== Users and Companies ===');
        $users = User::with('companies')->get();
        foreach ($users as $user) {
            $this->info("User: {$user->name} (ID: {$user->id}, Role: {$user->role})");
            foreach ($user->companies as $company) {
                $this->info("  - Company: {$company->name} (ID: {$company->id})");
            }
        }
    }
}