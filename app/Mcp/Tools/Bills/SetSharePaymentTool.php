<?php

namespace App\Mcp\Tools\Bills;

use App\Actions\Bills\SetSharePayment;
use App\Mcp\Support\BillPresenter;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Marca ou desmarca o pagamento de uma parte da conta. Administradores marcam qualquer membro; cada membro marca apenas a própria parte.')]
class SetSharePaymentTool extends DiddyVisorTool
{
    public function __construct(
        McpUserResolver $users,
        private readonly SetSharePayment $setSharePayment,
    ) {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
            'bill_id' => ['required', 'integer'],
            'membership_id' => ['required', 'integer'],
            'paid' => ['required', 'boolean'],
        ]);

        $user = $this->user($request);
        $house = $this->house($validated['house_id']);

        ($this->setSharePayment)(
            $user,
            $house,
            $validated['bill_id'],
            $validated['membership_id'],
            $validated['paid'],
        );

        $bill = $house->bills()
            ->with('shares.membership.user')
            ->findOrFail($validated['bill_id']);

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
            'membership_id' => $schema->integer()->required()->description('Id da participação cujo pagamento será alterado.'),
            'paid' => $schema->boolean()->required()->description('true marca como pago; false desmarca.'),
        ];
    }
}
