<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    protected $fillable = ['name'];

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function isMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->whereNull('left_at')->exists();
    }

    public function isAdmin(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->whereNull('left_at')->where('role', MembershipRole::Admin)->exists();
    }
}
