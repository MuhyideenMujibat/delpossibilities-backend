<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    public function __construct(public string $otp)
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
        return (new MailMessage)
            ->subject("D'EL-POSSIBILITIES - Reset Your Password")
            ->greeting("D'EL-POSSIBILITIES")
            ->line('We received a request to reset your password. Use this code to continue:')
            ->line("**{$this->otp}**")
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation("Regards,\nD'EL-Possibilities");
    }
}
