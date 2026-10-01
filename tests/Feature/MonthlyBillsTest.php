<?php

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

uses(RefreshDatabase::class);

test('equal split is immediate and can restore previous manual parts', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $members = collect([$user, User::factory()->create()])->map(fn ($member) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin]));
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $toggle = TestAction::make('toggleEqualSplit')->schemaComponent('total');
    $page = Livewire::test(MonthlyBills::class)->mountAction('createBill')->fillForm([
        'name' => 'Divisão ajustada', 'due_date' => now()->toDateString(), 'total' => '100,01',
        'participants' => $members->pluck('id')->all(),
        'shares' => [['membership_id' => $members[0]->id, 'amount' => '90,00'], ['membership_id' => $members[1]->id, 'amount' => '10,01']],
    ])->assertActionExists($toggle, fn (Action $action) => ! $action->isConfirmationRequired());
    $page->callAction($toggle)->assertSchemaStateSet(function ($state) {
        expect(array_column($state['shares'], 'amount'))->toBe(['50,01', '50,00']);
    });
    $page->assertActionExists($toggle, fn (Action $action) => $action->getLabel() === 'Desfazer divisão igual');
    $page->callAction($toggle)->assertSchemaStateSet(['previous_shares' => null]);
    $page->assertActionExists($toggle, fn (Action $action) => $action->getLabel() === 'Dividir igualmente');
    $page->callMountedAction()->assertHasNoFormErrors()->assertSee('R$ 90,00')->assertSee('R$ 10,01');
});

test('month outside available window cannot create bill', function () {
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
    expect($house->bills()->get())->toHaveCount(0);
});

test('editing due date moves bill month', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $input = ['name' => 'Conta de setembro', 'competence' => '2026-09', 'due_date' => '2026-09-05', 'total' => '10,00', 'participants' => [$member->id], 'shares' => []];
    $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: $input);
    $bill = $house->bills()->sole();
    $page->mountAction(TestAction::make('editBill')->table($bill))->assertFormFieldIsDisabled('competence')
        ->set('mountedActions.0.data.due_date', '2026-10-05')
        ->assertFormSet(['competence' => '2026-10'], form: 'mountedActionSchema0')
        ->callMountedAction()->assertHasNoFormErrors()->assertSet('month', '2026-10')->assertSee('05/10/2026')->assertSee('Conta de setembro');
    expect($bill->fresh()->competence->format('Y-m'))->toBe('2026-10');
});

test('due date change updates bill month', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->mountAction('createBill')
        ->assertFormSet(['competence' => '2026-09'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.due_date', '2026-11-05')
        ->assertFormSet(['competence' => '2026-11'], form: 'mountedActionSchema0');
});

test('month change moves due date into same month', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->mountAction('createBill')
        ->set('mountedActions.0.data.competence', '2026-12')
        ->assertFormSet(['due_date' => null], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.due_date', '2027-01-31')
        ->assertFormSet(['competence' => '2027-01'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.competence', '2027-02')
        ->assertFormSet(['due_date' => '2027-02-28'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.due_date', '2027-01-31')
        ->set('mountedActions.0.data.competence', '2027-04')
        ->assertFormSet(['due_date' => '2027-04-30'], form: 'mountedActionSchema0');
});

test('competence outside available months does not move due date', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->mountAction('createBill')
        ->set('mountedActions.0.data.due_date', '2026-11-05')
        ->set('mountedActions.0.data.competence', '2030-01')
        ->assertFormSet(['due_date' => '2026-11-05'], form: 'mountedActionSchema0');
});

test('due date and month keep syncing after repeated changes', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->mountAction('createBill')
        ->set('mountedActions.0.data.due_date', '2026-11-05')
        ->assertFormSet(['competence' => '2026-11'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.competence', '2026-12')
        ->assertFormSet(['due_date' => '2026-12-05'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.due_date', '2027-01-10')
        ->assertFormSet(['competence' => '2027-01'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.competence', '2027-02')
        ->assertFormSet(['due_date' => '2027-02-10'], form: 'mountedActionSchema0')
        ->set('mountedActions.0.data.due_date', '2027-03-10')
        ->assertFormSet(['competence' => '2027-03'], form: 'mountedActionSchema0');
});

test('due date outside available months keeps bill month', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->mountAction('createBill')
        ->set('mountedActions.0.data.due_date', '2030-01-05')
        ->assertFormSet(['competence' => '2026-09'], form: 'mountedActionSchema0');
});

test('equal split rejects invalid money without server error', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(MonthlyBills::class)->mountAction('createBill')->fillForm(['total' => 'abc'])->callAction(TestAction::make('toggleEqualSplit')->schemaComponent('total'))->assertHasErrors(['mountedActions.0.data.total']);
});

test('render does not query admin role for each payment cell', function () {
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
    expect($roleQueries)->toBeLessThanOrEqual(5);
});

test('table lists every bill of the month without pagination', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    foreach (range(1, 26) as $index) {
        app(SaveBill::class)($user, $house, now()->format('Y-m'), ['name' => 'Conta '.$index, 'due_date' => now()->toDateString(), 'total' => '1,00', 'participants' => [$member->id], 'shares' => []]);
    }
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(MonthlyBills::class)->assertSee('Conta 26');
});

test('zero share has no payment checkbox', function () {
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
});

test('overdue filter uses local day without hiding summary', function () {
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
});

test('equal split action replaces manual parts before save', function () {
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
    $page->callAction(TestAction::make('toggleEqualSplit')->schemaComponent('total'));
    $page->callMountedAction()->assertHasNoFormErrors()->assertSee('R$ 50,01')->assertSee('R$ 50,00');
});

test('thirteen month navigation changes month without deleting history', function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-09-30 12:00:00', 'America/Sao_Paulo'));
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Somente setembro', 'due_date' => '2026-09-05', 'total' => '1,00', 'participants' => [$member->id], 'shares' => []]);
    $page->assertSee('set/2027')->call('selectMonth', '2026-10')->assertDontSee('Somente setembro');
    $page->call('selectMonth', '2026-09')->assertSee('Somente setembro');
});

test('admin can delete unpaid bill', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Conta descartada', 'due_date' => now()->toDateString(), 'total' => '1,00', 'participants' => [$member->id], 'shares' => []]);
    $page->callAction(TestAction::make('deleteBill')->table($house->bills()->sole()))->assertDontSee('Conta descartada');
});

test('paid bill can be renamed but financial changes are rejected', function () {
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
});

test('payment checkboxes recalculate status and can reopen bill', function () {
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
});

test('admin creates bill with exact equal shares', function () {
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
});
