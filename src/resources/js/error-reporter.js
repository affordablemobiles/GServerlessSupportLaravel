// Import the dependency directly. Vite will bundle it.
import StackTrace from 'stacktrace-js';

/**
 * Checks if a function name looks like a valid identifier, not a parser artifact.
 * A valid name should not contain characters like parentheses, spaces, etc.
 * @param {string | null | undefined} name The function name to check.
 * @returns {boolean}
 */
function isValidFunctionName(name) {
    if (!name || name === '<unknown>') {
        return false;
    }
    // This regex allows for typical JS identifiers, including object properties (e.g., object.method).
    // It explicitly disallows characters commonly found in parser artifacts like ')' or ' '.
    return /^[a-zA-Z0-9_$.<>]+$/.test(name);
}


const errorReporter = {
    config: {
        endpointUrl: null,
        service: 'not-configured',
        version: 'not-configured',
        traceId: 'not-configured',
        context: {},
    },

    init(userConfig) {
        this.config = { ...this.config, ...userConfig };

        if (typeof StackTrace === 'undefined') {
            console.error('StackTrace.js is not available. Error reporting is disabled.');
            return;
        }

        window.onerror = (message, source, lineno, colno, error) => {
            this.handleError(error || message);
            return false;
        };

        window.addEventListener('unhandledrejection', event => {
            this.handleError(event.reason || 'Unhandled promise rejection');
        });
    },

    handleError(error) {
        StackTrace.fromError(error)
            .then(stackFrames => {
                const payload = this.formatPayload(error, stackFrames);
                this.sendReport(payload);
            })
            .catch(err => {
                console.error('Error while generating stacktrace:', err);
                const payload = this.formatPayload(error, []);
                this.sendReport(payload);
            });
    },

    formatPayload(error, stackFrames) {
        let errorMessage = String(error);
        // If the error is a proper Error object, prefix the message with its type (e.g., "ReferenceError: ...").
        if (error instanceof Error && error.name && error.message) {
            errorMessage = `${error.name}: ${error.message}`;
        }

        return {
            message: errorMessage,
            serviceContext: {
                service: this.config.service,
                version: this.config.version,
            },
            traceId: this.config.traceId,
            stack_trace_frames: stackFrames.map(sf => ({
                function_name: isValidFunctionName(sf.functionName) ? sf.functionName : null,
                file_name: sf.fileName,
                line_number: sf.lineNumber,
                column_number: sf.columnNumber,
            })),
            context: {
                ...this.config.context,
                url: window.location.href,
                userAgent: navigator.userAgent,
            },
        };
    },

    sendReport(payload) {
        if (!this.config.endpointUrl) {
            console.error('Error reporter: endpointUrl is not configured.');
            console.log('Error payload:', payload);
            return;
        }

        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(this.config.endpointUrl, blob);
        } else {
            fetch(this.config.endpointUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
                keepalive: true,
            }).catch(err => console.error('Error sending report:', err));
        }
    }
};

// Directly assign to window to ensure it's globally available for UMD builds.
window.errorReporter = errorReporter;