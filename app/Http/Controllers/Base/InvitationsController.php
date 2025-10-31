<?php

namespace App\Http\Controllers\Base;

use App\Http\Requests\CreateInvitationRequest;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Base\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;

class InvitationsController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    /**
     * Display the team management page with invitations.
     */
    public function index(): Response
    {
        $organization = Auth::user()->organization;
        
        // Get team members
        $teamMembers = $organization->users()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->getRoleNames()->first() ?? 'member',
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'is_current_user' => $user->id === Auth::id(),
                ];
            });

        // Get pending invitations
        $pendingInvitations = $this->invitationService->getPendingInvitations($organization)
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'invited_by' => [
                        'name' => $invitation->invitedBy->name,
                        'email' => $invitation->invitedBy->email,
                    ],
                    'expires_at' => $invitation->expires_at,
                    'created_at' => $invitation->created_at,
                    'invitation_url' => $invitation->getInvitationUrl(),
                ];
            });

        return Inertia::render('users/index', [
            'teamMembers' => $teamMembers,
            'pendingInvitations' => $pendingInvitations,
            'canManageTeam' => Auth::user()->can('manage team'),
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(CreateInvitationRequest $request)
    {
        try {
            $invitation = $this->invitationService->createInvitation(
                Auth::user()->organization,
                Auth::user(),
                $request->validated('email'),
                $request->validated('role', 'member')
            );

            return back()->with('success', 'Invitation sent successfully!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Resend an invitation.
     */
    public function resend(Invitation $invitation)
    {
        // Ensure the invitation belongs to the current user's organization
        if ($invitation->organization_id !== Auth::user()->organization_id) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->invitationService->resendInvitation($invitation);
            return back()->with('success', 'Invitation resent successfully!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Cancel an invitation.
     */
    public function destroy(Invitation $invitation)
    {
        // Ensure the invitation belongs to the current user's organization
        if ($invitation->organization_id !== Auth::user()->organization_id) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->invitationService->cancelInvitation($invitation);
            return back()->with('success', 'Invitation cancelled successfully!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Show the invitation acceptance page.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation) {
            return Inertia::render('invitations/invalid', [
                'message' => 'Invalid invitation token.'
            ]);
        }

        if (!$invitation->isValid()) {
            return Inertia::render('invitations/invalid', [
                'message' => $invitation->isExpired() 
                    ? 'This invitation has expired.' 
                    : 'This invitation has already been accepted.'
            ]);
        }

        // Check if user is already logged in
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->email === $invitation->email) {
            // User is already logged in with the invited email
            try {
                $this->invitationService->acceptInvitation($token);
                return redirect()->route('dashboard')->with('success', 'Welcome to the team!');
            } catch (ValidationException $e) {
                return Inertia::render('invitations/invalid', [
                    'message' => $e->getMessage()
                ]);
            }
        }

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization' => [
                    'name' => $invitation->organization->name,
                ],
                'invited_by' => [
                    'name' => $invitation->invitedBy->name,
                ],
                'expires_at' => $invitation->expires_at,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function accept(AcceptInvitationRequest $request, string $token)
    {
        try {
            $user = $this->invitationService->acceptInvitation(
                $token,
                $request->validated()
            );

            // Log the user in
            Auth::login($user);

            return redirect()->route('dashboard')->with('success', 'Welcome to the team!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Update a user's details (name and role).
     */
    public function updateUser(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Ensure the user belongs to the same organization
        if ($user->organization_id !== $currentUser->organization_id) {
            abort(404, 'User not found in your organization.');
        }

        // Can't edit your own details through this endpoint
        if ($user->id === $currentUser->id) {
            return back()->withErrors(['user' => 'You cannot edit your own details through this interface.']);
        }

        // Prevent demoting the last admin
        if ($user->hasRole('admin') && $request->input('role') !== 'admin') {
            $adminCount = User::where('organization_id', $currentUser->organization_id)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })->count();
            
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Cannot demote the last admin. There must be at least one admin in the organization.']);
            }
        }

        // Validate the request
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => [
                'required',
                'string',
                Rule::in(['admin', 'member']),
            ],
        ]);

        try {
            // Update user name
            $user->update([
                'name' => $request->input('name'),
            ]);

            // Update user role
            $user->syncRoles([$request->input('role')]);

            return back()->with('success', 'User details updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['user' => 'Failed to update user details.']);
        }
    }

    /**
     * Remove a user from the organization.
     */
    public function removeUser(User $user)
    {
        $currentUser = Auth::user();

        // Ensure the user belongs to the same organization
        if ($user->organization_id !== $currentUser->organization_id) {
            abort(404, 'User not found in your organization.');
        }

        // Can't remove yourself
        if ($user->id === $currentUser->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself from the organization.']);
        }

        // Prevent removing the last admin
        if ($user->hasRole('admin')) {
            $adminCount = User::where('organization_id', $currentUser->organization_id)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })->count();
            
            if ($adminCount <= 1) {
                return back()->withErrors(['user' => 'Cannot remove the last admin. There must be at least one admin in the organization.']);
            }
        }

        try {
            // Remove user from organization (set organization_id to null)
            $user->update([
                'organization_id' => null,
            ]);

            // Remove all roles from the user
            $user->syncRoles([]);

            return back()->with('success', 'User removed from organization successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['user' => 'Failed to remove user from organization.']);
        }
    }
} 