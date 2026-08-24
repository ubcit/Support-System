<?php

namespace App\Support;

/**
 * PHP builds (Ondřej PPA, some Herd CLI setups) often ship with an empty
 * openssl.cafile. SMTP STARTTLS then fails with:
 *   error:0A000086:SSL routines::certificate verify failed
 * against providers like Hostinger even though the cert chain is valid.
 */
class EnsureTlsCaBundle
{
    /**
     * Point OpenSSL / cURL at a system or env CA bundle when none is configured.
     */
    public static function apply(?string $explicit = null): ?string
    {
        $current = ini_get('openssl.cafile') ?: ini_get('curl.cainfo') ?: null;
        if (is_string($current) && $current !== '' && is_readable($current)) {
            return $current;
        }

        foreach (self::candidates($explicit) as $path) {
            if (! is_readable($path)) {
                continue;
            }

            ini_set('openssl.cafile', $path);
            ini_set('curl.cainfo', $path);

            return $path;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidates(?string $explicit = null): array
    {
        $paths = array_filter([
            $explicit,
            env('MAIL_CAFILE'),
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/opt/homebrew/etc/openssl@3/cert.pem',
            '/usr/local/etc/openssl@3/cert.pem',
            getenv('HOME')
                ? rtrim((string) getenv('HOME'), '/').'/Library/Application Support/Herd/config/php/cacert.pem'
                : null,
        ]);

        return array_values(array_unique($paths));
    }
}
