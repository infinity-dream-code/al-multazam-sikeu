/**
 * Session keep-alive + silent recovery for 419 / transient 503 / 500.
 * Tidak redirect ke login, tidak alert "sesi habis".
 */
(function () {
    'use strict';

    var KEEP_ALIVE_URL = document.querySelector('meta[name="keep-alive-url"]')?.getAttribute('content') || '';
    var INTERVAL_MS = 4 * 60 * 1000;
    var pingInFlight = null;
    var lastPingAt = 0;
    var activeUserRequests = 0;

    function csrfMeta() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function getCsrf() {
        var meta = csrfMeta();
        return meta ? meta.getAttribute('content') : '';
    }

    function setCsrf(token) {
        if (!token) return;
        var meta = csrfMeta();
        if (meta) meta.setAttribute('content', token);
        var inputs = document.querySelectorAll('input[name="_token"]');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].value = token;
        }
        if (window.jQuery) {
            try {
                window.jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
            } catch (e) { /* ignore */ }
        }
    }

    function refreshCsrf() {
        if (!KEEP_ALIVE_URL) {
            return Promise.resolve(false);
        }
        if (pingInFlight) {
            return pingInFlight;
        }

        pingInFlight = fetch(KEEP_ALIVE_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store'
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (data && data.csrf) {
                        setCsrf(data.csrf);
                        lastPingAt = Date.now();
                        return true;
                    }
                    return res.ok;
                }).catch(function () { return res.ok; });
            })
            .catch(function () { return false; })
            .then(function (ok) {
                pingInFlight = null;
                return ok;
            });

        return pingInFlight;
    }

    window.__refreshCsrfToken = refreshCsrf;

    function scheduleKeepAlive() {
        if (!KEEP_ALIVE_URL) return;

        setInterval(function () {
            if (document.hidden) return;
            if (pingInFlight) return;
            if (activeUserRequests > 0) return;
            if (Date.now() - lastPingAt < INTERVAL_MS - 1000) return;
            refreshCsrf();
        }, INTERVAL_MS);

        setTimeout(function () {
            if (!document.hidden && activeUserRequests === 0) refreshCsrf();
        }, 20000);
    }

    function withUpdatedCsrfHeaders(headers) {
        var token = getCsrf();
        if (!token) return headers;
        // Hanya X-CSRF-TOKEN (plain). Jangan set X-XSRF-TOKEN = plain token —
        // Laravel menganggap X-XSRF-TOKEN terenkripsi; salah → bisa 500.
        if (headers instanceof Headers) {
            headers.set('X-CSRF-TOKEN', token);
            headers.delete('X-XSRF-TOKEN');
            return headers;
        }
        var next = Object.assign({}, headers || {});
        next['X-CSRF-TOKEN'] = token;
        if (next['X-XSRF-TOKEN']) delete next['X-XSRF-TOKEN'];
        return next;
    }

    function shouldRetryStatus(status) {
        return status === 419 || status === 503 || status === 500;
    }

    function applyCsrfToBody(body) {
        var token = getCsrf();
        if (!token || !body) return body;
        if (body instanceof FormData) {
            if (body.has('_token')) body.set('_token', token);
            return body;
        }
        if (typeof body === 'string' && body.indexOf('_token=') !== -1) {
            return body.replace(/_token=[^&]*/, '_token=' + encodeURIComponent(token));
        }
        return body;
    }

    function takeCsrfFromResponse(response) {
        return response.clone().json().then(function (data) {
            if (data && data.csrf) {
                setCsrf(data.csrf);
                return true;
            }
            return false;
        }).catch(function () { return false; });
    }

    function patchFetch() {
        if (!window.fetch) return;
        var rawFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            init = init || {};
            var url = (typeof input === 'string') ? input : (input && input.url) || '';
            var isKeepAlive = KEEP_ALIVE_URL && url.indexOf(KEEP_ALIVE_URL) !== -1;

            if (!isKeepAlive) activeUserRequests++;

            var run = function (attemptInit, retried) {
                return rawFetch(input, attemptInit).then(function (response) {
                    if (retried || !shouldRetryStatus(response.status) || isKeepAlive) {
                        return response;
                    }
                    return takeCsrfFromResponse(response).then(function () {
                        return refreshCsrf();
                    }).then(function (ok) {
                        if (!ok && response.status !== 419) {
                            return response;
                        }
                        var retryInit = Object.assign({}, attemptInit, { _sessionRetried: true });
                        retryInit.headers = withUpdatedCsrfHeaders(attemptInit.headers);
                        retryInit.body = applyCsrfToBody(attemptInit.body);
                        return rawFetch(input, retryInit);
                    });
                });
            };

            return run(init, !!init._sessionRetried).finally(function () {
                if (!isKeepAlive) activeUserRequests = Math.max(0, activeUserRequests - 1);
            });
        };
    }

    function patchJquery() {
        var $ = window.jQuery;
        if (!$ || !$.ajax) return;

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': getCsrf() } });

        var rawAjax = $.ajax;
        $.ajax = function (url, options) {
            if (typeof url === 'object') {
                options = url;
                url = undefined;
            }
            options = $.extend(true, {}, options || {});
            var reqUrl = url || options.url || '';
            var isKeepAlive = KEEP_ALIVE_URL && String(reqUrl).indexOf(KEEP_ALIVE_URL) !== -1;

            if (options._sessionRetried) {
                return url !== undefined ? rawAjax.call($, url, options) : rawAjax.call($, options);
            }

            var userError = options.error;
            var userStatusCode = options.statusCode || {};

            options.statusCode = $.extend({}, userStatusCode, {
                419: function () {},
                503: function () {}
            });

            options.error = function (xhr, textStatus, errorThrown) {
                if (xhr && shouldRetryStatus(xhr.status)) {
                    return;
                }
                if (typeof userError === 'function') {
                    return userError.apply(this, arguments);
                }
            };

            if (!isKeepAlive) activeUserRequests++;

            var jqXHR = url !== undefined ? rawAjax.call($, url, options) : rawAjax.call($, options);

            jqXHR.always(function () {
                if (!isKeepAlive) activeUserRequests = Math.max(0, activeUserRequests - 1);
            });

            jqXHR.fail(function (xhr) {
                if (!xhr || !shouldRetryStatus(xhr.status) || options._sessionRetried) {
                    return;
                }
                refreshCsrf().then(function (ok) {
                    if (!ok && xhr.status !== 419) {
                        if (typeof userError === 'function') {
                            userError.call(options.context || jqXHR, xhr, 'error', xhr.statusText);
                        }
                        return;
                    }
                    var retry = $.extend(true, {}, options, { _sessionRetried: true });
                    retry.headers = $.extend({}, retry.headers || {}, { 'X-CSRF-TOKEN': getCsrf() });
                    if (typeof retry.data === 'string' && retry.data.indexOf('_token=') !== -1) {
                        retry.data = retry.data.replace(/_token=[^&]*/, '_token=' + encodeURIComponent(getCsrf()));
                    } else if (retry.data && typeof retry.data === 'object' && !(retry.data instanceof FormData) && retry.data._token !== undefined) {
                        retry.data._token = getCsrf();
                    } else if (retry.data instanceof FormData && retry.data.has('_token')) {
                        retry.data.set('_token', getCsrf());
                    }
                    retry.error = userError;
                    retry.statusCode = userStatusCode;
                    if (url !== undefined) {
                        rawAjax.call($, url, retry);
                    } else {
                        rawAjax.call($, retry);
                    }
                });
            });

            return jqXHR;
        };
    }

    patchFetch();
    if (window.jQuery) {
        patchJquery();
    } else {
        document.addEventListener('DOMContentLoaded', patchJquery);
    }
    scheduleKeepAlive();
})();
