<?php

namespace XLaravel\PulseRequests\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use XLaravel\PulseRequests\Recorders\Requests as RequestsRecorder;

#[Lazy]
class Requests extends Card
{
    public const ALL = '*';

    #[Url(as: 'requests')]
    public string $group = self::ALL;

    public function render(): Renderable
    {
        [$groups, $time, $runAt] = $this->remember(fn () => $this->graph(
            ['informational', 'successful', 'redirection', 'client_error', 'server_error'],
            'count',
        ), 'requests');

        $options = $this->options();
        $selected = $this->selected($options);
        $readings = $this->readings($groups, $options, $selected);

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('pulse-requests-chart-update', readings: $readings);
        }

        return View::make('pulse-requests::requests', [
            'options' => $options,
            'selected' => $selected,
            'readings' => $readings,
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.RequestsRecorder::class),
        ]);
    }

    protected function options(): array
    {
        $groups = $this->option('groups', []);

        if ($groups === []) {
            return [];
        }

        $labels = array_values(array_unique($groups));
        $fallback = $this->option('fallback', 'other');

        if ($fallback !== null && ! in_array($fallback, $labels, true)) {
            $labels[] = $fallback;
        }

        return [static::ALL => 'All'] + array_combine($labels, $labels);
    }

    protected function selected(array $options): ?string
    {
        if ($options === []) {
            return null;
        }

        return array_key_exists($this->group, $options) ? $this->group : static::ALL;
    }

    protected function readings(Collection $groups, array $options, ?string $selected): Collection
    {
        if ($selected === static::ALL) {
            return $this->sum($groups->only(array_diff(array_keys($options), [static::ALL])));
        }

        $readings = $groups->get($selected ?? RequestsRecorder::UNGROUPED) ?? collect();

        return $readings->map(fn (Collection $series) => $series->map(fn ($value) => (int) $value));
    }

    protected function sum(Collection $groups): Collection
    {
        return $groups->reduce(function (?Collection $carry, Collection $readings) {
            if ($carry === null) {
                return $readings->map(fn (Collection $series) => $series->map(fn ($value) => (int) $value));
            }

            return $carry->map(fn (Collection $series, string $type) => $series->map(
                fn (int $value, string $timestamp) => $value + (int) $readings->get($type)?->get($timestamp)
            ));
        }) ?? collect();
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return Config::get('pulse.recorders.'.RequestsRecorder::class.'.'.$key, $default);
    }
}
