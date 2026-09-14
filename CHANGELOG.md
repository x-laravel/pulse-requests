# Changelog

All notable changes to `x-laravel/pulse-requests` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 - 2026-09-14

Initial release.

### Added

- Recorder counting every handled request by HTTP status class: `informational`, `successful`, `redirection`, `client_error` and `server_error`.
- Group labelling through regular expressions. `match` decides whether patterns run against the request path or the host and path together, so applications split across subdomains can be grouped.
- `only` and `ignore` filters, a `fallback` label for unmatched requests and `sample_rate` support.
- Pulse card rendering one chart per selection, with a group selector bound to the query string.
