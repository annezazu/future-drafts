<?php
declare(strict_types=1);

namespace FutureDrafts\Dashboard;

use FutureDrafts\Rest\Controller;

final class Widget
{
    private const WIDGET_ID = 'future_drafts_widget';
    private const HANDLE    = 'future-drafts-widget';

    public function __construct(private readonly string $pluginFile)
    {
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addWidget(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __('Future Drafts', 'future-drafts'),
            [$this, 'renderRoot']
        );
    }

    public function renderRoot(): void
    {
        echo '<div id="future-drafts-root"></div>';
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }

        $build = plugin_dir_path($this->pluginFile) . 'build/widget.asset.php';
        $asset = file_exists($build)
            ? require $build
            : ['dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'], 'version' => '0.1.0'];

        wp_enqueue_script(
            self::HANDLE,
            plugins_url('build/widget.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            self::HANDLE,
            plugins_url('build/widget.css', $this->pluginFile),
            ['wp-components'],
            $asset['version']
        );

        wp_localize_script(self::HANDLE, 'futureDrafts', [
            'restNamespace' => Controller::NAMESPACE,
            'today'         => wp_date('Y-m-d'),
            'subtitle'      => __('Drafts for your future self.', 'future-drafts'),
        ]);

        wp_set_script_translations(self::HANDLE, 'future-drafts');
    }
}
