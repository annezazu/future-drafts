<?php
declare(strict_types=1);

namespace FutureDrafts;

final class Plugin
{
    public function __construct(private readonly string $pluginFile)
    {
    }

    public function register(): void
    {
        // Wired up in subsequent commits:
        //   (new PostMeta())->register();
        //   (new Dashboard\Widget($this->pluginFile))->register();
        //   (new Rest\Controller())->register();
        //   (new Hooks\CleanupOnPublish())->register();
    }

    public function pluginFile(): string
    {
        return $this->pluginFile;
    }
}
