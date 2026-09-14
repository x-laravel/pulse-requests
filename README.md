# Pulse Requests

A Laravel Pulse card that counts incoming requests by HTTP status class, split into the route groups you define.

Every handled request is counted as one of five classes: `informational` (1xx), `successful` (2xx), `redirection` (3xx), `client_error` (4xx) and `server_error` (5xx). You decide which requests are counted and how they are grouped, and the card draws one chart per group with a selector in its header.

## Installation

```bash
composer require x-laravel/pulse-requests
```

Add the card to `resources/views/vendor/pulse/dashboard.blade.php`:

```blade
<x-pulse>
    <livewire:pulse.requests cols="8" />

    {{-- ... --}}
</x-pulse>
```

Register the recorder in `config/pulse.php`:

```php
'recorders' => [
    \XLaravel\PulseRequests\Recorders\Requests::class => [
        'enabled' => env('PULSE_REQUESTS_ENABLED', true),
        'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 1),
        'match' => 'path',
        'only' => [],
        'ignore' => [
            '#^/'.env('PULSE_PATH', 'pulse').'(/.*)?$#',
            '#^/'.env('HORIZON_PATH', 'horizon').'(/.*)?$#',
            '#^/'.env('TELESCOPE_PATH', 'telescope').'(/.*)?$#',
            '#^/livewire/#',
        ],
        'groups' => [],
        'fallback' => 'other',
    ],

    // ...
],
```

Every key is optional. An empty array records every request under a single `all` series.

## The card

The header carries a `Group` selector whose options come from your configuration. `All` is the default and sums every configured group; picking a single group narrows the chart to it. When no groups are configured the selector is hidden and the card shows one series.

The selector lists what the configuration defines. A label you remove from `groups` disappears from the card even while its rows are still in the database, and it no longer counts towards `All`.

## Configuration

Every option below lives in the recorder's entry in `config/pulse.php`.

### What patterns run against

Every pattern in `only`, `ignore` and `groups` is a regular expression, and `match` decides what it is applied to:

| `match` | Pattern runs against |
|---------|----------------------|
| `path` (default) | `/events` |
| `host_path` | `api.example.com/events` |

Use `host_path` when routes are split across subdomains rather than path prefixes:

```php
'match' => 'host_path',
'groups' => [
    '#^api\.#' => 'api',
    '#^ticket\.#' => 'ticket',
    '#^webhook\.#' => 'webhook',
],
```

> [!WARNING]
> The `ignore` patterns in the installation example are written for `path` and start with `^/`. Under `host_path` they match nothing, so rewrite them for the host, for example `'#^monitor\.#'`.

### Which requests are counted

A request is recorded when it matches `only` and does not match `ignore`. While `only` is empty every request is a candidate:

```php
'only' => ['#^/api/#'],
'ignore' => ['#^/api/health$#'],
```

The example counts the whole API except its health endpoint.

### How requests are grouped

`groups` maps a pattern to a label, and the first match wins, so put the narrower pattern first:

```php
'groups' => [
    '#^/api/internal/#' => 'api-internal',
    '#^/api/#' => 'api',
],
```

A request matching no pattern is labelled with `fallback`, which defaults to `other`. Set `fallback` to `null` to drop those requests instead. While `groups` is empty every request is counted under a single `all` series.

Labels are written to Pulse's `key` column, so keep them to a small fixed set. A label built from the path itself grows `pulse_aggregates` without bound.

### Sampling

Record a fraction of the traffic and let the card scale the numbers back up:

```php
'sample_rate' => 0.1,
```

Sampled values are shown with a `~` prefix, the convention Pulse's own cards use.

### Defaults

Every option may be left out. The recorder then falls back to these values:

| Option | Default | Effect |
|--------|---------|--------|
| `enabled` | `true` | Pulse skips the recorder entirely when false |
| `sample_rate` | `1` | Fraction of requests recorded |
| `match` | `path` | `path` or `host_path` |
| `only` | `[]` | Every request is a candidate |
| `ignore` | `[]` | Nothing is dropped, including your Pulse and Horizon traffic |
| `groups` | `[]` | Everything is counted under a single `all` series |
| `fallback` | `other` | Label for requests matching no group |

## How the data is stored

The recorder writes bucketed aggregates (`->count()->onlyBuckets()`): one counter per period, status class and group. No per-request rows are kept, so storage stays flat however much traffic the application takes.

The counter is bound to the `RequestHandled` event, so it covers every request Laravel finishes. A request killed mid-flight, by a PHP fatal error or a memory limit, never reaches it.

## Publishing the view

```bash
php artisan vendor:publish --tag=pulse-requests-views
```

## Testing

```bash
DOCKER_BUILDKIT=0 docker compose --profile php83 build
docker compose --profile php83 up   # php84, php85, livewire3
```

Tests run against SQLite in memory and need no external services.

## License

MIT. See [LICENSE.md](LICENSE.md).
