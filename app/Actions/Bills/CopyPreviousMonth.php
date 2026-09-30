<?php

namespace App\Actions\Bills;

use App\Models\House;
use App\Models\User;
use App\Support\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CopyPreviousMonth
{
    public function __invoke(User $actor, House $house, string $month, array $data): void
    {
        Validator::make($data, [
            'bill_ids' => ['required', 'array', 'min:1'], 'bill_ids.*' => ['integer', 'distinct'],
            'revisions' => ['required', 'array'], 'revisions.*.bill_id' => ['required', 'integer', 'distinct'],
            'revisions.*.fingerprint' => ['required', 'string', 'size:64'], 'revisions.*.shares' => ['required', 'array', 'min:1'],
        ])->validate();

        DB::transaction(function () use ($actor, $house, $month, $data) {
            $house = House::query()->lockForUpdate()->findOrFail($house->id);
            Gate::forUser($actor)->authorize('update', $house);
            $previous = (new DateTimeImmutable($month.'-01'))->modify('-1 month')->format('Y-m-d');
            $sources = $house->bills()->where('competence', $previous)->whereIn('id', $data['bill_ids'])->orderBy('id')->lockForUpdate()->with('shares')->get();
            if ($sources->count() !== count($data['bill_ids'])) {
                throw ValidationException::withMessages(['bill_ids' => 'Selecione contas do mês anterior desta casa.']);
            }
            $reviews = collect($data['revisions'])->keyBy('bill_id');
            foreach ($sources as $source) {
                $review = $reviews->get($source->id);
                if (! $review || ! hash_equals($source->copyFingerprint(), $review['fingerprint'])) {
                    throw ValidationException::withMessages(['bill_ids' => 'Uma conta mudou. Reabra a revisão antes de copiar.']);
                }
                $date = new DateTimeImmutable($source->due_date->toDateString());
                $next = $date->modify('first day of next month');
                $next = $next->setDate((int) $next->format('Y'), (int) $next->format('m'), min((int) $date->format('d'), (int) $next->format('t')));
                app(SaveBill::class)($actor, $house, $month, [
                    'name' => $source->name, 'due_date' => $next->format('Y-m-d'), 'total' => Money::decimal($source->total_cents),
                    'participants' => array_column($review['shares'], 'membership_id'), 'shares' => $review['shares'],
                ]);
            }
        });
    }
}
