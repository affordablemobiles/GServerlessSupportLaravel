<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Exceptions\Ignition;

use AffordableMobiles\GServerlessSupportLaravel\View\Exceptions\BladeMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewException;
use Spatie\ErrorSolutions\Contracts\ProvidesSolution;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Spatie\LaravelIgnition\Exceptions\ViewExceptionWithSolution;

class ViewExceptionMapper
{
    public function __construct(
        protected BladeMapper $mapper,
    ) {}

    public function map(ViewException $viewException): IgnitionViewException
    {
        $baseException = $this->getRealException($viewException);

        if ($baseException instanceof IgnitionViewException) {
            return $baseException;
        }

        $exception = $this->createException($baseException);

        if ($baseException instanceof ProvidesSolution) {
            // @var ViewExceptionWithSolution $exception
            $exception->setSolution($baseException->getSolution());
        }

        $this->modifyViewsInTrace($exception);

        $exception->setView(
            $exception->getFile(),
        );
        $exception->setViewData($this->getViewData($exception));

        return $exception;
    }

    protected function createException(\Throwable $baseException): IgnitionViewException
    {
        $viewExceptionClass = $baseException instanceof ProvidesSolution
            ? ViewExceptionWithSolution::class
            : IgnitionViewException::class;

        $viewFile = $this->mapper->findCompiledView($baseException->getFile());
        $file     = $viewFile ?? $baseException->getFile();
        $line     = $viewFile ? $this->mapper->detectLineNumber($file, $baseException->getLine()) : $baseException->getLine();

        return new $viewExceptionClass(
            $baseException->getMessage(),
            0,
            1,
            $file,
            $line,
            $baseException
        );
    }

    protected function modifyViewsInTrace(IgnitionViewException $exception): void
    {
        $viewIndex = null;

        $trace = Collection::make($exception->getPrevious()->getTrace())
            ->map(function ($frame) {
                if ($originalPath = $this->mapper->findCompiledView((string) Arr::get($frame, 'file', ''))) {
                    $frame['file'] = $originalPath;
                    $frame['line'] = $this->mapper->detectLineNumber($frame['file'], $frame['line']);
                }

                return $frame;
            })->toArray()
        ;

        $traceProperty = new \ReflectionProperty('Exception', 'trace');
        $traceProperty->setAccessible(true);
        $traceProperty->setValue($exception, $trace);
    }

    /**
     * Look at the previous exceptions to find the original exception.
     * This is usually the first Exception that is not a ViewException.
     */
    protected function getRealException(\Throwable $exception): \Throwable
    {
        $rootException = $exception->getPrevious() ?? $exception;

        while ($rootException instanceof ViewException && $rootException->getPrevious()) {
            $rootException = $rootException->getPrevious();
        }

        return $rootException;
    }

    protected function getViewData(\Throwable $exception): array
    {
        foreach ($exception->getTrace() as $frame) {
            if (PhpEngine::class === Arr::get($frame, 'class')) {
                $data = Arr::get($frame, 'args.1', []);

                return $this->filterViewData($data);
            }
        }

        return [];
    }

    protected function filterViewData(array $data): array
    {
        // By default, Laravel views get two data keys:
        // __env and app. We try to filter them out.
        return array_filter($data, static function ($value, $key) {
            if ('app' === $key) {
                return !$value instanceof Application;
            }

            return '__env' !== $key;
        }, ARRAY_FILTER_USE_BOTH);
    }
}
