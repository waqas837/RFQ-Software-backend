<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $admin = Role::create(['name' => 'admin']);
        $buyer = Role::create(['name' => 'buyer']);
        $supplier = Role::create(['name' => 'supplier']);

        // Create permissions
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Item management
            'view items',
            'create items',
            'edit items',
            'delete items',
            
            // RFQ management
            'view rfqs',
            'create rfqs',
            'edit rfqs',
            'delete rfqs',
            'publish rfqs',
            'close rfqs',
            
            // Supplier management
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',
            'approve suppliers',
            
            // Bid management
            'view bids',
            'create bids',
            'edit bids',
            'delete bids',
            'submit bids',
            'award bids',
            
            // Reports
            'view reports',
            'export reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        // Admin: System management, user management, item catalog
        $admin->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view items',
            'create items',
            'edit items',
            'delete items',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',
            'approve suppliers',
            'view rfqs',
            'view bids',
            'view reports',
            'export reports',
        ]);
        
        // Buyer: RFQ creation, bid evaluation, PO management
        $buyer->givePermissionTo([
            'view items',
            'view rfqs',
            'create rfqs',
            'edit rfqs',
            'delete rfqs',
            'publish rfqs',
            'close rfqs',
            'view suppliers',
            'view bids',
            'award bids',
            'view reports',
            'export reports',
        ]);
        
        // Supplier: Bid submission, negotiation, order fulfillment
        $supplier->givePermissionTo([
            'view items',
            'view rfqs',
            'view bids',
            'create bids',
            'edit bids',
            'delete bids',
            'submit bids',
        ]);
    }
}
