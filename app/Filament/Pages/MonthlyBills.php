<?php

namespace App\Filament\Pages;

use App\Actions\Bills\CopyPreviousMonth;
use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Enums\BillStatus;
use App\Filament\Tables\Columns\SharePaymentColumn;
use App\Models\Bill;
use App\Models\BillShare;
use App\Models\House;
use App\Queries\MonthlySummary;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class MonthlyBills extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.monthly-bills';

    protected static ?string $title = 'Contas';

    #[Locked]
    public string $month;

    protected ?House $requestHouse = null;

    public function getHeading(): string
    {
        return ucfirst(CarbonImmutable::parse($this->month.'-01')->locale('pt_BR')->translatedFormat('F \\d\\e Y'));
    }

    public function getSubheading(): ?string
    {
        return $this->house()->name.' · Cada parte no seu lugar.';
    }

    public function summary(): array
    {
        return app(MonthlySummary::class)($this->house(), $this->month);
    }

    public function months(): array
    {
        $current = CarbonImmutable::now('America/Sao_Paulo')->startOfMonth();

        return collect(range(0, 12))->mapWithKeys(fn ($offset) => [$current->addMonths($offset)->format('Y-m') => $current->addMonths($offset)->locale('pt_BR')->translatedFormat('M/Y')])->all();
    }

    public function history(): array
    {
        return $this->house()->bills()->where('competence', '<', now('America/Sao_Paulo')->startOfMonth()->toDateString())->distinct()->orderByDesc('competence')->pluck('competence')->mapWithKeys(fn ($date) => [$date->format('Y-m') => $date->locale('pt_BR')->translatedFormat('F/Y')])->all();
    }

    public function selectMonth(string $month): void
    {
        abort_unless(array_key_exists($month, $this->months()) || array_key_exists($month, $this->history()), 422);
        $this->month = $month;
        $this->tableSearch = '';
        $this->resetTableFiltersForm();
        $this->unmountAction();
        $this->resetTable();
    }

    public function mount(): void
    {
        $this->house();
        $this->month = now('America/Sao_Paulo')->format('Y-m');
    }

    public function house(): House
    {
        $house = Filament::getTenant();
        abort_unless($house instanceof House, 404);
        if (! $this->requestHouse?->is($house)) {
            Gate::authorize('view', $house);
            $this->requestHouse = $house;
        }

        return $house;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('copyPreviousMonth')->label('Copiar mês anterior')->color('gray')->visible(fn () => $this->house()->isAdmin(auth()->user()))
            ->modalDescription('Revise as partes. Pagamentos serão desmarcados. Repetir esta ação pode duplicar contas.')
            ->schema([
                Select::make('bill_ids')->label('Contas a copiar')->multiple()->required()->options(fn () => $this->previousBills()->pluck('name', 'id')),
                Repeater::make('revisions')->label('Revisão da divisão')->addable(false)->deletable(false)->itemLabel(fn (array $state) => $state['name'] ?? 'Conta')->schema([
                    Hidden::make('bill_id'), Hidden::make('fingerprint'), Hidden::make('name'),
                    Repeater::make('shares')->label('Participantes e partes')->schema([
                        Select::make('membership_id')->label('Membro')->required()->options(fn () => $this->house()->memberships()->with('user')->get()->mapWithKeys(fn ($member) => [$member->id => $member->label()])),
                        TextInput::make('amount')->label('Parte')->prefix('R$')->required(),
                    ]),
                ]),
            ])->fillForm(fn () => ['bill_ids' => [], 'revisions' => $this->previousBills()->map(fn (Bill $bill) => [
                'bill_id' => $bill->id, 'name' => $bill->name, 'fingerprint' => $bill->copyFingerprint(),
                'shares' => $bill->shares->map(fn ($share) => ['membership_id' => $share->membership_id, 'amount' => Money::decimal($share->amount_cents)])->all(),
            ])->all()])->action(function (array $data) {
                app(CopyPreviousMonth::class)(auth()->user(), $this->house(), $this->month, $data);
                $this->resetTable();
            }), Action::make('createBill')->label('Adicionar conta')->visible(fn () => $this->house()->isAdmin(auth()->user()))
            ->schema($this->billFields())->action(function (array $data) {
                $this->followBillMonth($this->saveBill($data));
            })];
    }

    private function followBillMonth(Bill $bill): void
    {
        if ($this->month !== $bill->competence->format('Y-m')) {
            $this->month = $bill->competence->format('Y-m');
            $this->tableSearch = '';
            $this->resetTableFiltersForm();
        }
        $this->resetTable();
    }

    private function saveBill(array $data, ?Bill $record = null): Bill
    {
        try {
            $month = $data['competence'] ?? $record?->competence->format('Y-m') ?? $this->month;
            if (! is_string($month) || ! array_key_exists($month, $this->months() + $this->history())) {
                throw ValidationException::withMessages(['competence' => 'Selecione um mês disponível nesta casa.']);
            }

            return app(SaveBill::class)(auth()->user(), $this->house(), $month, $data, $record?->id);
        } catch (ValidationException $exception) {
            $path = $this->getMountedActionSchema()->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => [$path.'.'.$field => $messages])->all());
        }
    }

    private function previousBills()
    {
        return $this->house()->bills()->where('competence', CarbonImmutable::parse($this->month.'-01')->subMonth()->toDateString())->with('shares')->orderBy('id')->get();
    }

    protected function billFields(): array
    {
        return [
            Hidden::make('previous_shares')->default(null)->dehydrated(false),
            TextInput::make('name')->label('Conta')->required()->maxLength(255),
            Select::make('competence')->label('Mês da conta')->required()->live()->default(fn () => $this->month)
                ->options(fn () => collect($this->months() + $this->history())->mapWithKeys(fn ($label, $month) => [$month => ucfirst(CarbonImmutable::parse($month.'-01')->locale('pt_BR')->translatedFormat('F \\d\\e Y'))])->all())
                ->disabled(fn (?Bill $record) => $record !== null)->dehydrated()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                    if (blank($state) || blank($due = $get('due_date')) || ! array_key_exists($state, $this->months() + $this->history())) {
                        return;
                    }
                    $dueDate = CarbonImmutable::parse($due);
                    if ($dueDate->format('Y-m') === $state) {
                        return;
                    }
                    $month = CarbonImmutable::parse($state.'-01');
                    $set('due_date', $month->day(min($dueDate->day, $month->daysInMonth))->toDateString());
                })
                ->helperText(fn (?Bill $record) => $record ? 'Mês em que esta conta aparece na planilha. Segue a data de vencimento.' : 'Escolha em qual mês a conta aparece na planilha. A data de vencimento acompanha este mês.'),
            DatePicker::make('due_date')->label('Data de vencimento')->required()->displayFormat('d/m/Y')->native(false)
                ->defaultFocusedDate(fn (Get $get) => ($get('competence') ?: $this->month).'-01')
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                    if (blank($state)) {
                        return;
                    }
                    $month = substr($state, 0, 7);
                    if ($get('competence') === $month || ! array_key_exists($month, $this->months() + $this->history())) {
                        return;
                    }
                    $set('competence', $month);
                })
                ->helperText('Selecione o dia em que a conta deve ser paga. O mês da conta acompanha esta data.'),
            TextInput::make('total')->label('Valor total')->prefix('R$')->inputMode('decimal')->required()->helperText('Exemplo: 100,00. Sem separador de milhar.')->disabled(fn (?Bill $record) => $record?->shares->contains('is_paid', true) ?? false)->dehydrated()
                ->hintActions([Action::make('toggleEqualSplit')
                    ->label(fn (Get $get) => $get('previous_shares') === null ? 'Dividir igualmente' : 'Desfazer divisão igual')
                    ->color('gray')
                    ->visible(fn (?Bill $record) => ! ($record?->shares->contains('is_paid', true) ?? false))
                    ->action(function (Get $schemaGet, Set $schemaSet, TextInput $schemaComponent) {
                        if ($schemaGet('previous_shares') !== null) {
                            $schemaSet('shares', $schemaGet('previous_shares') ?? []);
                            $schemaSet('previous_shares', null);

                            return;
                        }
                        try {
                            $total = Money::parse((string) $schemaGet('total'));
                        } catch (\InvalidArgumentException $exception) {
                            throw ValidationException::withMessages([$schemaComponent->getStatePath() => $exception->getMessage()]);
                        }
                        $ids = collect($schemaGet('participants') ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
                        if ($ids->isEmpty()) {
                            throw ValidationException::withMessages(['participants' => 'Selecione participantes antes de dividir.']);
                        }
                        $schemaSet('previous_shares', $schemaGet('shares') ?? []);
                        $schemaSet('shares', $ids->map(fn ($id, $index) => ['membership_id' => $id, 'amount' => Money::decimal(intdiv($total, $ids->count()) + ($index < $total % $ids->count() ? 1 : 0))])->all());
                    }),
                ]),
            Select::make('participants')->label('Participantes')->multiple()->required()->disabled(fn (?Bill $record) => $record?->shares->contains('is_paid', true) ?? false)->dehydrated()->options(fn (?Bill $record) => $this->house()->memberships()->with('user')->where(fn ($query) => $query->whereNull('left_at')->orWhereIn('id', $record?->shares->pluck('membership_id') ?? []))->get()->mapWithKeys(fn ($member) => [$member->id => $member->label()]))->default(fn () => $this->house()->memberships()->whereNull('left_at')->pluck('id')->all()),
            Repeater::make('shares')->label('Partes ajustadas')->default([])->disabled(fn (?Bill $record) => $record?->shares->contains('is_paid', true) ?? false)->dehydrated()->schema([
                Select::make('membership_id')->label('Membro')->options(fn () => $this->house()->memberships()->with('user')->get()->mapWithKeys(fn ($member) => [$member->id => $member->label()]))->required(),
                TextInput::make('amount')->label('Parte')->prefix('R$')->required(),
            ])->helperText('Deixe vazio para dividir igualmente. Para ajustar, informe uma parte por participante.'),
        ];
    }

    public function table(Table $table): Table
    {
        $house = $this->house();
        $admin = $house->isAdmin(auth()->user());
        $members = $house->memberships()->with('user')->where(fn ($query) => $query->whereNull('left_at')->orWhereIn('id', BillShare::query()->whereIn('bill_id', $house->bills()->where('competence', $this->month.'-01')->select('id'))->select('membership_id')))->orderBy('id')->get();
        $columns = [
            TextColumn::make('name')->label('Conta')->searchable()->sortable(),
            TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y')->sortable(),
            TextColumn::make('total_cents')->label('Valor total')->formatStateUsing(fn ($state) => Money::display($state))->sortable()->alignEnd(),
        ];
        foreach ($members as $member) {
            $columns[] = TextColumn::make('member_'.$member->id.'_amount')->label($member->label())->state(fn (Bill $record) => $record->shares->firstWhere('membership_id', $member->id)?->amount_cents)->formatStateUsing(fn ($state) => Money::display($state))->placeholder('—')->alignEnd()->extraCellAttributes(['class' => 'dv-member-start']);
            $columns[] = SharePaymentColumn::make('member_'.$member->id.'_paid')->label($member->label().' pagou?')
                ->state(function (Bill $record) use ($member) {
                    $share = $record->shares->firstWhere('membership_id', $member->id);

                    return $share?->amount_cents > 0 ? $share->is_paid : null;
                })
                ->disabled(fn (Bill $record) => ! ($record->shares->firstWhere('membership_id', $member->id)?->amount_cents > 0) || (! $admin && ($member->left_at !== null || $member->user_id !== auth()->id())))
                ->updateStateUsing(function (Bill $record, mixed $state) use ($member) {
                    abort_unless(is_bool($state), 422);
                    $result = app(SetSharePayment::class)(auth()->user(), $this->house(), $record->id, $member->id, $state);
                    $record->refresh();

                    return $result;
                });
        }
        $columns[] = TextColumn::make('status')->label('Status')->badge()->description(fn (Bill $record) => $record->isOverdue() ? 'Atrasada' : null);

        return $table->query($house->bills()->where('competence', $this->month.'-01')->with('shares')->getQuery())
            ->columns($columns)->defaultSort(fn ($query) => $query->orderBy('due_date')->orderBy('id'))->paginated(false)
            ->emptyState(view('filament.components.empty-state', [
                'image' => 'images/diddy/diddy-sem-contas.png',
                'heading' => 'Ainda sem contas neste mês',
                'description' => 'Adicione uma conta ou copie o mês anterior para começar.',
            ]))
            ->filters([
                SelectFilter::make('status')->label('Status')->options(BillStatus::class),
                Filter::make('overdue')->label('Atrasadas')->query(fn ($query) => $query->where('due_date', '<', now('America/Sao_Paulo')->toDateString())->where('status', '!=', BillStatus::Paid)),
            ])
            ->recordActions([
                Action::make('deleteBill')->label('Excluir')->color('danger')->requiresConfirmation()
                    ->visible($admin)->disabled(fn (Bill $record) => $record->shares->contains('is_paid', true))
                    ->action(function (Bill $record) {
                        app(DeleteBill::class)(auth()->user(), $this->house(), $record->id);
                        $this->resetTable();
                    }),
                Action::make('editBill')->label('Editar')->visible($admin)->schema($this->billFields())
                    ->fillForm(fn (Bill $record) => [
                        'name' => $record->name, 'competence' => $record->competence->format('Y-m'), 'due_date' => $record->due_date->toDateString(), 'total' => Money::decimal($record->total_cents),
                        'participants' => $record->shares->pluck('membership_id')->all(),
                        'shares' => $record->shares->map(fn ($share) => ['membership_id' => $share->membership_id, 'amount' => Money::decimal($share->amount_cents)])->all(),
                    ])
                    ->action(function (Bill $record, array $data) {
                        $this->followBillMonth($this->saveBill($data, $record));
                    }),
            ]);
    }
}
