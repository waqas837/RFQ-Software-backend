<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvitation;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierRegistrationController extends Controller
{
    /**
     * Register a supplier from an invitation token.
     */
    public function registerFromInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:supplier_invitations,token',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
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
            // Find and validate invitation
            $invitation = SupplierInvitation::where('token', $request->token)->first();
            
            if (!$invitation->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired invitation token'
                ], 400);
            }

            // Check if email matches invitation
            if ($invitation->email !== $request->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email does not match the invitation'
                ], 400);
            }

            // Check if user already exists
            if (User::where('email', $request->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User with this email already exists'
                ], 400);
            }

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
                    'type' => 'supplier',
                    'status' => 'active', // Auto-approve invited suppliers
                ]
            );

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'position' => $request->position,
                'department' => $request->department,
                'is_active' => true, // Auto-approve invited suppliers
                'email_verified_at' => now(), // Auto-verify for invited suppliers
            ]);

            // Assign role
            $user->assignRole('supplier');

            // Attach user to company
            $user->companies()->attach($company->id);

            // Mark invitation as registered
            $invitation->markAsRegistered($user->id);

            // Add supplier to RFQ
            $invitation->rfq->suppliers()->attach($company->id);

            // Generate token for auto-login
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Debug: Log token creation
            Log::info('Token created for user', [
                'user_id' => $user->id,
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 10) . '...'
            ]);

            // Send welcome email
            $this->sendWelcomeEmail($user, $invitation->rfq);

            Log::info("Supplier registered from invitation", [
                'user_id' => $user->id,
                'email' => $user->email,
                'rfq_id' => $invitation->rfq_id,
                'invitation_id' => $invitation->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! You can now submit your bid.',
                'data' => [
                    'user' => $user->load('roles', 'companies'),
                    'token' => $token,
                    'rfq' => $invitation->rfq,
                    'rfq_url' => config('app.frontend_url') . '/rfqs/' . $invitation->rfq_id,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Supplier registration failed", [
                'error' => $e->getMessage(),
                'token' => $request->token,
                'email' => $request->email
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate invitation token.
     */
    public function validateInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|exists:supplier_invitations,token',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invitation token'
            ], 400);
        }

        try {
            $invitation = SupplierInvitation::where('token', $request->token)
                ->with(['rfq', 'inviter'])
                ->first();

            if (!$invitation->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has expired or is no longer valid'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'invitation' => $invitation,
                    'rfq' => $invitation->rfq,
                    'inviter' => $invitation->inviter,
                    'company_name' => $invitation->company_name,
                    'contact_name' => $invitation->contact_name,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user exists by email.
     */
    public function checkUserExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required'
            ], 422);
        }

        try {
            $userExists = User::where('email', $request->email)->exists();

            return response()->json([
                'success' => true,
                'exists' => $userExists,
                'email' => $request->email
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check user existence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send welcome email to newly registered supplier.
     */
    private function sendWelcomeEmail($user, $rfq)
    {
        try {
            $data = [
                'user_name' => $user->name,
                'company_name' => $user->companies->first()->name,
                'rfq_title' => $rfq->title,
                'rfq_reference' => $rfq->reference_number,
                'bid_deadline' => $rfq->bid_deadline->format('F j, Y \a\t g:i A'),
                'login_url' => config('app.frontend_url') . '/login',
                'rfq_url' => config('app.frontend_url') . '/rfqs/' . $rfq->id,
            ];

            Mail::send('emails.supplier-welcome', $data, function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Welcome to RFQ System - Registration Successful');
            });

        } catch (\Exception $e) {
            Log::error("Failed to send welcome email", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}