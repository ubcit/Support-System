<?php

namespace Tests\Unit;

use App\Support\EnsureTlsCaBundle;
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
}
