<?php

namespace App\Actions\Houses;

use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageMembership
{
    public function setRole(User $actor, House $house, int $membershipId, MembershipRole $role): void
    {
        DB::transaction(function () use ($actor, $house, $membershipId, $role) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            $member = $house->memberships()->whereNull('left_at')->findOrFail($membershipId);
            if ($role === MembershipRole::Member) {
                $this->protectLastAdmin($house, $member);
            }
            $member->update(['role' => $role]);
        });
    }

    public function remove(User $actor, House $house, int $membershipId): void
    {
        DB::transaction(function () use ($actor, $house, $membershipId) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            $member = $house->memberships()->whereNull('left_at')->findOrFail($membershipId);
            Gate::forUser($actor)->authorize($member->user_id === $actor->id ? 'view' : 'update', $house);
            $this->protectLastAdmin($house, $member);
            $member->update(['display_name' => $member->user->name, 'left_at' => now()]);
        });
    }

    private function protectLastAdmin(House $house, Membership $member): void
    {
        if ($member->role === MembershipRole::Admin && $house->memberships()->whereNull('left_at')->where('role', MembershipRole::Admin)->count() === 1) {
            throw ValidationException::withMessages(['membership' => 'Promova outro administrador antes de sair ou rebaixar o último administrador.']);
        }
    }
}
