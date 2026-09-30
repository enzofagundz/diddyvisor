<?php

namespace App\Actions\Bills;

use App\Models\House;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteBill
{
    public function __invoke(User $actor, House $house, int $billId): void
    {
        DB::transaction(function () use ($actor, $house, $billId) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            $bill = $house->bills()->lockForUpdate()->findOrFail($billId);
            if ($bill->shares()->where('is_paid', true)->exists()) {
                throw ValidationException::withMessages(['bill' => 'Conta com pagamentos não pode ser excluída.']);
            }
            $bill->delete();
        });
    }
}
