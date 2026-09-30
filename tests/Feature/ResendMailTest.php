<?php

namespace Tests\Feature;

use App\Notifications\HouseInvitation;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Psr\Http\Client\ClientInterface;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    public function test_invitation_is_sent_through_resend_without_contacting_external_api(): void
    {
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

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.resend.com/emails', (string) $request->getUri());
        $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('"DiddyVisor" <contas@example.com>', $payload['from']);
        $this->assertSame(['morador@example.com'], $payload['to']);
        $this->assertSame('Convite para Casa compartilhada · DiddyVisor', $payload['subject']);
        $this->assertStringContainsString('Ver convite', $payload['html']);
    }

    public function test_resend_mailer_uses_native_laravel_transport(): void
    {
        config(['services.resend.key' => 'test-key-do-not-send']);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());
    }
}
