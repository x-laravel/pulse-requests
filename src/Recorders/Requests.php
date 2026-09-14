<?php

namespace XLaravel\PulseRequests\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Ignores;
use Laravel\Pulse\Recorders\Concerns\Sampling;

class Requests
{
    use Ignores, Sampling;

    public const UNGROUPED = 'all';

    public string $listen = RequestHandled::class;

    public function __construct(
        protected Pulse $pulse,
    ) {}

    public function record(RequestHandled $event): void
    {
        $timestamp = CarbonImmutable::now()->getTimestamp();
        $subject = $this->subject($event);
        $status = $event->response->getStatusCode();

        $this->pulse->lazy(function () use ($timestamp, $subject, $status) {
            if (! $this->shouldSample() || ! $this->shouldRecord($subject)) {
                return;
            }

            $group = $this->group($subject);

            if ($group === null) {
                return;
            }

            $this->pulse->record(
                type: $this->statusClass($status),
                key: $group,
                timestamp: $timestamp,
            )->count()->onlyBuckets();
        });
    }

    protected function subject(RequestHandled $event): string
    {
        $path = $event->request->getPathInfo();

        return $this->option('match', 'path') === 'host_path'
            ? $event->request->getHost().$path
            : $path;
    }

    protected function shouldRecord(string $subject): bool
    {
        return $this->matchesOnly($subject) && ! $this->shouldIgnore($subject);
    }

    protected function matchesOnly(string $subject): bool
    {
        $only = $this->option('only', []);

        if ($only === []) {
            return true;
        }

        foreach ($only as $pattern) {
            if (preg_match($pattern, $subject) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function group(string $subject): ?string
    {
        $groups = $this->option('groups', []);

        if ($groups === []) {
            return static::UNGROUPED;
        }

        foreach ($groups as $pattern => $label) {
            if (preg_match($pattern, $subject) === 1) {
                return $label;
            }
        }

        return $this->option('fallback', 'other');
    }

    protected function statusClass(int $status): string
    {
        return match (true) {
            $status < 200 => 'informational',
            $status < 300 => 'successful',
            $status < 400 => 'redirection',
            $status < 500 => 'client_error',
            default => 'server_error',
        };
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return Config::get('pulse.recorders.'.static::class.'.'.$key, $default);
    }
}
