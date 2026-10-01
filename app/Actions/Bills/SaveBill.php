<?php

namespace App\Actions\Bills;

use App\Models\Bill;
use App\Models\House;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SaveBill
{
    public function __invoke(User $actor, House $house, string $month, array $input, ?int $billId = null): Bill
    {
        $input = Validator::make($input, [
            'name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'total' => ['required', 'string'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', 'distinct'],
            'shares' => ['present', 'array'],
            'shares.*.membership_id' => ['required', 'integer', 'distinct'],
            'shares.*.amount' => ['required', 'string'],
        ])->validate();
        $total = $this->parse($input['total'], 'total');
        if ($total === 0) {
            throw ValidationException::withMessages(['total' => 'O total deve ser maior que zero.']);
        }
        $ids = collect($input['participants'])->map(fn ($id) => (int) $id)->sort()->values();
        $amounts = [];
        if ($input['shares'] === []) {
            foreach ($ids as $index => $id) {
                $amounts[$id] = intdiv($total, $ids->count()) + ($index < $total % $ids->count() ? 1 : 0);
            }
        } else {
            foreach ($input['shares'] as $share) {
                $amounts[(int) $share['membership_id']] = $this->parse($share['amount'], 'shares');
            }
        }
        if (collect(array_keys($amounts))->sort()->values()->all() !== $ids->all()) {
            throw ValidationException::withMessages(['shares' => 'Informe uma parte para cada participante.']);
        }
        $remaining = $total;
        foreach ($amounts as $amount) {
            if ($amount > $remaining) {
                throw ValidationException::withMessages(['shares' => 'A soma das partes deve fechar o total.']);
            }
            $remaining -= $amount;
        }
        if ($remaining !== 0) {
            throw ValidationException::withMessages(['shares' => 'A soma das partes deve fechar o total.']);
        }

        return DB::transaction(function () use ($actor, $house, $month, $input, $total, $ids, $amounts, $billId) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            $bill = $billId ? $house->bills()->lockForUpdate()->findOrFail($billId) : null;
            $existing = $bill?->shares()->get();
            $financialChanged = $bill && ($bill->total_cents !== $total || $existing->pluck('amount_cents', 'membership_id')->sortKeys()->all() !== collect($amounts)->sortKeys()->all());
            if ($financialChanged && $existing->contains('is_paid', true)) {
                throw ValidationException::withMessages(['total' => 'Desmarque os pagamentos antes de alterar a divisão.']);
            }
            if (! $bill || $financialChanged) {
                if ($house->memberships()->whereNull('left_at')->whereIn('id', $ids)->count() !== $ids->count()) {
                    throw ValidationException::withMessages(['participants' => 'Selecione membros ativos desta casa.']);
                }
            }
            $bill ??= $house->bills()->make();
            $bill->fill(['name' => trim($input['name']), 'competence' => $month.'-01', 'due_date' => $input['due_date'], 'total_cents' => $total])->save();
            if (! $existing || $financialChanged) {
                $bill->shares()->delete();
                foreach ($amounts as $id => $amount) {
                    $bill->shares()->create(['membership_id' => $id, 'amount_cents' => $amount]);
                }
            }
            $bill->recalculateStatus();

            return $bill;
        });
    }

    private function parse(string $value, string $field): int
    {
        try {
            return Money::parse($value);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
