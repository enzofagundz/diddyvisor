<?php

namespace App\Actions\Bills;

use App\Models\House;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetSharePayment
{
    public function __invoke(User $actor, House $house, int $billId, int $membershipId, bool $desired): bool
    {
        return DB::transaction(function () use ($actor, $house, $billId, $membershipId, $desired) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('view', $house);
            $bill = $house->bills()->lockForUpdate()->findOrFail($billId);
            $share = $bill->shares()->where('membership_id', $membershipId)->with('membership')->firstOrFail();
            abort_unless($share->amount_cents > 0 && ($house->isAdmin($actor) || ($share->membership->left_at === null && $share->membership->user_id === $actor->id)), 403);
            $share->update(['is_paid' => $desired]);
            $bill->recalculateStatus();

            return $desired;
        });
    }
}
