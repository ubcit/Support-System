<?php

namespace Tests\Unit;

use App\Support\EnsureTlsCaBundle;
use Tests\TestCase;

class EnsureTlsCaBundleTest extends TestCase
{
    public function test_apply_sets_readable_system_or_env_bundle(): void
    {
        $applied = EnsureTlsCaBundle::apply();

        if ($applied === null) {
            $this->markTestSkipped('No CA bundle available on this machine.');
        }

        $this->assertFileIsReadable($applied);
        $this->assertSame($applied, ini_get('openssl.cafile') ?: ini_get('curl.cainfo'));
    }

    public function test_candidates_include_ubuntu_default(): void
    {
        $this->assertContains(
            '/etc/ssl/certs/ca-certificates.crt',
            EnsureTlsCaBundle::candidates()
        );
    }
}
