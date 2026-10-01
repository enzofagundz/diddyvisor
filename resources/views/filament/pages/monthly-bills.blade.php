<x-filament-panels::page>
    @if ($errors->any())
        <div role="alert" class="dv-error">{{ $errors->first() }}</div>
    @endif
    @php($summary = $this->summary())
    <section class="dv-overview" aria-label="Visão geral do mês">
        <div><span>Contas do mês</span><strong>{{ $summary['overview']->count }}</strong></div>
        <div><span>Valor total</span><strong>{{ \App\Support\Money::display($summary['overview']->total) }}</strong></div>
        <div><span>Não quitadas</span><strong>{{ $summary['overview']->pending }}</strong></div>
        <div><span>Pagas</span><strong>{{ $summary['overview']->paid }}</strong></div>
    </section>
    @if ((int) $summary['overview']->count > 0 && (int) $summary['overview']->pending === 0)
        <section class="dv-all-paid" aria-label="Mês quitado">
            <img src="{{ asset('images/diddy/diddy-pago.png') }}" alt="" width="84" height="84">
            <div>
                <strong>Tudo quitado neste mês.</strong>
                <span>Cada parte foi paga. Bom trabalho, {{ auth()->user()->name }}!</span>
            </div>
        </section>
    @endif
    <nav class="dv-months" aria-label="Competência mensal">
        @foreach ($this->months() as $key => $label)
            <button type="button" wire:click="selectMonth('{{ $key }}')" wire:loading.attr="disabled" @class(['dv-month', 'dv-month-selected' => $month === $key]) aria-current="{{ $month === $key ? 'date' : 'false' }}">{{ $label }}</button>
        @endforeach
    </nav>
    @if ($this->history())
        <label class="dv-history">Histórico
            <select wire:change="selectMonth($event.target.value)">
                <option value="" disabled selected>Selecionar mês anterior</option>
                @foreach ($this->history() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <div class="dv-ledger">
        {{ $this->table }}
    </div>
    <section class="dv-summary" aria-labelledby="monthly-summary-title">
        <h2 id="monthly-summary-title">Resumo do mês</h2>
        <dl class="dv-totals">
            <div><dt>Total</dt><dd>{{ \App\Support\Money::display($summary['overview']->total) }}</dd></div>
            <div><dt>Quitado</dt><dd class="dv-paid">{{ \App\Support\Money::display($summary['totals']->paid) }}</dd></div>
            <div><dt>Pendente</dt><dd>{{ \App\Support\Money::display($summary['totals']->pending) }}</dd></div>
        </dl>
        <div class="dv-summary-scroll">
            <table><thead><tr><th>Membro</th><th>Total</th><th>Quitado</th><th>Pendente</th></tr></thead>
                <tbody>@foreach ($summary['members'] as $row)
                    <tr><th scope="row">{{ $row['name'] }}</th><td>{{ \App\Support\Money::display($row['total']) }}</td><td>{{ \App\Support\Money::display($row['paid']) }}</td><td>{{ \App\Support\Money::display($row['pending']) }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
    </section>
    <section class="dv-upcoming" aria-labelledby="upcoming-title">
        <h2 id="upcoming-title">Próximos vencimentos</h2>
        <ul>
            @forelse ($summary['upcoming'] as $bill)
                <li><span>{{ $bill->name }}</span><time datetime="{{ $bill->due_date->toDateString() }}">{{ $bill->due_date->format('d/m/Y') }}</time>@if ($bill->isOverdue()) <strong>Atrasada</strong> @endif</li>
            @empty
                <li>Nenhuma conta pendente neste mês.</li>
            @endforelse
        </ul>
    </section>
</x-filament-panels::page>
