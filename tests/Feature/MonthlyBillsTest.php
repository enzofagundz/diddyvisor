<?php

namespace Tests\Feature;

use App\Actions\Bills\SaveBill;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyBillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_equal_split_is_immediate_and_can_restore_previous_manual_parts(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create()])->map(fn ($member) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $split = TestAction::make('splitEqually')->schemaComponent('total');
        $undo = TestAction::make('undoEqualSplit')->schemaComponent('total');
        $page = Livewire::test(MonthlyBills::class)->mountAction('createBill')->fillForm([
            'name' => 'Divisão ajustada', 'due_date' => now()->toDateString(), 'total' => '100,01',
            'participants' => $members->pluck('id')->all(),
            'shares' => [['membership_id' => $members[0]->id, 'amount' => '90,00'], ['membership_id' => $members[1]->id, 'amount' => '10,01']],
        ])->assertActionExists($split, fn (Action $action) => ! $action->isConfirmationRequired());
        $page->callAction($split)->assertSchemaStateSet(function ($state) {
            $this->assertSame(['50,01', '50,00'], array_column($state['shares'], 'amount'));
        })->assertActionVisible($undo)->callAction($undo)->assertSchemaStateSet(['previous_shares' => null]);
        $page->callMountedAction()->assertHasNoFormErrors()->assertSee('R$ 90,00')->assertSee('R$ 10,01');
    }

    public function test_month_outside_available_window_cannot_create_bill(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(MonthlyBills::class)->callAction('createBill', data: [
            'name' => 'Mês inválido', 'competence' => '2030-01', 'due_date' => '2030-01-05',
            'total' => '10,00', 'participants' => [$member->id], 'shares' => [],
        ])->assertHasFormErrors(['competence'])->assertSet('month', '2026-09');
        $this->assertCount(0, $house->bills()->get());
    }

    public function test_due_date_edit_preserves_existing_bill_month(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $input = ['name' => 'Conta de setembro', 'due_date' => '2026-09-05', 'total' => '10,00', 'participants' => [$member->id], 'shares' => []];
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: $input);
        $bill = $house->bills()->sole();
        $page->mountAction(TestAction::make('editBill')->table($bill))->assertFormFieldIsDisabled('competence')
            ->fillForm([...$input, 'competence' => '2026-10', 'due_date' => '2026-10-05'])->callMountedAction()->assertHasNoFormErrors()->assertSee('05/10/2026')->assertSee('Conta de setembro');
        $this->assertSame('2026-09', $bill->fresh()->competence->format('Y-m'));
    }

    public function test_admin_selects_bill_month_in_modal_independently_of_due_date(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->mountAction('createBill')->assertFormSet(['competence' => '2026-09'], form: 'mountedActionSchema0')->assertFormFieldExists('competence')->fillForm([
            'name' => 'Conta de outubro', 'competence' => '2026-10', 'due_date' => '2026-11-05',
            'total' => '10,00', 'participants' => [$member->id], 'shares' => [],
        ])->callMountedAction()->assertHasNoFormErrors()->assertSet('month', '2026-10')->assertSee('Conta de outubro')->assertSee('05/11/2026');
        $page->call('selectMonth', '2026-09')->assertDontSee('Conta de outubro');
    }

    public function test_equal_split_rejects_invalid_money_without_server_error(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(MonthlyBills::class)->mountAction('createBill')->fillForm(['total' => 'abc'])->callAction(TestAction::make('splitEqually')->schemaComponent('total'))->assertHasErrors(['mountedActions.0.data.total']);
    }

    public function test_render_does_not_query_admin_role_for_each_payment_cell(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create(), User::factory()->create()])->map(fn ($member) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin]));
        foreach (range(1, 3) as $index) {
            app(SaveBill::class)($user, $house, now()->format('Y-m'), ['name' => 'Conta '.$index, 'due_date' => now()->toDateString(), 'total' => '100,00', 'participants' => $members->pluck('id')->all(), 'shares' => []]);
        }
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $roleQueries = 0;
        DB::listen(function ($query) use (&$roleQueries) {
            if (str_contains($query->sql, 'select exists') && str_contains($query->sql, '"role"')) {
                $roleQueries++;
            }
        });
        Livewire::test(MonthlyBills::class)->assertSee('Conta 3');
        $this->assertLessThanOrEqual(5, $roleQueries);
    }

    public function test_zero_share_has_no_payment_checkbox(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['name' => 'Ana']);
        $other = User::factory()->create(['name' => 'Bruno']);
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
        $zero = $house->memberships()->create(['user_id' => $other->id, 'display_name' => 'Bruno']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(MonthlyBills::class)->callAction('createBill', data: [
            'name' => 'Parte zero', 'due_date' => now()->toDateString(), 'total' => '0,01', 'participants' => [$member->id, $zero->id], 'shares' => [],
        ])->assertSee('R$ 0,00')->assertDontSee('aria-label="Bruno #'.$zero->id.' pagou?"', false);
    }

    public function test_overdue_filter_uses_local_day_without_hiding_summary(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-30 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class);
        foreach (['Conta atrasada' => '2026-09-29', 'Conta de hoje' => '2026-09-30'] as $name => $due) {
            $page->callAction('createBill', data: ['name' => $name, 'due_date' => $due, 'total' => '10,00', 'participants' => [$member->id], 'shares' => []]);
        }
        $page->filterTable('overdue')->assertSee('Atrasada')->assertCanSeeTableRecords($house->bills()->where('name', 'Conta atrasada')->get())->assertCanNotSeeTableRecords($house->bills()->where('name', 'Conta de hoje')->get())->assertSee('R$ 20,00');
    }

    public function test_equal_split_action_replaces_manual_parts_before_save(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create()])->map(fn ($member) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->mountAction('createBill')->fillForm([
            'name' => 'Divisão nova', 'due_date' => now()->toDateString(), 'total' => '100,01',
            'participants' => $members->pluck('id')->all(),
            'shares' => [['membership_id' => $members[0]->id, 'amount' => '90,00'], ['membership_id' => $members[1]->id, 'amount' => '10,01']],
        ]);
        $page->callAction(TestAction::make('splitEqually')->schemaComponent('total'));
        $page->callMountedAction()->assertHasNoFormErrors()->assertSee('R$ 50,01')->assertSee('R$ 50,00');
    }

    public function test_thirteen_month_navigation_changes_month_without_deleting_history(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-30 12:00:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Somente setembro', 'due_date' => '2026-10-05', 'total' => '1,00', 'participants' => [$member->id], 'shares' => []]);
        $page->assertSee('set/2027')->call('selectMonth', '2026-10')->assertDontSee('Somente setembro');
        $page->call('selectMonth', '2026-09')->assertSee('Somente setembro');
    }

    public function test_admin_can_delete_unpaid_bill(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Conta descartada', 'due_date' => now()->toDateString(), 'total' => '1,00', 'participants' => [$member->id], 'shares' => []]);
        $page->callAction(TestAction::make('deleteBill')->table($house->bills()->sole()))->assertDontSee('Conta descartada');
    }

    public function test_paid_bill_can_be_renamed_but_financial_changes_are_rejected(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $input = ['name' => 'Internet', 'due_date' => now()->toDateString(), 'total' => '50,00', 'participants' => [$member->id], 'shares' => []];
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: $input);
        $bill = $house->bills()->sole();
        $page->call('updateTableColumnState', 'member_'.$member->id.'_paid', (string) $bill->id, true);
        $page->callAction(TestAction::make('editBill')->table($bill), data: [...$input, 'name' => 'Internet fibra'])->assertHasNoFormErrors()->assertSee('Internet fibra');
        $page->callAction(TestAction::make('editBill')->table($bill), data: [...$input, 'total' => '60,00', 'shares' => [['membership_id' => $member->id, 'amount' => '60,00']]])->assertHasFormErrors(['total']);
        $page->assertFormFieldIsDisabled('total')->assertFormFieldIsDisabled('participants')->assertFormFieldIsDisabled('shares');
    }

    public function test_payment_checkboxes_recalculate_status_and_can_reopen_bill(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create()])->map(fn ($member) => $house->memberships()->create([
            'user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin,
        ]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: [
            'name' => 'Água', 'due_date' => now()->toDateString(), 'total' => '10,00',
            'participants' => $members->pluck('id')->all(), 'shares' => [],
        ]);
        $bill = $house->bills()->sole();
        $page->call('updateTableColumnState', 'member_'.$members[0]->id.'_paid', (string) $bill->id, true)->assertSee('Parcial');
        $page->call('updateTableColumnState', 'member_'.$members[1]->id.'_paid', (string) $bill->id, true)->assertSee('Pago');
        $page->call('updateTableColumnState', 'member_'.$members[0]->id.'_paid', (string) $bill->id, false)->assertSee('Parcial');
    }

    public function test_admin_creates_bill_with_exact_equal_shares(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create(), User::factory()->create()])->map(fn ($member) => $house->memberships()->create([
            'user_id' => $member->id, 'display_name' => $member->name,
            'role' => $member->is($user) ? MembershipRole::Admin : MembershipRole::Member,
        ]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);

        Livewire::test(MonthlyBills::class)->callAction('createBill', data: [
            'name' => 'Energia', 'due_date' => now()->toDateString(), 'total' => '100,00',
            'participants' => $members->pluck('id')->all(), 'shares' => [],
        ])->assertHasNoFormErrors()->assertSee('Energia')->assertSee('R$ 33,34')->assertSee('Pendente');
    }
}
