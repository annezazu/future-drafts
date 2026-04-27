<?php
declare(strict_types=1);

namespace FutureDrafts\Hooks;

use FutureDrafts\PostMeta;
use WP_Post;

final class CleanupOnPublish
{
    public function register(): void
    {
        add_action('transition_post_status', [$this, 'maybeCleanup'], 10, 3);
    }

    public function maybeCleanup(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($post->post_type !== 'post') {
            return;
        }
        if (!in_array($newStatus, ['publish', 'trash'], true)) {
            return;
        }
        delete_post_meta($post->ID, PostMeta::KEY);
    }
}
