<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user with role selection and company registration.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            
            // Company information
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string|max:500',
            'company_city' => 'nullable|string|max:100',
            'company_state' => 'nullable|string|max:100',
            'company_country' => 'nullable|string|max:100',
            'company_postal_code' => 'nullable|string|max:20',
            'company_website' => 'nullable|url|max:255',
            'company_registration_number' => 'nullable|string|max:100',
            'company_tax_id' => 'nullable|string|max:100',
            'company_description' => 'nullable|string|max:1000',
            
            // Role selection
            'role' => 'required|in:buyer,supplier',
            
            // Terms agreement
            'agree_to_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create or find company
            $company = Company::firstOrCreate(
                ['email' => $request->company_email],
                [
                    'name' => $request->company_name,
                    'phone' => $request->company_phone,
                    'address' => $request->company_address,
                    'city' => $request->company_city,
                    'state' => $request->company_state,
                    'country' => $request->company_country,
                    'postal_code' => $request->company_postal_code,
                    'website' => $request->company_website,
                    'registration_number' => $request->company_registration_number,
                    'tax_id' => $request->company_tax_id,
                    'description' => $request->company_description,
                    'type' => $request->role,
                    'status' => 'active',
                ]
            );

            // All users are active immediately after email verification
            $userStatus = 'active';

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'position' => $request->position,
                'department' => $request->department,
                'role' => $request->role,
                'status' => $userStatus,
                'email_verified_at' => null, // Will be verified via email
            ]);

            // Assign role
            $user->assignRole($request->role);

            // Attach user to company and set as primary
            $user->companies()->attach($company->id, ['is_primary' => true, 'role' => 'owner']);

            // Generate email verification token
            $verificationToken = Str::random(64);
            $user->update(['email_verification_token' => $verificationToken]);

            // Send email verification
            $this->sendVerificationEmail($user, $verificationToken);

            // No admin approval needed - users are active immediately

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'data' => [
                    'user' => $user->load('roles', 'companies'),
                    'requires_approval' => false,
                    'email_verification_required' => true,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
            ], 422);
        }

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token',
            ], 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
        ]);
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request)
    {
        Log::info('Resend verification request received', ['email' => $request->email]);
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            Log::warning('Resend verification validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid email',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::warning('Resend verification: User not found', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->email_verified_at) {
            Log::info('Resend verification: Email already verified', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'Email already verified',
            ], 400);
        }

        // Generate new verification token
        $verificationToken = Str::random(64);
        $user->update(['email_verification_token' => $verificationToken]);

        Log::info('Sending resend verification email', ['email' => $request->email, 'user_id' => $user->id]);

        // Send verification email
        $this->sendVerificationEmail($user, $verificationToken);

        Log::info('Resend verification email sent successfully', ['email' => $request->email]);

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully!',
        ]);
    }

    /**
     * Check registration status.
     */
    public function checkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        return response()->json([
            'success' => true, // API call successful
            'email_exists' => (bool)$user, // True if email exists, false if available
            'message' => $user ? 'Email is already registered.' : 'Email is available for registration.',
            'data' => $user ? [
                'email_verified' => !is_null($user->email_verified_at),
                'status' => $user->status,
                'role' => $user->role,
                'requires_approval' => false,
            ] : null
        ]);
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        // Check if email is verified
        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
                'email_verification_required' => true,
            ], 401);
        }
        
        // Check if account is active (all users are active after email verification)
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact support.',
                'status' => $user->status,
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles', 'companies'),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request)
    {
        // Debug: Log the request details
        Log::info('Profile request received', [
            'user_id' => $request->user() ? $request->user()->id : 'null',
            'auth_header' => $request->header('Authorization'),
            'token' => $request->bearerToken(),
            'ip' => $request->ip()
        ]);
        
        $user = $request->user()->load('roles', 'companies');

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Generate reset token
            $token = Str::random(64);
            
            // Store token in database
            DB::table('password_resets')->updateOrInsert(
                ['email' => $request->email],
                [
                    'email' => $request->email,
                    'token' => $token,
                    'created_at' => now()
                ]
            );

            // Send email
            Mail::to($request->email)->send(new \App\Mail\PasswordResetMail($token, $user->name));

            return response()->json([
                'success' => true,
                'message' => 'Password reset link has been sent to your email address'
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset email. Please try again.'
            ], 500);
        }
    }

    /**
     * Reset password.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find password reset record
            $passwordReset = DB::table('password_resets')
                ->where('token', $request->token)
                ->where('created_at', '>', now()->subHours(1)) // Token expires in 1 hour
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Find user
            $user = User::where('email', $passwordReset->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Delete password reset record
            DB::table('password_resets')->where('email', $passwordReset->email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Update user profile.
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

        $user->update($request->only(['name', 'phone', 'position', 'department']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->load('roles', 'companies')
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }


    /**
     * Send verification email.
     */
    private function sendVerificationEmail($user, $token)
    {
        try {
            $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;
            
            Log::info("Sending verification email to {$user->email}", [
                'user_id' => $user->id,
                'verification_url' => $verificationUrl
            ]);
            
            // Send verification email
            Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail(
                $user->name, 
                $verificationUrl, 
                null // null for signup verification
            ));
            
            Log::info("Verification email sent successfully to {$user->email}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send verification email to {$user->email}: " . $e->getMessage());
            // Don't throw exception to avoid breaking the registration flow
        }
    }

    /**
     * Send admin approval notification.
     */
    private function sendAdminApprovalNotification($user, $company)
    {
        // For now, we'll just log the notification. In production, you'd send to admin
        Log::info("Admin approval required for supplier: {$user->email} from company: {$company->name}");
        
        // TODO: Implement actual admin notification
    }

    /**
     * Send password reset email.
     */
    private function sendPasswordResetEmail($user, $token)
    {
        // For now, we'll just log the email. In production, you'd use a proper email service
        Log::info("Password reset email sent to {$user->email} with token: {$token}");
        
        // TODO: Implement actual email sending
        // Mail::to($user->email)->send(new PasswordResetMail($user, $token));
    }
}
