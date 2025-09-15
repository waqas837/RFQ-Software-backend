<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    /**
     * Get all companies with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $query = Company::query();
            
            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%")
                      ->orWhere('state', 'like', "%{$search}%");
                });
            }
            
            $companies = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
            
            // Add user count for each company
            $companies->getCollection()->transform(function ($company) {
                $company->users_count = $company->users()->count();
                return $company;
            });
            
            return response()->json([
                'success' => true,
                'data' => $companies,
                'message' => 'Companies retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving companies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific company.
     */
    public function show($id)
    {
        try {
            $company = Company::with(['users'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $company,
                'message' => 'Company retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Company not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create a new company.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|in:buyer,supplier,both',
                'email' => 'required|email|unique:companies,email',
                'phone' => 'nullable|string|max:20',
                'website' => 'nullable|url',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
                'registration_number' => 'nullable|string|max:50',
                'status' => 'required|in:active,inactive,pending_approval',
                'industry' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $company = Company::create($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $company,
                'message' => 'Company created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a company.
     */
    public function update(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:buyer,supplier,both',
                'email' => 'sometimes|required|email|unique:companies,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'website' => 'nullable|url',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
                'registration_number' => 'nullable|string|max:50',
                'status' => 'sometimes|required|in:active,inactive,pending_approval',
                'industry' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $company->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $company,
                'message' => 'Company updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a company.
     */
    public function destroy($id)
    {
        try {
            $company = Company::findOrFail($id);
            
            // Check if company has associated data
            if ($company->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with associated users'
                ], 400);
            }
            
            if ($company->rfqs()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with associated RFQs'
                ], 400);
            }
            
            $company->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting company: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update company status.
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,inactive,pending_approval'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $company->update(['status' => $request->status]);
            
            return response()->json([
                'success' => true,
                'data' => $company,
                'message' => 'Company status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating company status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
