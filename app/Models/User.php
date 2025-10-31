<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use App\Http\Services\LoopsService;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'google_id',
        'avatar',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if the user has completed onboarding.
     */
    public function hasCompletedOnboarding(): bool
    {
        // For now, we'll consider onboarding complete if user has an organization
        // This can be expanded later with additional onboarding steps
        return $this->organization_id !== null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            // Only add users to mailing list if they registered normally or via Google OAuth
            // Skip users created through invitations
            $shouldAddToMailingList = !$user->wasCreatedThroughInvitation();
            
            if (!$shouldAddToMailingList) {
                Log::info('Skipping mailing list addition for invitation-created user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }

            if(strpos($user->email, '@feedguardians.com') !== false) {
                Log::info('Skipping mailing list addition for feedguardians user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return;
            }
            
            // Add user to Loops contact list
            try {

                $loopsService = new LoopsService();
                
                // Determine registration source
                $source = 'Email Registration';
                if ($user->google_id) {
                    $source = 'Google OAuth';
                }

                $contactData = [
                    'email' => $user->email,
                    'firstName' => self::extractFirstName($user->name),
                    'lastName' => self::extractLastName($user->name),
                    'source' => $source,
                    'mailingLists' => [
                        "cmcatj7za03tb0iwk7zlm9kw0" => true,
                    ],
                    'userGroup' => 'Subscribers',
                    'signupDate' => $user->created_at->format('Y-m-d'),
                ];

                // Add organization name if available (load relationship if needed)
                if ($user->organization_id) {
                    $organization = $user->organization ?? Organization::find($user->organization_id);
                    if ($organization) {
                        $contactData['organizationName'] = $organization->name;
                    }
                }

                $result = $loopsService->createContact($contactData);

                if (isset($result['success']) && $result['success'] === false) {
                    Log::warning('Failed to create Loops contact for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                } else {
                    Log::info('Successfully created Loops contact for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'source' => $source
                    ]);
                }

            } catch (\Exception $e) {
                // Don't let Loops failures affect user registration
                Log::error('Exception while creating Loops contact', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Check if user was created through an invitation.
     */
    public function wasCreatedThroughInvitation(): bool
    {
        return \App\Models\Invitation::where('email', $this->email)
            ->whereNotNull('accepted_at')
            ->exists();
    }
}
