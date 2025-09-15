<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin company
        $adminCompany = Company::create([
            'name' => 'RFQ System Admin',
            'email' => 'admin@rfqsystem.com',
            'phone' => '+1234567890',
            'address' => '123 Admin Street, Admin City',
            'type' => 'buyer',
            'status' => 'active',
        ]);

        // Create admin user
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@rfqsystem.com',
            'password' => Hash::make('password123'),
            'phone' => '+1234567890',
            'position' => 'System Administrator',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $admin->assignRole('admin');
        $admin->companies()->attach($adminCompany->id);

        // Create buyer company
        $buyerCompany = Company::create([
            'name' => 'ABC Manufacturing Co.',
            'email' => 'procurement@abcmfg.com',
            'phone' => '+1987654321',
            'address' => '456 Manufacturing Ave, Industry City',
            'type' => 'buyer',
            'status' => 'active',
        ]);

        // Create buyer user
        $buyer = User::create([
            'name' => 'John Buyer',
            'email' => 'buyer@abcmfg.com',
            'password' => Hash::make('password123'),
            'phone' => '+1987654321',
            'position' => 'Procurement Manager',
            'role' => 'buyer',
            'status' => 'active',
        ]);

        $buyer->assignRole('buyer');
        $buyer->companies()->attach($buyerCompany->id);

        // Create supplier companies
        $supplier1 = Company::create([
            'name' => 'XYZ Electronics Ltd.',
            'email' => 'sales@xyzelectronics.com',
            'phone' => '+1555123456',
            'address' => '789 Electronics Blvd, Tech City',
            'type' => 'supplier',
            'status' => 'active',
        ]);

        $supplier2 = Company::create([
            'name' => 'Quality Parts Inc.',
            'email' => 'info@qualityparts.com',
            'phone' => '+1555987654',
            'address' => '321 Parts Street, Parts City',
            'type' => 'supplier',
            'status' => 'active',
        ]);

        // Create supplier users
        $supplierUser1 = User::create([
            'name' => 'Sarah Supplier',
            'email' => 'supplier1@xyzelectronics.com',
            'password' => Hash::make('password123'),
            'phone' => '+1555123456',
            'position' => 'Sales Manager',
            'role' => 'supplier',
            'status' => 'active',
        ]);

        $supplierUser2 = User::create([
            'name' => 'Mike Vendor',
            'email' => 'supplier2@qualityparts.com',
            'password' => Hash::make('password123'),
            'phone' => '+1555987654',
            'position' => 'Business Development',
            'role' => 'supplier',
            'status' => 'active',
        ]);

        $supplierUser1->assignRole('supplier');
        $supplierUser1->companies()->attach($supplier1->id);

        $supplierUser2->assignRole('supplier');
        $supplierUser2->companies()->attach($supplier2->id);
    }
}
