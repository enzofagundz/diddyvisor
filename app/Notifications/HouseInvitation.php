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
        return (new MailMessage)->subject('Convite para '.$this->houseName.' · DiddyVisor')->greeting('Você recebeu um convite!')->line('Participe de '.$this->houseName.' para organizar as contas da casa.')->line('Este convite vale por sete dias e só pode ser aceito pelo e-mail convidado.')->action('Ver convite', $this->url)->salutation('DiddyVisor');
    }
}
