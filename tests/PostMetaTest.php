<?php
declare(strict_types=1);

namespace FutureDrafts\Tests;

use FutureDrafts\PostMeta;
use WP_UnitTestCase;

final class PostMetaTest extends WP_UnitTestCase
{
    public function test_sanitize_accepts_valid_iso_date(): void
    {
        $this->assertSame('2026-06-14', PostMeta::sanitize('2026-06-14'));
    }

    public function test_sanitize_trims_whitespace(): void
    {
        $this->assertSame('2026-06-14', PostMeta::sanitize('  2026-06-14  '));
    }

    public function test_sanitize_rejects_malformed_strings(): void
    {
        $this->assertSame('', PostMeta::sanitize('not-a-date'));
        $this->assertSame('', PostMeta::sanitize('2026/06/14'));
        $this->assertSame('', PostMeta::sanitize('06-14-2026'));
        $this->assertSame('', PostMeta::sanitize(''));
    }

    public function test_sanitize_rejects_impossible_dates(): void
    {
        $this->assertSame('', PostMeta::sanitize('2026-02-30'));
        $this->assertSame('', PostMeta::sanitize('2026-13-01'));
    }

    public function test_sanitize_rejects_non_strings(): void
    {
        $this->assertSame('', PostMeta::sanitize(null));
        $this->assertSame('', PostMeta::sanitize(123));
        $this->assertSame('', PostMeta::sanitize(['2026-06-14']));
    }
}
