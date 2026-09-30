<?php

namespace App\Policies;

use App\Models\House;
use App\Models\User;

class HousePolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, House $house): bool
    {
        return $user->canAccessTenant($house);
    }

    public function update(User $user, House $house): bool
    {
        return $user->hasVerifiedEmail() && $house->isAdmin($user);
    }

    public function delete(User $user, House $house): bool
    {
        return $this->update($user, $house);
    }
}
