<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\View\Exceptions;

/**
 * Thrown when an attempt is made to compile a Blade view at runtime in an
 * environment where this has been explicitly disabled.
 */
class RuntimeCompilationNotSupportedException extends \RuntimeException
{
    // No additional logic is needed; the type itself is the differentiator.
}
