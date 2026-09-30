<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillShare extends Model
{
    protected $fillable = ['membership_id', 'amount_cents', 'is_paid'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'is_paid' => 'boolean'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
