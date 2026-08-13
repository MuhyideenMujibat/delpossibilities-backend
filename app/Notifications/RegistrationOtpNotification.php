<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationOtpNotification extends Notification
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
            ->subject("D'EL-POSSIBILITIES - Verify Your Email")
            ->greeting("D'EL-POSSIBILITIES")
            ->line('Use this code to verify your email and finish creating your account:')
            ->line("**{$this->otp}**")
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this, no further action is required.')
            ->salutation("Regards,\nD'EL-Possibilities");
    }
}
