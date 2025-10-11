<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use App\Mail\EmailVerificationMail;

class UserController extends Controller
{
    /**
     * Get all users with pagination.
     */
    public function index(Request $request)
    {
        // Debug: Log the request details
        Log::info('Users API request', [
            'user_id' => $request->user() ? $request->user()->id : 'null',
            'user_roles' => $request->user() ? $request->user()->roles->pluck('name') : 'null',
            'auth_header' => $request->header('Authorization'),
            'ip' => $request->ip()
        ]);

        // Debug: Check total users first
        $totalUsers = User::count();
        Log::info('Total users in database', ['count' => $totalUsers]);

        // Debug: Check request parameters
        Log::info('Request parameters', [
            'search' => $request->search,
            'role' => $request->role,
            'per_page' => $request->per_page
        ]);

        $users = User::with(['roles', 'companies'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->role, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->paginate($request->per_page ?? 15);

        Log::info('Users found', ['count' => $users->count(), 'total' => $users->total()]);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get a specific user.
     */
    public function show($id)
    {
        $user = User::with(['roles', 'companies'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'position' => 'nullable|string|max:100',
            'role' => 'required|exists:roles,name',
            'company_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create or find company
        $company = Company::firstOrCreate(
            ['name' => $request->company_name],
            [
                'name' => $request->company_name,
                'type' => $request->role === 'supplier' ? 'supplier' : 'buyer',
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]
        );

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'position' => $request->position,
            'is_active' => true,
        ]);

        $user->assignRole($request->role);
        $user->companies()->attach($company->id);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load('roles', 'companies')
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, $id)
    {
        Log::info('UserController - Update request received', [
            'user_id' => $id,
            'request_data' => $request->all()
        ]);

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'role' => 'sometimes|exists:roles,name',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->except(['role', 'password']);
        
        // Handle is_active field properly
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }
        
        Log::info('UserController - Update data being applied', [
            'user_id' => $user->id,
            'update_data' => $updateData
        ]);
        
        $user->update($updateData);
        
        Log::info('UserController - User updated successfully', [
            'user_id' => $user->id,
            'updated_user' => $user->fresh()->toArray()
        ]);

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load('roles', 'companies')
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get all roles.
     */
    public function roles()
    {
        $roles = Role::all();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get all companies.
     */
    public function companies()
    {
        $companies = Company::where('status', 'active')->get();

        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }

    /**
     * Get users for invitations (accessible by buyers and admins)
     */
    public function getUsersForInvitations(Request $request)
    {
        try {
            // Check if user has permission (buyers and admins)
            $user = $request->user();
            if (!$user->roles->pluck('name')->intersect(['buyer', 'admin'])->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view users for invitations'
                ], 403);
            }

            // Get users with supplier and buyer roles (exclude current user)
            $users = User::where('id', '!=', $user->id)
                ->whereIn('role', ['supplier', 'buyer'])
                ->where('status', 'active')
                ->select('id', 'name', 'email', 'role', 'position')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Users retrieved successfully for invitations'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users for invitations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's own profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load(['roles', 'companies']);
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Get other users' profiles based on role permissions.
     */
    public function getOtherUsers(Request $request)
    {
        $currentUser = $request->user();
        $userRole = $currentUser->role;
        
        $query = User::with(['roles', 'companies'])
            ->where('id', '!=', $currentUser->id)
            ->where('status', 'active');

        // Role-based filtering
        if ($userRole === 'admin') {
            // Admin can see all users
            $users = $query->get();
        } elseif ($userRole === 'buyer') {
            // Buyers can see other buyers
            $users = $query->where('role', 'buyer')->get();
        } elseif ($userRole === 'supplier') {
            // Suppliers can see other suppliers
            $users = $query->where('role', 'supplier')->get();
        } else {
            $users = collect();
        }

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get a specific user's profile by ID.
     */
    public function getUserProfile(Request $request, $id)
    {
        $currentUser = $request->user();
        $userRole = $currentUser->role;
        
        $user = User::with(['roles', 'companies'])->find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if current user can view this profile
        $canView = false;
        
        if ($user->id === $currentUser->id) {
            // Users can always view their own profile
            $canView = true;
        } elseif ($userRole === 'admin') {
            // Admin can view all profiles
            $canView = true;
        } elseif ($userRole === 'buyer' && $user->role === 'buyer') {
            // Buyers can view other buyers
            $canView = true;
        } elseif ($userRole === 'supplier' && $user->role === 'supplier') {
            // Suppliers can view other suppliers
            $canView = true;
        }

        if (!$canView) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this profile'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update user's own profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['name', 'phone', 'position', 'department']);
            
            // Remove empty values
            $updateData = array_filter($updateData, function($value) {
                return $value !== null && $value !== '';
            });

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()->load(['roles', 'companies'])
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Request email update with verification.
     */
    public function requestEmailUpdate(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'new_email' => 'required|email|unique:users,email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $newEmail = $request->new_email;
            
            Log::info('Email update request', [
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'current_email' => $user->email
            ]);
            
            // Check if user already has a pending verification for this email
            $existingVerification = EmailVerification::where('user_id', $user->id)
                ->where('new_email', $newEmail)
                ->where('is_verified', false)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingVerification) {
                Log::info('Existing verification found', [
                    'verification_id' => $existingVerification->id,
                    'expires_at' => $existingVerification->expires_at
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'A verification email has already been sent to this address. Please check your inbox or wait before requesting another.'
                ], 400);
            }

            // Delete any old pending verifications for this user
            $deletedCount = EmailVerification::where('user_id', $user->id)
                ->where('is_verified', false)
                ->delete();
            
            Log::info('Deleted old verifications', ['count' => $deletedCount]);

            // Create new verification record
            $token = Str::random(64);
            $verification = EmailVerification::create([
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'token' => $token,
                'expires_at' => now()->addHours(24),
                'is_verified' => false
            ]);

            Log::info('Verification record created', [
                'verification_id' => $verification->id,
                'token' => substr($token, 0, 10) . '...',
                'expires_at' => $verification->expires_at
            ]);

            // Send verification email
            $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;
            Log::info('Sending verification email', [
                'to' => $newEmail,
                'url' => $verificationUrl
            ]);
            
            Mail::to($newEmail)->send(new EmailVerificationMail($user->name, $verificationUrl, $newEmail));

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent to your new email address. Please check your inbox and click the verification link.'
            ]);

        } catch (\Exception $e) {
            Log::error('Email update request error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email'
            ], 500);
        }
    }

    /**
     * Verify email update token.
     */
    public function verifyEmailUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token'
            ], 422);
        }

        try {
            Log::info('Email verification attempt', [
                'token' => $request->token,
                'token_length' => strlen($request->token),
                'current_time' => now(),
            ]);

            $verification = EmailVerification::where('token', $request->token)
                ->where('expires_at', '>', now())
                ->first();

            Log::info('Verification query result', [
                'verification_found' => !!$verification,
                'verification_id' => $verification ? $verification->id : null,
                'expires_at' => $verification ? $verification->expires_at : null,
                'is_verified' => $verification ? $verification->is_verified : null,
            ]);

            if (!$verification) {
                // Check if token exists at all
                $anyVerification = EmailVerification::where('token', $request->token)->first();
                Log::info('Any verification with this token', [
                    'exists' => !!$anyVerification,
                    'expires_at' => $anyVerification ? $anyVerification->expires_at : null,
                    'is_verified' => $anyVerification ? $anyVerification->is_verified : null,
                    'is_expired' => $anyVerification ? $anyVerification->expires_at < now() : null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification token'
                ], 400);
            }

            // Check if already verified
            if ($verification->is_verified) {
                Log::info('Token already verified', [
                    'verification_id' => $verification->id,
                    'user_id' => $verification->user_id,
                    'new_email' => $verification->new_email
                ]);

                // Check if user's email is already updated
                $user = User::find($verification->user_id);
                if ($user && $user->email === $verification->new_email) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Email address has already been verified and updated successfully',
                        'data' => $user->fresh()->load(['roles', 'companies'])
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'This verification link has already been used. Please request a new verification email if you need to change your email again.'
                    ], 400);
                }
            }

            // Update user's email
            $user = User::find($verification->user_id);
            $user->email = $verification->new_email;
            $user->save();

            // Mark verification as completed
            $verification->is_verified = true;
            $verification->save();

            // Delete all pending verifications for this user
            EmailVerification::where('user_id', $user->id)
                ->where('is_verified', false)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email address updated successfully',
                'data' => $user->fresh()->load(['roles', 'companies'])
            ]);

        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email address'
            ], 500);
        }
    }
}
