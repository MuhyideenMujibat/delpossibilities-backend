<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('services.frontend.url'), '/').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject("D'EL-POSSIBILITIES - Reset Your Password")
            ->greeting("D'EL-POSSIBILITIES")
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation("Regards,\nD'EL-POSSIBILITIES");
    }
}
