<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Filesystem;

class StaticFilesDevelopmentServiceProvider extends StaticFilesServiceProvider
{
    public function boot(): void
    {
        if (is_gae_development()) {
            $this->map();
        }
    }
}
