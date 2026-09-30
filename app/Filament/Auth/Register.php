<?php

namespace App\Filament\Auth;

use App\Models\Invitation;
use Filament\Schemas\Components\Component;

class Register extends \Filament\Auth\Pages\Register
{
    public function mount(): void
    {
        parent::mount();

        $pending = session('pending_invitation');
        if (! is_array($pending)) {
            return;
        }

        $invitation = Invitation::query()->find($pending['id'] ?? null);
        if ($invitation?->isOpen() && is_string($pending['hash'] ?? null) && hash_equals($invitation->token_hash, $pending['hash'])) {
            $this->form->fill([...$this->data, 'email' => $invitation->email]);
        }
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->mutateStateForValidationUsing(fn (?string $state) => strtolower(trim($state ?? '')))
            ->dehydrateStateUsing(fn (?string $state) => strtolower(trim($state ?? '')));
    }
}
