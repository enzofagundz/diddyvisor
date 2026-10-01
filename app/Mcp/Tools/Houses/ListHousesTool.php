<?php

namespace App\Mcp\Tools\Houses;

use App\Enums\MembershipRole;
use App\Mcp\Support\McpUserResolver;
use App\Mcp\Tools\DiddyVisorTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Lista as casas das quais o usuário configurado participa, com o papel dele em cada uma.')]
class ListHousesTool extends DiddyVisorTool
{
    public function __construct(McpUserResolver $users)
    {
        parent::__construct($users);
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $user = $this->user();

        $memberships = $user->memberships()
            ->with('house')
            ->whereNull('left_at')
            ->orderBy('id')
            ->get();

        $items = $memberships
            ->map(fn ($membership): array => [
                'id' => $membership->house->id,
                'name' => $membership->house->name,
                'role' => $membership->role === MembershipRole::Admin ? 'admin' : 'member',
            ])
            ->values()
            ->all();

        return $this->ok(['items' => $items, 'total' => count($items), 'next_offset' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
