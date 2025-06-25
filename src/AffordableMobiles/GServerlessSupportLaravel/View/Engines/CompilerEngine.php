<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Engines;

use AffordableMobiles\GServerlessSupportLaravel\View\Compilers\FakeCompiler;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine as LaravelCompilerEngine;

class CompilerEngine extends LaravelCompilerEngine
{
    /**
     * Create a new compiler engine instance.
     */
    public function __construct(CompilerInterface $compiler) // Ensure constructor matches parent
    {
        parent::__construct($compiler);
    }

    /**
     * Get the evaluated contents of the view at the given path.
     *
     * @param string $path
     * @param array  $data
     *
     * @return string
     */
    protected function evaluatePath($path, $data)
    {
        $compiler = $this->getCompiler();

        // When using pre-compiled views, we sometimes need to change the echo
        // format at runtime, like when rendering markdown mailables. This logic
        // checks if a custom echo format is active and replaces the standard
        // echo statements in the pre-compiled file with the custom format.
        if ($compiler instanceof FakeCompiler && $compiler->getEchoFormat() !== $compiler->getDefaultEchoFormat()) {
            $obLevel = ob_get_level();
            ob_start();

            $tempFileHandle = tmpfile();
            if (false === $tempFileHandle) {
                throw new \RuntimeException('Unable to create temporary file for Blade rendering.');
            }
            $tempFilePath = stream_get_meta_data($tempFileHandle)['uri'];

            try {
                $originalContents = $this->files->get($path);
                $customFormat     = $compiler->getEchoFormat();
                $defaultFormat    = $compiler->getDefaultEchoFormat();

                // Deconstruct the format strings into prefix and suffix by splitting on '%s'.
                // This is safer than assuming a simple `function(%s)` structure.
                $defaultPos = strpos($defaultFormat, '%s');
                if (false === $defaultPos) {
                    throw new \RuntimeException("Default echo format does not contain a '%s' placeholder.");
                }
                $defaultPrefix = substr($defaultFormat, 0, $defaultPos);
                $defaultSuffix = substr($defaultFormat, $defaultPos + 2);

                $customPos = strpos($customFormat, '%s');
                if (false === $customPos) {
                    throw new \RuntimeException("Custom echo format does not contain a '%s' placeholder.");
                }
                $customPrefix = substr($customFormat, 0, $customPos);
                $customSuffix = substr($customFormat, $customPos + 2);

                // This regex handles balanced parentheses to correctly find the boundaries of the echo statement.
                // It captures the `echo`, the prefix, the content, and the suffix separately.
                $pattern = \sprintf(
                    '/(echo\s+)(%s)((?:[^()]+|\((?3)\))*)(%s)/s',
                    preg_quote($defaultPrefix, '/'),
                    preg_quote($defaultSuffix, '/')
                );

                $newContents = preg_replace_callback($pattern, static function ($matches) use ($customPrefix, $customSuffix) {
                    // Reconstruct the echo statement with the new custom format parts.
                    // $matches[1] is "echo "
                    // $matches[2] is the default prefix (e.g., "e(")
                    // $matches[3] is the content inside the parentheses
                    // $matches[4] is the default suffix (e.g., ")")
                    return $matches[1].$customPrefix.$matches[3].$customSuffix;
                }, $originalContents);

                if (null === $newContents) {
                    throw new \RuntimeException('PCRE error during Blade echo format replacement: '.preg_last_error_msg());
                }

                fwrite($tempFileHandle, $newContents);
                fflush($tempFileHandle);

                // Now, require the temporary file with the modified contents.
                $this->files->getRequire($tempFilePath, $data);
            } catch (\Throwable $e) {
                // Let the parent class handle the exception. It will correctly wrap it
                // in a ViewException and use the overridden getMessage() method.
                parent::handleViewException($e, $obLevel);
            } finally {
                fclose($tempFileHandle); // This also deletes the file.
            }

            return ltrim(ob_get_clean());
        }

        return parent::evaluatePath($path, $data);
    }

    /**
     * Get the exception message for an exception.
     * Overrides the parent method to display the canonical view name.
     */
    protected function getMessage(\Throwable $e): string
    {
        // Get the last view path pushed onto the stack (our "fake" path)
        $viewPath = last($this->lastCompiled) ?: 'Unknown';

        // Extract the canonical name by removing the .blade.php suffix
        $canonicalName = $viewPath;
        if (Str::endsWith($viewPath, '.blade.php')) {
            $canonicalName = Str::beforeLast($viewPath, '.blade.php');
        }

        // Construct the message using the canonical name
        return $e->getMessage().' (View: '.$canonicalName.')';
    }
}
