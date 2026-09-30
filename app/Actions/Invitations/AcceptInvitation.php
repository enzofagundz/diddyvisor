<?php

namespace App\Actions\Invitations;

use App\Models\House;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __invoke(User $actor, int $invitationId, string $hash): House
    {
        return DB::transaction(function () use ($actor, $invitationId, $hash) {
            $preview = Invitation::query()->findOrFail($invitationId);
            $house = House::query()->lockForUpdate()->findOrFail($preview->house_id);
            $invitation = $house->invitations()->lockForUpdate()->findOrFail($invitationId);
            abort_unless($actor->hasVerifiedEmail() && $actor->email === $invitation->email && $invitation->isOpen() && hash_equals($invitation->token_hash, $hash), 403);
            abort_if($house->isMember($actor), 403);
            $house->memberships()->create(['user_id' => $actor->id, 'display_name' => $actor->name]);
            $invitation->update(['accepted_at' => now()]);

            return $house;
        });
    }
}
