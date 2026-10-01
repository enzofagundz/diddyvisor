<?php

namespace App\Mcp\Tools\Bills;

use App\Mcp\Support\BillPresenter;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use App\Queries\MonthlySummary;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Resumo financeiro de um mês (YYYY-MM) de uma casa: total das contas, quantas pagas, valores quitados e pendentes, saldo por membro e próximos vencimentos.')]
class GetMonthlySummaryTool extends DiddyVisorTool
{
    public function __construct(
        McpUserResolver $users,
        private readonly MonthlySummary $summaries,
    ) {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $user = $this->user();
        $house = $this->house($validated['house_id']);

        Gate::forUser($user)->authorize('view', $house);

        $month = $validated['month'];
        $summary = ($this->summaries)($house, $month);

        $members = collect($summary['members'])
            ->sortBy('membership_id')
            ->values()
            ->map(fn (array $row): array => [
                'membership_id' => $row['membership_id'],
                'name' => $row['name'],
                'total' => Money::decimal($row['total']),
                'total_cents' => (int) $row['total'],
                'paid' => Money::decimal($row['paid']),
                'paid_cents' => (int) $row['paid'],
                'pending' => Money::decimal($row['pending']),
                'pending_cents' => (int) $row['pending'],
            ])
            ->values()
            ->all();

        return $this->ok([
            'month' => $month,
            'count' => (int) $summary['overview']->count,
            'total' => Money::decimal($summary['overview']->total),
            'total_cents' => (int) $summary['overview']->total,
            'paid_bills' => (int) $summary['overview']->paid,
            'pending_bills' => (int) $summary['overview']->pending,
            'paid' => Money::decimal($summary['totals']->paid),
            'paid_cents' => (int) $summary['totals']->paid,
            'pending' => Money::decimal($summary['totals']->pending),
            'pending_cents' => (int) $summary['totals']->pending,
            'members' => $members,
            'upcoming' => $summary['upcoming']
                ->map(fn ($bill): array => BillPresenter::summary($bill))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
            'month' => $schema->string()->required()->description('Competência no formato YYYY-MM.'),
        ];
    }
}
