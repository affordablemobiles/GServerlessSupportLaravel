@if(config('js-error-reporter.enabled'))

    @php
        // The service provider is responsible for reading the manifest.json and sharing
        // the correct, versioned path for the 'error-reporter' entry point.
        $assetPath = View::shared('errorReporterAssetPath', '');
        if (!empty($assetPath)) {
            if (function_exists('error_reporting_queue_preload_header')) {
                error_reporting_queue_preload_header($assetPath, 'script');
            }
        }
    @endphp

    @if(!empty($assetPath))
        {{-- This tag hints to the browser to start fetching the critical script with high priority --}}
        <link rel="preload" href="{{ $assetPath }}" as="script">

        {{-- This is the actual script tag that will execute the code once downloaded --}}
        <script src="{{ $assetPath }}"></script>

        <script>
            if (typeof window.errorReporter !== 'undefined') {
                window.errorReporter.init({
                    endpointUrl:    '{{ route('js-error-reporter.report') }}',
                    service:        '{{ g_service() }}',
                    version:        '{{ g_version() }}',
                    traceId:        '{{ g_serverless_short_trace_id() }}',
                });
            }
        </script>
    @endif
@endif