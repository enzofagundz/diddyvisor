<?php

namespace Tests\Feature;

use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Actions\Houses\ManageMembership;
use App\Enums\BillStatus;
use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_payments_keep_final_status_consistent(): void
    {
        [$house, $users, $members] = $this->houseWithAdmins();
        $bill = app(SaveBill::class)($users[0], $house, now()->format('Y-m'), ['name' => 'Concorrente', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => $members->pluck('id')->all(), 'shares' => []]);
        $results = $this->simultaneously([
            fn () => app(SetSharePayment::class)($users[0], $house, $bill->id, $members[0]->id, true),
            fn () => app(SetSharePayment::class)($users[1], $house, $bill->id, $members[1]->id, true),
        ]);
        $this->assertSame(['ok', 'ok'], $results);
        $this->assertSame(BillStatus::Paid, $house->bills()->sole()->status);
        $this->assertSame(2, $bill->shares()->where('is_paid', true)->count());
    }

    public function test_simultaneous_admin_departures_preserve_last_admin(): void
    {
        [$house, $users, $members] = $this->houseWithAdmins();
        $results = $this->simultaneously([
            fn () => app(ManageMembership::class)->remove($users[0], $house, $members[0]->id),
            fn () => app(ManageMembership::class)->remove($users[1], $house, $members[1]->id),
        ]);
        sort($results);
        $this->assertSame(['Illuminate\\Validation\\ValidationException', 'ok'], $results);
        $this->assertSame(1, $house->memberships()->whereNull('left_at')->where('role', MembershipRole::Admin)->count());
    }

    private function houseWithAdmins(): array
    {
        $house = House::create(['name' => 'Casa']);
        $users = User::factory()->count(2)->create();
        $members = $users->map(fn ($user) => $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]));

        return [$house, $users, $members];
    }

    private function simultaneously(array $operations): array
    {
        $this->assertTrue(function_exists('pcntl_fork'), 'A verificação concorrente exige pcntl.');
        DB::purge();
        $children = [];
        foreach ($operations as $operation) {
            [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Não foi possível iniciar o processo concorrente.');
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
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();

        return $results;
    }
}
