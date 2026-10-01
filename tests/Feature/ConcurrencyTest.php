<?php

use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Actions\Houses\ManageMembership;
use App\Enums\BillStatus;
use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

test('simultaneous payments keep final status consistent', function () {
    [$house, $users, $members] = concurrencyHouseWithAdmins();
    $bill = app(SaveBill::class)($users[0], $house, now()->format('Y-m'), ['name' => 'Concorrente', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => $members->pluck('id')->all(), 'shares' => []]);
    $results = concurrencySimultaneously([
        fn () => app(SetSharePayment::class)($users[0], $house, $bill->id, $members[0]->id, true),
        fn () => app(SetSharePayment::class)($users[1], $house, $bill->id, $members[1]->id, true),
    ]);
    expect($results)->toBe(['ok', 'ok'])
        ->and($house->bills()->sole()->status)->toBe(BillStatus::Paid)
        ->and($bill->shares()->where('is_paid', true)->count())->toBe(2);
});

test('simultaneous admin departures preserve last admin', function () {
    [$house, $users, $members] = concurrencyHouseWithAdmins();
    $results = concurrencySimultaneously([
        fn () => app(ManageMembership::class)->remove($users[0], $house, $members[0]->id),
        fn () => app(ManageMembership::class)->remove($users[1], $house, $members[1]->id),
    ]);
    sort($results);
    expect($results)->toBe(['Illuminate\\Validation\\ValidationException', 'ok'])
        ->and($house->memberships()->whereNull('left_at')->where('role', MembershipRole::Admin)->count())->toBe(1);
});

/**
 * @return array{0: House, 1: Collection<int, User>, 2: Collection<int, Membership>}
 */
function concurrencyHouseWithAdmins(): array
{
    $house = House::create(['name' => 'Casa']);
    $users = User::factory()->count(2)->create();
    $members = $users->map(fn ($user) => $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]));

    return [$house, $users, $members];
}

/**
 * @param  array<int, callable>  $operations
 * @return array<int, string>
 */
function concurrencySimultaneously(array $operations): array
{
    expect(function_exists('pcntl_fork'))->toBeTrue('A verificação concorrente exige pcntl.');
    DB::purge();
    $children = [];
    foreach ($operations as $operation) {
        [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Não foi possível iniciar o processo concorrente.');
        }
        if ($pid === 0) {
            fclose($parent);
            fread($child, 1);
            try {
                DB::purge();
                DB::statement("SET lock_timeout = '5s'");
                $operation();
                fwrite($child, 'ok');
            } catch (Throwable $exception) {
                fwrite($child, $exception::class);
            }
            fclose($child);
            exit(0);
        }
        fclose($child);
        stream_set_timeout($parent, 10);
        $children[] = [$pid, $parent];
    }
    foreach ($children as [$pid, $socket]) {
        fwrite($socket, '1');
    }
    $results = [];
    foreach ($children as [$pid, $socket]) {
        $results[] = stream_get_contents($socket);
        fclose($socket);
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    DB::purge();

    return $results;
}
