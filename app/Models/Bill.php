<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = ['name', 'competence', 'due_date', 'total_cents', 'status'];

    protected function casts(): array
    {
        return ['competence' => 'immutable_date', 'due_date' => 'immutable_date', 'total_cents' => 'integer', 'status' => BillStatus::class];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(BillShare::class);
    }

    public function recalculateStatus(): void
    {
        $positive = $this->shares()->where('amount_cents', '>', 0)->get();
        $paid = $positive->where('is_paid', true)->count();
        $this->status = $paid === 0 ? BillStatus::Pending : ($paid === $positive->count() ? BillStatus::Paid : BillStatus::Partial);
        $this->save();
    }

    public function copyFingerprint(): string
    {
        return hash('sha256', json_encode([$this->name, $this->due_date->toDateString(), $this->total_cents, $this->shares->sortBy('membership_id')->map(fn ($share) => [$share->membership_id, $share->amount_cents])->values()->all()], JSON_THROW_ON_ERROR));
    }

    public function isOverdue(): bool
    {
        return $this->status !== BillStatus::Paid && $this->due_date->toDateString() < now('America/Sao_Paulo')->toDateString();
    }
}
