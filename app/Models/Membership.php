<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $fillable = ['user_id', 'role', 'display_name', 'left_at'];

    protected function casts(): array
    {
        return ['role' => MembershipRole::class, 'left_at' => 'immutable_datetime'];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return $this->left_at ? $this->display_name.' · Removido #'.$this->id : $this->user->name.' #'.$this->id;
    }
}
