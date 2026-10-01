<?php

use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\CreateBillTool;
use App\Mcp\Tools\Bills\GetBillTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

test('creates a bill with an equal split between participants', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $house = House::create(['name' => 'Casa A']);
    $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Member]);

    config(['diddyvisor.mcp.user' => $user->email]);

    $response = DiddyVisorServer::tool(CreateBillTool::class, [
        'house_id' => $house->id,
        'month' => '2026-10',
        'name' => 'Aluguel',
        'due_date' => '2026-10-20',
        'total' => '100,00',
        'participants' => [$adminMembership->id, $memberMembership->id],
    ]);

    $response->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('ok', true)
        ->where('data.bill.name', 'Aluguel')
        ->where('data.bill.month', '2026-10')
        ->where('data.bill.due_date', '2026-10-20')
        ->where('data.bill.total', '100,00')
        ->where('data.bill.status', 'pending')
        ->where('data.bill.shares.0.amount', '50,00')
        ->where('data.bill.shares.1.amount', '50,00')
        ->etc());

    $billId = $house->bills()->sole()->id;

    DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $billId])
        ->assertOk()
        ->assertSee('"name":"Aluguel"');
});

test('rounds cents so an equal split adds up to the total', function () {
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa A']);
    $memberships = collect([$user, User::factory()->create(), User::factory()->create()])
        ->map(fn (User $member, int $index) => $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => $index === 0 ? MembershipRole::Admin : MembershipRole::Member]));

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(CreateBillTool::class, [
        'house_id' => $house->id,
        'month' => '2026-10',
        'name' => 'Aluguel',
        'due_date' => '2026-10-20',
        'total' => '100,00',
        'participants' => $memberships->pluck('id')->all(),
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('data.bill.shares.0.amount', '33,34')
            ->where('data.bill.shares.1.amount', '33,33')
            ->where('data.bill.shares.2.amount', '33,33')
            ->etc());
});

test('rejects shares that do not add up to the total', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $house = House::create(['name' => 'Casa A']);
    $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
    $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => $member->name, 'role' => MembershipRole::Member]);

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(CreateBillTool::class, [
        'house_id' => $house->id,
        'month' => '2026-10',
        'name' => 'Aluguel',
        'due_date' => '2026-10-20',
        'total' => '100,00',
        'participants' => [$adminMembership->id, $memberMembership->id],
        'shares' => [
            ['membership_id' => $adminMembership->id, 'amount' => '40,00'],
            ['membership_id' => $memberMembership->id, 'amount' => '40,00'],
        ],
    ])
        ->assertHasErrors()
        ->assertSee('"code":"validation"')
        ->assertSee('A soma das partes deve fechar o total.');

    expect($house->bills()->count())->toBe(0);
});

test('forbids a regular member from creating a bill', function () {
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa A']);
    $adminMembership = $house->memberships()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Chefe', 'role' => MembershipRole::Admin]);
    $memberMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Member]);

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(CreateBillTool::class, [
        'house_id' => $house->id,
        'month' => '2026-10',
        'name' => 'Aluguel',
        'due_date' => '2026-10-20',
        'total' => '100,00',
        'participants' => [$adminMembership->id, $memberMembership->id],
    ])
        ->assertHasErrors()
        ->assertSee('"code":"forbidden"');

    expect($house->bills()->count())->toBe(0);
});
