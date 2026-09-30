<?php

namespace App\Actions\Invitations;

use App\Models\House;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeInvitation
{
    public function __invoke(User $actor, House $house, int $invitationId): void
    {
        DB::transaction(function () use ($actor, $house, $invitationId) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            $invitation = $house->invitations()->lockForUpdate()->findOrFail($invitationId);
            abort_if($invitation->accepted_at !== null, 409);
            $invitation->update(['revoked_at' => now()]);
        });
    }
}
