<?php

namespace A1comms\GaeSupportLaravel\Foundation;

use Illuminate\Foundation\Application as IlluminateApplication;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\StreamHandler;
use A1comms\GaeSupportLaravel\Storage\Optimizer;

/**
 * class Application
 *
 * @uses IlluminateApplication
 *
 * @package A1comms\GaeSupportLaravel\Foundation
 */
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
        $this->gaeBucketPath = null;

        // Load the 'realpath()' function replacement
        // for GAE storage buckets.
        require_once(__DIR__ . '/gae_realpath.php');

        $this->detectGae();

        if ( is_gae_std() ) {
            $this->configureMonologUsing(function ($monolog) {
                $monolog->pushHandler(new SyslogHandler('laravel'));
            });
        } else if ( is_gae_flex() ) {
            $this->configureMonologUsing(function ($monolog) {
                $logging = new LoggingClient();
                $monolog->pushHandler(new PsrHandler($logging->psrLogger('app', ['batchEnabled' => true])));
            });
        } else {
            $this->configureMonologUsing(function ($monolog) {
                $handler = new StreamHandler($this->storagePath('logs/lumen.log'));
                $handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));
                $monolog->pushHandler($handler);
            });
        }

        $this->replaceDefaultSymfonyLineDumpers();

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
        if ( ! is_gae() ) {
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
    public function storagePath($path = null)
    {
        if ($this->runningOnGae) {
            if (! is_null($this->gaeBucketPath)) {
                return $this->gaeBucketPath.($path ? '/'.$path : $path);
            }
            $this->gaeBucketPath = Optimizer::getTemporaryPath();
            if (! file_exists($this->gaeBucketPath)) {
                mkdir($this->gaeBucketPath, 0755, true);
                mkdir($this->gaeBucketPath.'/app', 0755, true);
                mkdir($this->gaeBucketPath.'/framework', 0755, true);
                mkdir($this->gaeBucketPath.'/framework/views', 0755, true);
            }
            return $this->gaeBucketPath.($path ? '/'.$path : $path);
        }
        return parent::storagePath().($path ? '/'.$path : $path);
    }
}
