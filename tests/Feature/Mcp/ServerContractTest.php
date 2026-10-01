<?php

use App\Mcp\Servers\DiddyVisorServer;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

test('exposes the agreed tools with serializable schemas', function () {
    $tools = (new DiddyVisorServer(new FakeTransporter))->createContext()->tools();

    expect($tools->map(fn (Tool $tool): string => $tool->name())->all())->toBe([
        'list_houses',
        'list_members',
        'list_bills',
        'get_bill',
        'get_monthly_summary',
        'create_bill',
        'set_share_payment',
        'delete_bill',
    ]);

    foreach ($tools as $tool) {
        expect($tool->toArray()['inputSchema']['type'])->toBe('object');
    }
});
