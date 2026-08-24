<?php

namespace App\Support;

use Illuminate\Mail\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * PHP on shared/cPanel hosts (and some FPM builds) often has an empty
 * openssl.cafile. SMTP STARTTLS then fails with:
 *   error:0A000086:SSL routines::certificate verify failed
 *
 * We ship a Mozilla CA bundle and also inject it into the Symfony SMTP
 * stream options — ini_set alone is not enough on many hosts.
 */
class EnsureTlsCaBundle
{
    /**
     * Resolve a readable CA bundle and set PHP ini defaults.
     */
    public static function apply(?string $explicit = null): ?string
    {
        $ca = self::resolve($explicit);

        if ($ca === null) {
            return null;
        }

        $current = ini_get('openssl.cafile') ?: '';
        if ($current === '' || ! is_readable($current)) {
            ini_set('openssl.cafile', $ca);
            ini_set('curl.cainfo', $ca);
        }

        return $ca;
    }

    /**
     * Force the SMTP transport to verify against a known CA file.
     */
    public static function applyToMailer(Mailer $mailer): void
    {
        $ca = self::apply();
        if ($ca === null || ! method_exists($mailer, 'getSymfonyTransport')) {
            return;
        }

        $transport = $mailer->getSymfonyTransport();
        if (! $transport instanceof EsmtpTransport) {
            return;
        }

        $stream = $transport->getStream();
        if (! $stream instanceof SocketStream) {
            return;
        }

        $options = $stream->getStreamOptions();
        $options['ssl'] = array_merge($options['ssl'] ?? [], [
            'cafile' => $ca,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]);
        $stream->setStreamOptions($options);
    }

    public static function resolve(?string $explicit = null): ?string
    {
        foreach (self::candidates($explicit) as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function bundledPath(): string
    {
        return resource_path('certs/cacert.pem');
    }

    /**
     * @return list<string>
     */
    public static function candidates(?string $explicit = null): array
    {
        $paths = array_filter([
            $explicit,
            env('MAIL_CAFILE'),
            self::bundledPath(),
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/opt/cpanel/ea-openssl11/cert.pem',
            '/opt/cpanel/ea-openssl/cert.pem',
            '/opt/homebrew/etc/openssl@3/cert.pem',
            '/usr/local/etc/openssl@3/cert.pem',
            getenv('HOME')
                ? rtrim((string) getenv('HOME'), '/').'/Library/Application Support/Herd/config/php/cacert.pem'
                : null,
        ]);

        return array_values(array_unique($paths));
    }
}
