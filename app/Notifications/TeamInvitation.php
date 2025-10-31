<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invitation $invitation
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = $this->invitation->organization->name;
        $inviterName = $this->invitation->invitedBy->name;
        $role = ucfirst($this->invitation->role);
        $acceptUrl = $this->invitation->getInvitationUrl();

        return (new MailMessage)
            ->subject("You're invited to join {$organizationName}")
            ->greeting("Hello!")
            ->line("{$inviterName} has invited you to join **{$organizationName}** as a **{$role}**.")
            ->line("Click the button below to accept your invitation and get started.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line('If you don\'t have an account yet, you\'ll be able to create one when you accept the invitation.')
            ->line('Thank you!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_name' => $this->invitation->organization->name,
            'inviter_name' => $this->invitation->invitedBy->name,
            'role' => $this->invitation->role,
            'expires_at' => $this->invitation->expires_at,
        ];
    }
} 