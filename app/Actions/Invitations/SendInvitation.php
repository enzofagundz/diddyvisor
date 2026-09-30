<?php

namespace App\Actions\Invitations;

use App\Models\House;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\HouseInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendInvitation
{
    public function __invoke(User $actor, House $house, string $email): Invitation
    {
        $email = strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->validate();
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($actor, $house, $email, $token) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            if ($house->memberships()->whereNull('left_at')->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => 'Este usuário já participa da casa.']);
            }
            $house->invitations()->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $house->invitations()->create(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by_user_id' => $actor->id]);
        });
        try {
            Notification::route('mail', $email)->notify(new HouseInvitation($house->name, url('/convites/'.$invitation->id).'#token='.$token));
        } catch (Throwable $exception) {
            DB::transaction(function () use ($house, $invitation) {
                House::query()->lockForUpdate()->findOrFail($house->id);
                $invitation->update(['revoked_at' => now()]);
            });
            throw ValidationException::withMessages(['email' => 'Não foi possível enviar o convite. Tente novamente.']);
        }

        return $invitation;
    }
}
