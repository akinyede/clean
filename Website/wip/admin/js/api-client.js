// /admin/js/api-client.js
// Must be loaded first: <script src="js/api-client.js"></script>

(function (global) {
    'use strict';

    // --- DOM Utilities for Safety and Convenience ---
    var DomUtils = {
        // Essential for preventing XSS when injecting dynamic HTML
        escapeHtml: function (unsafe) {
            if (typeof unsafe === 'undefined' || unsafe === null) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        // Simple utility to show a temporary notification
        showNotification: function (message, type) {
            type = type || 'info';

            var notification = document.createElement('div');
            var baseClass = 'fixed top-4 right-4 p-4 rounded-lg z-[10001] shadow-lg transition-opacity duration-300 opacity-0 ';
            var clazz;

            if (type === 'error') {
                clazz = 'bg-red-500 text-white';
            } else if (type === 'success') {
                clazz = 'bg-green-500 text-white';
            } else {
                clazz = 'bg-blue-500 text-white';
            }

            notification.className = baseClass + clazz;
            notification.textContent = message;
            document.body.appendChild(notification);

            requestAnimationFrame(function () {
                notification.style.opacity = '1';
            });

            setTimeout(function () {
                notification.style.opacity = '0';
                notification.addEventListener('transitionend', function () {
                    notification.remove();
                });
            }, 5000);
        },

        showError: function (message) {
            DomUtils.showNotification(message, 'error');
        },

        showSuccess: function (message) {
            DomUtils.showNotification(message, 'success');
        }
    };

    // --- API Client with Centralized Error Handling and Loading Toggle ---
    var ApiClient = {
        // This is a placeholder linked to the dashboard state for UI control
        globalLoadingToggle: function (show) {
            var loader = document.getElementById('globalLoader');
            if (loader) {
                loader.style.display = show ? 'flex' : 'none';
            }
        },

        // Core request function
        request: function (url, options) {
            options = options || {};
            var headers = options.headers || {};

            ApiClient.globalLoadingToggle(true);

            // Build final options without spread
            var fetchOptions = {};
            var key;

            // Copy original options first
            for (key in options) {
                if (Object.prototype.hasOwnProperty.call(options, key)) {
                    fetchOptions[key] = options[key];
                }
            }

            // Merge headers
            var mergedHeaders = {
                'Content-Type': 'application/json'
            };
            for (key in headers) {
                if (Object.prototype.hasOwnProperty.call(headers, key)) {
                    mergedHeaders[key] = headers[key];
                }
            }
            fetchOptions.headers = mergedHeaders;

            return fetch(url, fetchOptions)
                .then(function (response) {
                    if (!response.ok) {
                        return response.text().then(function (errorBody) {
                            var errorMessage = 'HTTP ' + response.status;

                            try {
                                var jsonError = JSON.parse(errorBody);
                                errorMessage = jsonError.message || errorMessage;
                            } catch (e) {
                                // Not JSON, use text or default message
                                errorMessage = errorBody || errorMessage;
                            }

                            throw new Error(errorMessage);
                        });
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success === false) {
                        throw new Error(data.message || 'Request failed on server-side.');
                    }
                    return data;
                })
                .catch(function (error) {
                    console.error('API request failed: ' + url, error);
                    DomUtils.showError(error.message || 'An unknown API error occurred.');
                    throw error;
                })
                .finally(function () {
                    ApiClient.globalLoadingToggle(false);
                });
        },

        get: function (url, params) {
            params = params || {};
            var queryString = new URLSearchParams(params).toString();
            var fullUrl = queryString ? url + '?' + queryString : url;
            return ApiClient.request(fullUrl);
        },

        post: function (url, data) {
            data = data || {};
            return ApiClient.request(url, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        put: function (url, data) {
            data = data || {};
            return ApiClient.request(url, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        delete: function (url) {
            return ApiClient.request(url, { method: 'DELETE' });
        }
    };

    // Expose the consolidated interface globally
    global.AdminUtils = {
        ApiClient: ApiClient,
        DomUtils: DomUtils,
        showNotification: DomUtils.showNotification,
        showError: DomUtils.showError,
        showSuccess: DomUtils.showSuccess
    };

})(window);
