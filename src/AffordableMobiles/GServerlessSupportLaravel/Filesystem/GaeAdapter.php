<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Filesystem;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;

/**
 * Class GaeAdapter.
 *
 * The class overrides the existing methods in order to:
 *
 * - remove exclusive locks(not supported by GAE) while writing files,
 *
 * - 'ensureDirectory()' replace a call to 'reapath()' functions with
 * a call to 'gae_realpath()' function, which is compatible with GCS buckets,
 *
 * - 'writeStream()' replace 'fopen()' mode from 'w+', which is not supported
 * on GCS buckets and replaces it with 'w', as for the specific function
 * both 'w+' and 'w' should work properly.
 *
 * - 'applyPathPrefix()' remove trailing directory separators, which prevent
 * listing of disk root directory on GAE. Originally Flysystem Local adapter
 * ends up with path 'gs://bucket/storage/app//' for disk root, then 'is_dir()'
 * is used to check that it is a folder path. The check fails due to the trailing
 * slash which is not supported by GCS and an empty directory listing is returned.
 * In order to make the check pass the path has to be 'gs://bucket/storage/app/'.
 */
class GaeAdapter extends Local
{
    public function __construct($root)
    {
        parent::__construct($root, 0, self::DISALLOW_LINKS);
    }

    public function writeStream($path, $resource, Config $config)
    {
        $location = $this->applyPathPrefix($path);
        $this->ensureDirectory(\dirname($location));

        if (!$stream = fopen($location, 'w')) {
            return false;
        }

        while (!feof($resource)) {
            fwrite($stream, fread($resource, 1024), 1024);
        }

        if (!fclose($stream)) {
            return false;
        }

        if ($visibility = $config->get('visibility')) {
            $this->setVisibility($path, $visibility);
        }

        return compact('path', 'visibility');
    }

    public function applyPathPrefix($path)
    {
        $prefixedPath = parent::applyPathPrefix($path);

        return rtrim($prefixedPath, \DIRECTORY_SEPARATOR);
    }

    protected function ensureDirectory($root)
    {
        if (false === is_dir($root)) {
            mkdir($root, 0o755, true);
        }

        return gae_realpath($root);
    }
}
