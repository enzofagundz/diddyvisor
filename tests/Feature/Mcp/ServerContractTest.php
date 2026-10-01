<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\DiddyVisorServer;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

class ServerContractTest extends TestCase
{
    public function test_exposes_the_agreed_tools_with_serializable_schemas(): void
    {
        $tools = (new DiddyVisorServer(new FakeTransporter))->createContext()->tools();

        $this->assertSame([
            'list_houses',
            'list_members',
            'list_bills',
            'get_bill',
            'get_monthly_summary',
            'create_bill',
            'set_share_payment',
            'delete_bill',
        ], $tools->map(fn (Tool $tool): string => $tool->name())->all());

        foreach ($tools as $tool) {
            $this->assertSame('object', $tool->toArray()['inputSchema']['type']);
        }
    }
}
