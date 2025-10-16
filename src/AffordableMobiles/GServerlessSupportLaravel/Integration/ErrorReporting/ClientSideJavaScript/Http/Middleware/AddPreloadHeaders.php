<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Http\Middleware;

use AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Services\PreloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddPreloadHeaders
{
    public function __construct(protected PreloadService $preloadService) {}

    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request): (Response) $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        $links = $this->preloadService->getLinks();

        if (!empty($links)) {
            // Append the existing Link header if it's already set
            $existing   = $response->headers->get('Link', '');
            $newLinks   = implode(', ', $links);
            $finalLinks = $existing ? "{$existing}, {$newLinks}" : $newLinks;
            $response->headers->set('Link', $finalLinks);
        }

        $this->preloadService->clear();

        return $response;
    }
}
