<?php
/**
 * Plugin Name: Future Drafts
 * Description: Drafts for your future self. Capture an experience now; finish writing about it later.
 * Version:     0.2.3
 * Author:      Anne McCarthy
 * License:     GPL-2.0-or-later
 * Text Domain: future-drafts
 * Requires PHP: 8.1
 * Requires at least: 6.5
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

add_action('plugins_loaded', static function (): void {
    (new \FutureDrafts\Plugin(__FILE__))->register();
});
