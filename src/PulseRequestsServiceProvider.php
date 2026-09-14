<?php

namespace XLaravel\PulseRequests;

use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

class PulseRequestsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse-requests');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/pulse-requests'),
            ], 'pulse-requests-views');
        }

        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(LivewireManager::class)) {
            return;
        }

        $this->callAfterResolving('livewire', function (LivewireManager $livewire) {
            $livewire->component('pulse.requests', Livewire\Requests::class);
        });
    }
}
