<?php
declare(strict_types=1);

namespace FutureDrafts\Tests\Hooks;

use FutureDrafts\Hooks\CleanupOnPublish;
use FutureDrafts\PostMeta;
use WP_UnitTestCase;

final class CleanupOnPublishTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new CleanupOnPublish())->register();
    }

    public function test_publishing_a_future_draft_deletes_the_meta(): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'draft',
            'post_type'   => 'post',
        ]);
        update_post_meta($postId, PostMeta::KEY, '2026-06-14');

        wp_publish_post($postId);

        $this->assertSame('', get_post_meta($postId, PostMeta::KEY, true));
    }

    public function test_trashing_a_future_draft_deletes_the_meta(): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'draft',
            'post_type'   => 'post',
        ]);
        update_post_meta($postId, PostMeta::KEY, '2026-06-14');

        wp_trash_post($postId);

        $this->assertSame('', get_post_meta($postId, PostMeta::KEY, true));
    }

    public function test_other_status_transitions_preserve_the_meta(): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'draft',
            'post_type'   => 'post',
        ]);
        update_post_meta($postId, PostMeta::KEY, '2026-06-14');

        wp_update_post(['ID' => $postId, 'post_status' => 'pending']);

        $this->assertSame('2026-06-14', get_post_meta($postId, PostMeta::KEY, true));
    }

    public function test_posts_without_the_meta_are_unaffected(): void
    {
        $postId = self::factory()->post->create(['post_status' => 'draft']);

        wp_publish_post($postId);

        $this->assertSame('publish', get_post_status($postId));
    }
}
