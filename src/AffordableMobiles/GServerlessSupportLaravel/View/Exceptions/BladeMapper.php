<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Exceptions;

use AffordableMobiles\GServerlessSupportLaravel\View\ViewServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper as LaravelBladeMapper;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider as LaravelViewServiceProvider;

class BladeMapper extends LaravelBladeMapper
{
    /**
     * The view factory instance.
     *
     * @var \AffordableMobiles\GServerlessSupportLaravel\View\FileViewFinder
     */
    protected $finder;

    /**
     * The view factory instance.
     *
     * @var FileViewFinder
     */
    protected $realFinder;

    /**
     * Create a new Blade mapper instance.
     */
    public function __construct(Application $app)
    {
        $this->finder = resolve(ViewServiceProvider::GSERVERLESS_VIEW_FINDER_KEY);
        (new LaravelViewServiceProvider($app))->register();
        $this->realFinder    = $app['view.finder'];
        $this->factory       = resolve(Factory::class);
        $this->bladeCompiler = resolve(BladeCompiler::class);

        foreach ($this->finder->getHints() as $namespace => $hints) {
            $this->realFinder->replaceNamespace($namespace, $hints);
        }
    }

    /**
     * Find the compiled view file for the given compiled path.
     */
    public function findCompiledView(string $compiledPath): ?string
    {
        $canonicalName = $this->finder->reverseFind(
            basename(
                realpath($compiledPath),
            ),
        ) ?? null;

        if (null === $canonicalName) {
            return null;
        }

        return $this->realFinder->find($canonicalName) ?? null;
    }

    /**
     * Detect the line number in the original blade file.
     *
     * @return int
     */
    public function detectLineNumber(string $filename, int $compiledLineNumber)
    {
        return parent::detectLineNumber($filename, $compiledLineNumber);
    }
}
