<?php

namespace A1comms\GaeFlexSupportL5\Foundation;

use Illuminate\Foundation\Application as IlluminateApplication;
use A1comms\GaeFlexSupportL5\Storage\Optimizer;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Google\Cloud\Core\Logger\AppEngineFlexHandler;
use A1comms\GaeFlexSupportL5\Logger\AppEngineFlexFormatter;

class Application extends IlluminateApplication
{
    /**
     * The GAE app ID.
     *
     * @var string
     */
    protected $appId;

    /**
     * The GAE app service / module.
     *
     * @var string
     */
    protected $appService;

    /**
     * The GAE app version.
     *
     * @var string
     */
    protected $appVersion;

    /**
     * 'true' if running on GAE.
     * @var boolean
     */
    protected $runningOnGae;


    /**
     * GAE storage optimizer
     */
    protected $optimizer = null;

    /**
     * Create a new GAE supported application instance.
     *
     * @param string $basePath
     */
    public function __construct($basePath = null)
    {
        $this->configureMonologUsing(function ($monolog) {
            $monolog->pushHandler($handler = new AppEngineFlexHandler());
            $handler->setFormatter(new AppEngineFlexFormatter());
        });

        $this->gaeBucketPath = null;

        // Load the 'realpath()' function replacement
        // for GAE storage buckets.
        require_once(__DIR__ . '/gae_realpath.php');

        $this->detectGae();

        if ($this->isRunningOnGae()) {
            $this->replaceDefaultSymfonyLineDumpers();
        }

        $this->optimizer = new Optimizer($basePath, $this->runningInConsole());
        $this->optimizer->bootstrap();

        parent::__construct($basePath);
    }


    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        $path = $this->optimizer->getCachedConfigPath();

        return $path ?: parent::getCachedConfigPath();
    }


    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath()
    {
        $path = $this->optimizer->getCachedRoutesPath();

        return $path ?: parent::getCachedRoutesPath();
    }

    /**
     * Get the path to the cached services.json file.
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        $path = $this->optimizer->getCachedServicesPath();

        if ($path) {
            return $path;
        }

        if ($this->isRunningOnGae()) {
            return $this->storagePath().'/framework/services.json';
        }

        return parent::getCachedServicesPath();
    }


    /**
     * Detect if the application is running on GAE.
     */
    protected function detectGae()
    {
        if (empty(gae_instance())) {
            $this->runningOnGae = false;
            $this->appId = null;
            $this->appService = null;
            $this->appVersion = null;

            return;
        }

        $this->runningOnGae = true;
        $this->appId = gae_project();
        $this->appService = gae_service();
        $this->appVersion = gae_version();
    }

    /**
     * Replaces the default output stream of Symfony's
     * CliDumper and HtmlDumper classes in order to
     * be able to run on Google App Engine.
     *
     * 'php://stdout' is used by CliDumper,
     * 'php://output' is used by HtmlDumper,
     * both are not supported on GAE.
     */
    protected function replaceDefaultSymfonyLineDumpers()
    {
        HtmlDumper::$defaultOutput =
        CliDumper::$defaultOutput =
            function ($line, $depth, $indentPad) {
                if (-1 !== $depth) {
                    echo str_repeat($indentPad, $depth).$line.PHP_EOL;
                }
            };
    }

    /**
     * Returns 'true' if running on GAE.
     *
     * @return bool
     */
    public function isRunningOnGae()
    {
        return $this->runningOnGae;
    }

    /**
     * Returns the GAE app ID.
     *
     * @return string
     */
    public function getGaeAppId()
    {
        return $this->appId;
    }

    /**
     * Returns the GAE app service / module.
     *
     * @return string
     */
    public function getGaeAppService()
    {
        return $this->appService;
    }

    /**
     * Returns the GAE app version.
     *
     * @return string
     */
    public function getGaeAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * Override the storage path
     *
     * @return string Storage path URL
     */
    public function storagePath()
    {
        if ($this->runningOnGae) {
            $bucket = '/tmp/laravel/storage';

            if (! file_exists($bucket)) {
                mkdir($bucket, 0755, true);
                mkdir($bucket.'/app', 0755, true);
                mkdir($bucket.'/framework', 0755, true);
                mkdir($bucket.'/framework/views', 0755, true);
            }

            return $bucket;
        }

        return parent::storagePath();
    }
}
