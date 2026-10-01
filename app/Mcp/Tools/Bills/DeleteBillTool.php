<?php

namespace App\Mcp\Tools\Bills;

use App\Actions\Bills\DeleteBill;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Exclui uma conta de uma casa (admin). Contas com qualquer pagamento marcado não podem ser excluídas. Operação destrutiva: exige confirm=true.')]
class DeleteBillTool extends DiddyVisorTool
{
    public function __construct(
        McpUserResolver $users,
        private readonly DeleteBill $deleteBill,
    ) {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'bill_id' => ['required', 'integer'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $this->requireConfirm($request);

        $user = $this->user();
        $house = $this->house($validated['house_id']);

        ($this->deleteBill)($user, $house, $validated['bill_id']);

        return $this->ok(['deleted' => true, 'id' => $validated['bill_id']]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
            'bill_id' => $schema->integer()->required()->description('Id da conta.'),
            'confirm' => $schema->boolean()->required()->description('Envie true para confirmar a exclusão.'),
        ];
    }
}
