<?php

namespace App\Mcp\Tools\Bills;

use App\Mcp\Support\BillPresenter;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Support\ToolException;
use App\Mcp\Tools\DiddyVisorTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Retorna uma conta de uma casa do usuário configurado, com as partes de cada membro e quem já pagou.')]
class GetBillTool extends DiddyVisorTool
{
    public function __construct(McpUserResolver $users)
    {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'bill_id' => ['required', 'integer'],
        ]);

        $user = $this->user();
        $house = $this->house($validated['house_id']);

        Gate::forUser($user)->authorize('view', $house);

        $bill = $house->bills()
            ->with('shares.membership.user')
            ->find($validated['bill_id']);

        if ($bill === null) {
            throw ToolException::notFound('Conta não encontrada.');
        }

        return $this->ok(['bill' => BillPresenter::detail($bill)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
            'bill_id' => $schema->integer()->required()->description('Id da conta.'),
        ];
    }
}
