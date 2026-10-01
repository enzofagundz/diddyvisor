<?php

use App\Notifications\HouseInvitation;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Psr\Http\Client\ClientInterface;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

test('invitation is sent through resend without contacting external api', function () {
    config(['mail.default' => 'resend', 'mail.from.address' => 'contas@example.com', 'mail.from.name' => 'DiddyVisor']);
    $request = null;
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('sendRequest')->once()->andReturnUsing(function ($sentRequest) use (&$request) {
        $request = $sentRequest;

        return new Response(200, ['Content-Type' => 'application/json'], '{"id":"mail-test-id"}');
    });
    $client = new Client(new HttpTransporter($http, BaseUri::from('api.resend.com'), Headers::withAuthorization(ApiKey::from('test-key-do-not-send'))));
    Mail::extend('resend', fn () => new ResendTransport($client));

    Notification::route('mail', 'morador@example.com')->notify(new HouseInvitation('Casa compartilhada', 'http://diddyvisor.test/convites/1'));

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.resend.com/emails');
    $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['from'])->toBe('"DiddyVisor" <contas@example.com>')
        ->and($payload['to'])->toBe(['morador@example.com'])
        ->and($payload['subject'])->toBe('Convite para Casa compartilhada · DiddyVisor')
        ->and($payload['html'])->toContain('Ver convite');
});

test('resend mailer uses native laravel transport', function () {
    config(['services.resend.key' => 'test-key-do-not-send']);

    expect(Mail::mailer('resend')->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class);
});
