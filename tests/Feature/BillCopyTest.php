<?php

namespace Tests\Feature;

use App\Actions\Bills\SaveBill;
use App\Actions\Houses\ManageMembership;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_rolls_back_whole_batch_when_one_participant_has_left(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $admin = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $member = $house->memberships()->create(['user_id' => $other->id, 'display_name' => $other->name]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class);
        foreach ([$admin, $member] as $index => $participant) {
            $page->callAction('createBill', data: ['name' => 'Conta '.$index, 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => [$participant->id], 'shares' => []]);
        }
        $sources = $house->bills()->pluck('id')->all();
        app(ManageMembership::class)->remove($user, $house, $member->id);
        $destination = now()->startOfMonth()->addMonth()->format('Y-m');
        $page->call('selectMonth', $destination)->callAction('copyPreviousMonth', data: ['bill_ids' => $sources])->assertHasErrors();
        $this->assertSame(0, $house->bills()->where('competence', $destination.'-01')->count());
    }

    public function test_copy_rejects_source_changed_after_review_opens(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $input = ['name' => 'Original', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => [$member->id], 'shares' => []];
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: $input);
        $source = $house->bills()->sole();
        $destination = now()->startOfMonth()->addMonth()->format('Y-m');
        $page->call('selectMonth', $destination)->mountAction('copyPreviousMonth')->fillForm(['bill_ids' => [$source->id]]);
        app(SaveBill::class)($user, $house, now()->format('Y-m'), [...$input, 'name' => 'Alterada'], $source->id);
        $page->callMountedAction()->assertHasErrors(['bill_ids']);
        $this->assertSame(0, $house->bills()->where('competence', $destination.'-01')->count());
    }

    public function test_copy_advances_due_date_without_overflow_and_clears_payment(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2028-01-15 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Aluguel', 'due_date' => '2028-01-31', 'total' => '100,00', 'participants' => [$member->id], 'shares' => []]);
        $source = $house->bills()->sole();
        $page->call('updateTableColumnState', 'member_'.$member->id.'_paid', (string) $source->id, true)->call('selectMonth', '2028-02');
        $page->callAction('copyPreviousMonth', data: ['bill_ids' => [$source->id]])->assertHasNoFormErrors()->assertSee('29/02/2028')->assertSee('Pendente');
    }
}
