<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Get all suppliers with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $suppliers = Company::with(['users'])
                ->where('type', 'supplier')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($request->sort_by, function ($query) use ($request) {
                    $direction = $request->sort_direction === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($request->sort_by, $direction);
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $suppliers,
                'message' => 'Suppliers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific supplier.
     */
    public function show($id)
    {
        $supplier = Company::with(['users'])->where('type', 'supplier')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $supplier
        ]);
    }

    /**
     * Create a new supplier.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:companies,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|unique:users,email',
            'contact_phone' => 'nullable|string|max:20',
            'contact_position' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create supplier company
        $supplier = Company::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'website' => $request->website,
            'tax_id' => $request->tax_id,
            'registration_number' => $request->registration_number,
            'type' => 'supplier',
            'status' => 'pending_approval', // Requires approval
        ]);

        // Create contact user
        $user = User::create([
            'name' => $request->contact_name,
            'email' => $request->contact_email,
            'password' => bcrypt('temp_password_' . time()), // Temporary password
            'phone' => $request->contact_phone,
            'position' => $request->contact_position,
            'is_active' => false,
        ]);

        $user->assignRole('supplier');
        $user->companies()->attach($supplier->id);

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully. Pending approval.',
            'data' => $supplier->load('users')
        ], 201);
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, $id)
    {
        $supplier = Company::where('type', 'supplier')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:companies,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,pending_approval',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $supplier->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully',
            'data' => $supplier->load('users')
        ]);
    }

    /**
     * Delete a supplier.
     */
    public function destroy($id)
    {
        $supplier = Company::where('type', 'supplier')->findOrFail($id);
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    }

    /**
     * Approve a supplier.
     */
    public function approve($id)
    {
        $supplier = Company::where('type', 'supplier')->findOrFail($id);
        
        $supplier->update(['status' => 'active']);
        
        // Activate all users associated with this supplier
        $supplier->users()->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier approved successfully',
            'data' => $supplier->load('users')
        ]);
    }

    /**
     * Reject a supplier.
     */
    public function reject($id)
    {
        $supplier = Company::where('type', 'supplier')->findOrFail($id);
        
        $supplier->update(['status' => 'inactive']);
        
        // Deactivate all users associated with this supplier
        $supplier->users()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier rejected successfully',
            'data' => $supplier->load('users')
        ]);
    }
}
