// Import the dependency directly. Vite will bundle it.
import StackTrace from 'stacktrace-js';

// --- Module Configuration & State ---
// All variables are now in the module's top-level scope.
let config = {
    endpointUrl: null,
    service: 'not-configured',
    version: 'not-configured',
    traceId: 'not-configured',
    context: {},
};

// --- Private Functions ---

/**
 * Handles the error, generates a stack trace, and sends the report.
 * @param {Error|string} error - The error object or message.
 */
function handleError(error) {
    StackTrace.fromError(error)
        .then(stackFrames => {
            const payload = formatPayload(error, stackFrames);
            sendReport(payload);
        })
        .catch(err => {
            console.error('Error while generating stacktrace:', err);
            // Fallback: send report with no stack trace
            const payload = formatPayload(error, []);
            sendReport(payload);
        });
}

/**
 * Formats the error data into the payload for the backend.
 * @param {Error|string} error - The error object or message.
 * @param {Array} stackFrames - The array of stack frames from StackTrace.js.
 * @returns {object} The formatted payload.
 */
function formatPayload(error, stackFrames) {
    return {
        message: error.message || String(error),
        serviceContext: {
            service: config.service,
            version: config.version,
        },
        traceId: config.traceId,
        stack_trace_frames: stackFrames.map(sf => ({
            function_name: sf.functionName,
            file_name: sf.fileName,
            line_number: sf.lineNumber,
            column_number: sf.columnNumber,
        })),
        context: {
            ...config.context,
            httpRequest: {
                url: window.location.href,
                userAgent: navigator.userAgent,
            },
        },
    };
}

/**
 * Sends the report to the configured backend endpoint.
 * @param {object} payload - The error report payload.
 */
function sendReport(payload) {
    if (!config.endpointUrl) {
        console.error('Error reporter: endpointUrl is not configured.');
        console.log('Error payload:', payload); // Log to console for debugging
        return;
    }

    if (navigator.sendBeacon) {
        const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        navigator.sendBeacon(config.endpointUrl, blob);
    } else {
        fetch(config.endpointUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true,
        }).catch(err => console.error('Error sending report:', err));
    }
}

// --- Public API ---

/**
 * Initializes the error reporter and attaches global handlers.
 * @param {object} userConfig - The configuration object.
 */
function init(userConfig) {
    config = { ...config, ...userConfig };

    if (typeof StackTrace === 'undefined') {
        console.error('StackTrace.js dependency is not available. Error reporting is disabled.');
        return;
    }

    window.onerror = (message, source, lineno, colno, error) => {
        handleError(error || message);
        return false;
    };

    window.addEventListener('unhandledrejection', event => {
        handleError(event.reason || 'Unhandled promise rejection');
    });
}

// --- EXPLICIT GLOBAL ASSIGNMENT ---
// Instead of relying on a default export and Vite's 'name' property in UMD mode,
// we explicitly assign our public API to the window object. This is a more direct
// and robust method to ensure the global variable is available after bundling.
window.errorReporter = {
    init,
};
