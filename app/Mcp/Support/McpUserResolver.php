<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolve o usuário dono dos dados acessados pelo MCP. No transporte HTTP a
 * identidade é o usuário autenticado (OAuth/Passport); no stdio local vale o
 * config('diddyvisor.mcp.user') — e-mail ou id numérico. Todas as tools operam
 * como esse usuário; nenhuma tool aceita user_id.
 */
final class McpUserResolver
{
    private ?User $resolved = null;

    private ?string $resolvedKey = null;

    public function resolve(?Authenticatable $user = null): User
    {
        if ($user instanceof User) {
            return $user;
        }

        $key = trim((string) config('diddyvisor.mcp.user'));

        if ($this->resolved !== null && $this->resolvedKey === $key) {
            return $this->resolved;
        }

        if ($key === '') {
            throw ToolException::configuration(
                'DIDDYVISOR_MCP_USER não está definido.',
                'Defina DIDDYVISOR_MCP_USER com o e-mail ou o id do usuário dono dos dados.',
            );
        }

        $user = ctype_digit($key)
            ? User::query()->whereKey((int) $key)->first()
            : User::query()->where('email', $key)->first();

        if ($user === null) {
            throw ToolException::configuration(
                'DIDDYVISOR_MCP_USER não corresponde a um usuário existente.',
            );
        }

        $this->resolvedKey = $key;

        return $this->resolved = $user;
    }

    public function forget(): void
    {
        $this->resolved = null;
        $this->resolvedKey = null;
    }
}
