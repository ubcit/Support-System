<?php

namespace Tests\Unit;

use App\Support\EnsureTlsCaBundle;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

class EnsureTlsCaBundleTest extends TestCase
{
    public function test_bundled_mozilla_ca_is_readable(): void
    {
        $path = EnsureTlsCaBundle::bundledPath();

        $this->assertFileExists($path);
        $this->assertFileIsReadable($path);
        $this->assertStringContainsString('BEGIN CERTIFICATE', (string) file_get_contents($path));
    }

    public function test_apply_resolves_a_readable_bundle(): void
    {
        $applied = EnsureTlsCaBundle::apply();

        $this->assertNotNull($applied);
        $this->assertFileIsReadable($applied);
    }

    public function test_candidates_include_bundled_cert(): void
    {
        $this->assertContains(
            EnsureTlsCaBundle::bundledPath(),
            EnsureTlsCaBundle::candidates()
        );
    }

    public function test_tls12_plus_crypto_method_includes_tls12(): void
    {
        $method = EnsureTlsCaBundle::tls12PlusCryptoMethod();

        $this->assertSame(
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            $method & STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        );
    }

    public function test_apply_to_mailer_sets_cafile_and_tls12_crypto_method(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => null,
                'host' => '127.0.0.1',
                'port' => 2525,
                'username' => null,
                'password' => null,
                'timeout' => null,
            ],
        ]);

        Mail::purge();

        $mailer = Mail::mailer('smtp');
        EnsureTlsCaBundle::applyToMailer($mailer);

        $transport = $mailer->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);

        $stream = $transport->getStream();
        $this->assertInstanceOf(SocketStream::class, $stream);

        $ssl = $stream->getStreamOptions()['ssl'] ?? [];
        $ca = EnsureTlsCaBundle::apply();

        $this->assertSame($ca, $ssl['cafile'] ?? null);
        $this->assertTrue($ssl['verify_peer'] ?? false);
        $this->assertTrue($ssl['verify_peer_name'] ?? false);
        $this->assertFalse($ssl['allow_self_signed'] ?? true);
        $this->assertSame(EnsureTlsCaBundle::tls12PlusCryptoMethod(), $ssl['crypto_method'] ?? 0);
    }
}
