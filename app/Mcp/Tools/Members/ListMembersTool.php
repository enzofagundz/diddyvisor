<?php

namespace App\Mcp\Tools\Members;

use App\Enums\MembershipRole;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use App\Models\Membership;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Lista os membros ativos de uma casa do usuário configurado. O id retornado é a participação usada nas contas e nos pagamentos.')]
class ListMembersTool extends DiddyVisorTool
{
    public function __construct(McpUserResolver $users)
    {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'house_id' => ['required', 'integer'],
        ]);

        $user = $this->user($request);
        $house = $this->house($validated['house_id']);

        Gate::forUser($user)->authorize('view', $house);

        $memberships = $house->memberships()
            ->with('user')
            ->whereNull('left_at')
            ->orderBy('id')
            ->get();

        $items = $memberships
            ->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'role' => $membership->role === MembershipRole::Admin ? 'admin' : 'member',
            ])
            ->all();

        return $this->ok(['items' => $items, 'total' => count($items), 'next_offset' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'house_id' => $schema->integer()->required()->description('Id da casa.'),
        ];
    }
}
