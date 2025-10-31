<?php

namespace App\Services\Base;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    /**
     * Create and send an invitation.
     */
    public function createInvitation(
        Organization $organization,
        User $invitedBy,
        string $email,
        string $role = 'member',
        array $metadata = []
    ): Invitation {
        // Check if user is already a member of the organization
        if (User::where('email', $email)->where('organization_id', $organization->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of your organization.'
            ]);
        }

        // Check if there's already any invitation for this email
        $existingInvitation = Invitation::where('organization_id', $organization->id)
            ->where('email', $email)
            ->first();

        if ($existingInvitation) {
            if ($existingInvitation->isValid()) {
                throw ValidationException::withMessages([
                    'email' => 'There is already a pending invitation for this email address.'
                ]);
            } else {
                // If invitation exists but is expired or accepted, delete it to allow new invitation
                $existingInvitation->delete();
            }
        }

        // Validate role
        $validRoles = ['admin', 'member'];
        if (!in_array($role, $validRoles)) {
            throw ValidationException::withMessages([
                'role' => 'Invalid role specified.'
            ]);
        }


        // Create the invitation
        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $invitedBy->id,
            'email' => $email,
            'role' => $role,
            'metadata' => $metadata,
        ]);

        // Send the invitation email
        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Send invitation email.
     */
    public function sendInvitationEmail(Invitation $invitation): void
    {
        // Send notification directly to the email address
        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitation($invitation));
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(Invitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has already been accepted.'
            ]);
        }

        if ($invitation->isExpired()) {
            // Extend the expiration date
            $invitation->update([
                'expires_at' => now()->addDays(7)
            ]);
        }

        $this->sendInvitationEmail($invitation);
    }

    /**
     * Accept an invitation and create/update user account.
     */
    public function acceptInvitation(string $token, array $userData = []): User
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation) {
            throw ValidationException::withMessages([
                'token' => 'Invalid invitation token.'
            ]);
        }

        if (!$invitation->isValid()) {
            throw ValidationException::withMessages([
                'invitation' => $invitation->isExpired() 
                    ? 'This invitation has expired.' 
                    : 'This invitation has already been accepted.'
            ]);
        }

        return DB::transaction(function () use ($invitation, $userData) {
            // Check if user already exists
            $user = User::where('email', $invitation->email)->first();

            if ($user) {
                // User exists, update their organization and role
                if ($user->organization_id && $user->organization_id !== $invitation->organization_id) {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already associated with another organization.'
                    ]);
                }

                $user->update([
                    'organization_id' => $invitation->organization_id,
                    'email_verified_at' => $user->email_verified_at ?? now(), // Auto-verify email for invited users if not already verified
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $userData['name'] ?? explode('@', $invitation->email)[0],
                    'email' => $invitation->email,
                    'password' => isset($userData['password']) ? bcrypt($userData['password']) : null,
                    'organization_id' => $invitation->organization_id,
                    'email_verified_at' => now(), // Auto-verify email for invited users
                ]);
            }

            // Assign role to user
            $user->assignRole($invitation->role);

            // TODO: Implement onboarding system
            // $user->markOnboardingCompleted();

            // Mark invitation as accepted
            $invitation->markAsAccepted($user);

            return $user;
        });
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(Invitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => 'Cannot cancel an invitation that has already been accepted.'
            ]);
        }

        $invitation->delete();
    }

    /**
     * Get pending invitations for an organization.
     */
    public function getPendingInvitations(Organization $organization)
    {
        return Invitation::where('organization_id', $organization->id)
            ->valid()
            ->with(['invitedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Clean up expired invitations.
     */
    public function cleanupExpiredInvitations(): int
    {
        return Invitation::expired()->delete();
    }
} 