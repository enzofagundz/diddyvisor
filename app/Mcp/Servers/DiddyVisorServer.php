<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Bills\CreateBillTool;
use App\Mcp\Tools\Bills\DeleteBillTool;
use App\Mcp\Tools\Bills\GetBillTool;
use App\Mcp\Tools\Bills\GetMonthlySummaryTool;
use App\Mcp\Tools\Bills\ListBillsTool;
use App\Mcp\Tools\Bills\SetSharePaymentTool;
use App\Mcp\Tools\Houses\ListHousesTool;
use App\Mcp\Tools\Members\ListMembersTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('DiddyVisor')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
    Servidor MCP do DiddyVisor, um sistema de divisão de contas domésticas.

    Regras de uso:
    - A identidade é fixa (variável DIDDYVISOR_MCP_USER): todas as tools operam o usuário configurado e nunca aceitam user_id.
    - Valores monetários são strings no formato pt-BR, como "1234,56".
    - Operações de escrita exigem que o usuário seja administrador da casa; cada membro só marca o próprio pagamento.
    - Contas com pagamento marcado não podem ser alteradas na divisão nem excluídas.
    - Operações destrutivas exigem o parâmetro confirm=true.
    MARKDOWN)]
final class DiddyVisorServer extends Server
{
    protected array $tools = [
        ListHousesTool::class,
        ListMembersTool::class,
        ListBillsTool::class,
        GetBillTool::class,
        GetMonthlySummaryTool::class,
        CreateBillTool::class,
        SetSharePaymentTool::class,
        DeleteBillTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
