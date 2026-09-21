import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Take the CSRF token from the XSRF-TOKEN cookie rather than the <meta> tag.
 *
 * The meta tag is only read once, when this module loads. Logging in
 * regenerates the session and with it the CSRF token, but Inertia swaps pages
 * without reloading document.head, so the tag keeps serving the pre-login
 * token for the rest of the visit. Every axios POST after that failed with
 * 419 — including the manager's Accept button.
 *
 * Laravel refreshes the XSRF-TOKEN cookie on every response, so reading it per
 * request is always current. Note that this only works with X-CSRF-TOKEN left
 * unset: Laravel reads that header first and never looks at the cookie header
 * when it is present, stale or not.
 */
window.axios.defaults.withXSRFToken = true;
