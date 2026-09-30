<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => strtolower(trim($value)));
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function houses(): BelongsToMany
    {
        return $this->belongsToMany(House::class, 'memberships')->wherePivotNull('left_at');
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->houses()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof House && $tenant->isMember($this) && $this->hasVerifiedEmail();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'app';
    }
}
