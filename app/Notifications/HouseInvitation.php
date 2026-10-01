<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HouseInvitation extends Notification
{
    public function __construct(public string $houseName, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite para '.$this->houseName.' · DiddyVisor')
            ->markdown('mail.house-invitation', ['houseName' => $this->houseName, 'url' => $this->url]);
    }
}
