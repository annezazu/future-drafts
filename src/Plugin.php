<?php
declare(strict_types=1);

namespace FutureDrafts;

use FutureDrafts\Dashboard\Widget;
use FutureDrafts\Hooks\CleanupOnPublish;
use FutureDrafts\Rest\Controller;

final class Plugin
{
    public function __construct(private readonly string $pluginFile)
    {
    }

    public function register(): void
    {
        add_action('init', [new PostMeta(), 'register']);
        (new Widget($this->pluginFile))->register();
        (new Controller())->register();
        (new CleanupOnPublish())->register();
    }

    public function pluginFile(): string
    {
        return $this->pluginFile;
    }
}
