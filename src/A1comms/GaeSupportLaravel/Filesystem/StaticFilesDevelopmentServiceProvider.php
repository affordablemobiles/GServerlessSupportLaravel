<?php

namespace A1comms\GaeSupportLaravel\Filesystem;

class StaticFilesDevelopmentServiceProvider extends StaticFilesServiceProvider
{
    public function boot()
    {
        if (is_gae_development()) {
            $this->map();
        }
    }
}
