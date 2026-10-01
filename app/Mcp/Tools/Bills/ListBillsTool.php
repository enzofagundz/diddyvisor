<?php

namespace App\Mcp\Tools\Bills;

use App\Mcp\Support\BillPresenter;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Support\ToolException;
use App\Mcp\Tools\DiddyVisorTool;
use App\Models\Bill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Lista as contas de uma casa do usuário configurado, com filtro por mês (YYYY-MM) e status. Paginado (limit 20, máx 100).')]
class ListBillsTool extends DiddyVisorTool
{
    public function __construct(McpUserResolver $users)
    {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string'],
        ]);

        $user = $this->user($request);
        $house = $this->house($validated['house_id']);

        Gate::forUser($user)->authorize('view', $house);

        $page = $this->page($request);

        $query = $house->bills()->withCount([
            'shares',
            'shares as paid_shares_count' => fn ($shares) => $shares->where('is_paid', true),
        ]);

        if (! empty($validated['month'])) {
            $query->where('competence', $validated['month'].'-01');
        }

        if (! empty($validated['status'])) {
            $status = BillPresenter::statusFromLabel($validated['status']);

            if ($status === null) {
                throw ToolException::validation('Status de conta inválido.');
            }

            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->skip($page['offset'])
            ->take($page['limit'])
            ->get()
            ->map(fn (Bill $bill): array => [
                ...BillPresenter::summary($bill),
                'shares_count' => (int) $bill->shares_count,
                'paid_shares_count' => (int) $bill->paid_shares_count,
            ])
            ->all();

        return $this->listed($items, $total, $page['limit'], $page['offset']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
            'month' => $schema->string()->description('Competência no formato YYYY-MM.'),
            'status' => $schema->string()->description('pending, partial ou paid.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 20).'),
            'offset' => $schema->integer()->min(0)->description('Deslocamento para paginação.'),
        ];
    }
}
