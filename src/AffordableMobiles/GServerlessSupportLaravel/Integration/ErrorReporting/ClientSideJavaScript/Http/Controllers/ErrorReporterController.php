<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\ErrorReporting\ClientSideJavaScript\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ErrorReporterController implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            static function (Request $request, \Closure $next) {
                $request->headers->set('Accept', 'application/json');

                return $next($request);
            },
            static function (Request $request, \Closure $next) {
                $authCallback = config('js-error-reporter.authentication');

                if (\is_callable($authCallback)) {
                    if (true !== $authCallback($request)) {
                        abort(403, 'Forbidden by authentication policy.');
                    }
                }

                return $next($request);
            },
        ];
    }

    /**
     * Receives a client-side error, formats it, and writes it to stderr.
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message'                            => 'required|string',
                'serviceContext'                     => 'required|array',
                'serviceContext.service'             => 'required|string',
                'serviceContext.version'             => 'required|string',
                'serviceContext.resourceType'        => 'prohibited',
                'traceId'                            => 'required|string|size:32',
                'stack_trace_frames'                 => 'required|array',
                'stack_trace_frames.*.function_name' => 'nullable|string',
                'stack_trace_frames.*.file_name'     => 'nullable|string',
                'stack_trace_frames.*.line_number'   => 'nullable|integer',
                'stack_trace_frames.*.column_number' => 'nullable|integer',
                'context'                            => 'nullable|array',
            ]);

            $traceId = $validated['traceId'];

            $stackTraceString = $this->formatStackTrace(
                $validated['message'],
                $validated['stack_trace_frames']
            );

            $topFrame       = $validated['stack_trace_frames'][0] ?? null;
            $reportLocation = [
                'filePath'     => $topFrame['file_name']     ?? 'unknown',
                'lineNumber'   => $topFrame['line_number']   ?? 0,
                'functionName' => $topFrame['function_name'] ?? 'anonymous',
            ];

            $userId = $this->getUserIdentifier($request);

            $logEntry = [
                'severity'       => 'ERROR',
                'message'        => $validated['message'],
                'stack_trace'    => $stackTraceString,
                'time'           => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
                '@type'          => 'type.googleapis.com/google.devtools.clouderrorreporting.v1beta1.ReportedErrorEvent',
                'serviceContext' => $validated['serviceContext'],
                'context'        => [
                    'httpRequest' => [
                        'method'             => 'CLIENT_SIDE_ERROR',
                        'url'                => $validated['context']['httpRequest']['url']       ?? 'unknown',
                        'userAgent'          => $validated['context']['httpRequest']['userAgent'] ?? 'unknown',
                        'referrer'           => $request->header('referer'),
                        'remoteIp'           => $request->ip(),
                    ],
                    'reportLocation' => $reportLocation,
                    'user'           => $userId,
                ],
                'logging.googleapis.com/trace' => 'projects/'.g_project().'/traces/'.$traceId,
            ];

            @file_put_contents('php://stderr', json_encode($logEntry)."\n");
        } catch (ValidationException $e) {
            Log::warning('Client-side error report failed validation', [
                'errors'  => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status'  => 'validation_failed',
                'details' => [
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => 'internal_server_error'], 500);
        }

        return response()->json(['status' => 'reported'], 202);
    }

    /**
     * Retrieves the user identifier based on the package configuration.
     */
    protected function getUserIdentifier(Request $request): ?string
    {
        $identifierConfig = config('js-error-reporter.user_identifier');

        if ($identifierConfig instanceof \Closure) {
            return $identifierConfig($request);
        }

        if (\is_array($identifierConfig)) {
            $source = $identifierConfig['source'] ?? null;
            $key    = $identifierConfig['key']    ?? null;

            if ($source && $key) {
                switch ($source) {
                    case 'cookie':
                        return $request->cookie($key);

                    case 'header':
                        return $request->header($key);

                    case 'session':
                        return $request->hasSession() ? $request->session()->get($key) : null;
                }
            }
        }

        return null;
    }

    /**
     * Formats an array of stack frames into a V8-style stack trace string.
     */
    private function formatStackTrace(string $message, array $stackFrames): string
    {
        $formattedLines = [$message];

        foreach ($stackFrames as $frame) {
            $functionName = $frame['function_name'] ?? 'anonymous';
            $fileName     = $frame['file_name']     ?? 'unknown.js';
            $lineNumber   = $frame['line_number']   ?? 0;
            $columnNumber = $frame['column_number'] ?? 0;

            $formattedLines[] = "    at {$functionName} ({$fileName}:{$lineNumber}:{$columnNumber})";
        }

        return implode("\n", $formattedLines);
    }
}
