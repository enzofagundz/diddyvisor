<?php

namespace App\Mcp\Tools\Bills;

use App\Actions\Bills\SaveBill;
use App\Mcp\Support\BillPresenter;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Cadastra uma conta em uma casa (admin). Sem shares, divide o total igualmente entre os participantes; com shares, a soma das partes deve fechar o total. Valores no formato pt-BR, como "1234,56".')]
class CreateBillTool extends DiddyVisorTool
{
    public function __construct(
        McpUserResolver $users,
        private readonly SaveBill $saveBill,
    ) {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'total' => ['required', 'string'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', 'distinct'],
            'shares' => ['sometimes', 'array'],
            'shares.*.membership_id' => ['required', 'integer', 'distinct'],
            'shares.*.amount' => ['required', 'string'],
        ]);

        $user = $this->user();
        $house = $this->house($validated['house_id']);

        $bill = ($this->saveBill)($user, $house, $validated['month'], [
            'name' => $validated['name'],
            'due_date' => $validated['due_date'],
            'total' => $validated['total'],
            'participants' => $validated['participants'],
            'shares' => $validated['shares'] ?? [],
        ]);

        return $this->ok(['bill' => BillPresenter::detail($bill->load('shares.membership.user'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
            'month' => $schema->string()->required()->description('Competência no formato YYYY-MM.'),
            'name' => $schema->string()->required()->description('Nome da conta.'),
            'due_date' => $schema->string()->required()->description('Vencimento no formato YYYY-MM-DD.'),
            'total' => $schema->string()->required()->description('Total em pt-BR, como "1234,56".'),
            'participants' => $schema->array()->items($schema->integer())->required()->description('Ids das participações que dividem a conta.'),
            'shares' => $schema->array()->description('Partes explícitas [{membership_id, amount}]; vazio divide igualmente.'),
        ];
    }
}
