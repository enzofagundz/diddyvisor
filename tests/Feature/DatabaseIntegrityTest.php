<?php

use App\Actions\Bills\SaveBill;
use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('postgres constraints reject invalid membership and share states', function () {
    expect(DB::getDriverName())->toBe('pgsql')
        ->and(DB::getDatabaseName())->toBe('diddyvisor_testing');
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $bill = app(SaveBill::class)($user, $house, now()->format('Y-m'), ['name' => 'Conta', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => [$member->id], 'shares' => []]);
    $attempts = [
        ['23505', fn () => $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name])],
        ['23514', fn () => DB::table('memberships')->where('id', $member->id)->update(['role' => 2])],
        ['23514', fn () => DB::table('bills')->where('id', $bill->id)->update(['total_cents' => 0])],
        ['23514', fn () => DB::table('bill_shares')->where('bill_id', $bill->id)->update(['amount_cents' => -1])],
        ['23514', fn () => DB::table('bill_shares')->where('bill_id', $bill->id)->update(['amount_cents' => 0, 'is_paid' => true])],
    ];
    foreach ($attempts as [$sqlState, $attempt]) {
        try {
            DB::transaction($attempt);
            $this->fail('PostgreSQL aceitou estado inválido.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe($sqlState);
        }
    }
});
