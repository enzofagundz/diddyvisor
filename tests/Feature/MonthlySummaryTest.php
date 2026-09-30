<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_paid_shares_in_partial_bill_even_when_search_hides_bill(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $members = collect([$user, User::factory()->create()])->map(fn ($member) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Admin]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->callAction('createBill', data: ['name' => 'Aluguel', 'due_date' => now()->toDateString(), 'total' => '100,00', 'participants' => $members->pluck('id')->all(), 'shares' => []]);
        $page->call('updateTableColumnState', 'member_'.$members[0]->id.'_paid', (string) $house->bills()->sole()->id, true)
            ->searchTable('Não existe')->assertSee('Resumo do mês')->assertSeeInOrder(['Quitado', 'R$ 50,00', 'Pendente', 'R$ 50,00'])->assertSee('Próximos vencimentos')->assertSee('Aluguel');
    }
}
