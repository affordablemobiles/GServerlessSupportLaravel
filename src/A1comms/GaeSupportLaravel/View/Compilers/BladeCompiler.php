<?php

namespace A1comms\GaeSupportLaravel\View\Compilers;

use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * class BladeCompiler
 *
 * Provides a modified Blade compiler for better compatibility with cachefs.
 *
 * @package A1comms\GaeSupportLaravel\View\Compilers
 */
class BladeCompiler extends \Illuminate\View\Compilers\BladeCompiler implements CompilerInterface
{
    /**
     * Determine if the view at the given path is expired.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path)
    {
        // if not production envirionment
        // we want to load a new view on each page request
        if (!is_production()) {
            return true;
        }

        $compiled = $this->getCompiledPath($path);

        // On App Engine, we expect to be using the cachefs filesystem,
        // to provide a writable space for blade template cache.
        // Since it is backed by memcache and we can't rely on the cache
        // still being around between calls, we'll get the cache entirely here.
        // We'll then rely on the wrapper between cachefs and memcache to
        // store a copy of the cache locally, in memory during the request.
        // This will stop 500s if the memcache data no longer exists between
        // this check and us trying to render the view.
        try {
            $this->files->get($compiled);
        } catch (FileNotFoundException $e) {
            return true;
        }

        return false;
    }
}
