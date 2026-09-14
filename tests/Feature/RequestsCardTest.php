<?php

namespace XLaravel\PulseRequests\Tests\Feature;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\PulseRequests\Livewire\Requests as RequestsCard;
use XLaravel\PulseRequests\Recorders\Requests;
use XLaravel\PulseRequests\Tests\TestCase;

class RequestsCardTest extends TestCase
{
    #[Test]
    public function it_offers_no_group_selection_when_no_groups_are_configured(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->assertViewHas('options', [])
            ->assertDontSee('Group');
    }

    #[Test]
    public function it_lists_every_group_plus_the_fallback_behind_an_all_option(): void
    {
        $this->configureGroups([
            '#^/api/#' => 'api',
            '#^/common/#' => 'common',
        ]);

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->assertViewHas('options', [
                '*' => 'All',
                'api' => 'api',
                'common' => 'common',
                'other' => 'other',
            ])
            ->assertViewHas('selected', '*');
    }

    #[Test]
    public function it_leaves_the_fallback_out_of_the_options_when_it_is_null(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);
        Config::set('pulse.recorders.'.Requests::class.'.fallback', null);

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->assertViewHas('options', ['*' => 'All', 'api' => 'api']);
    }

    #[Test]
    public function it_sums_every_group_for_the_all_option(): void
    {
        $this->configureGroups([
            '#^/api/#' => 'api',
            '#^/common/#' => 'common',
        ]);

        $this->handle('/api/tickets');
        $this->handle('/common/cities');

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->assertViewHas('readings', fn ($readings) => $readings->get('successful')->sum() === 2);
    }

    #[Test]
    public function it_shows_a_single_group_once_one_is_selected(): void
    {
        $this->configureGroups([
            '#^/api/#' => 'api',
            '#^/common/#' => 'common',
        ]);

        $this->handle('/api/tickets');
        $this->handle('/common/cities');

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->set('group', 'api')
            ->assertViewHas('selected', 'api')
            ->assertViewHas('readings', fn ($readings) => $readings->get('successful')->sum() === 1);
    }

    #[Test]
    public function it_leaves_keys_that_are_no_longer_configured_out_of_the_all_total(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);

        $this->handle('/api/tickets');
        $this->recordLegacyKey('common');

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->assertViewHas('readings', fn ($readings) => $readings->get('successful')->sum() === 1);
    }

    #[Test]
    public function it_falls_back_to_all_for_an_unknown_group(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);

        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)
            ->set('group', 'nope')
            ->assertViewHas('selected', '*');
    }

    private function configureGroups(array $groups): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.groups', $groups);
    }

    private function recordLegacyKey(string $key): void
    {
        Pulse::record(
            type: 'successful',
            key: $key,
            timestamp: now()->getTimestamp(),
        )->count()->onlyBuckets();

        Pulse::ingest();
    }

    private function handle(string $path, int $status = 200): void
    {
        Event::dispatch(new RequestHandled(
            Request::create($path),
            new Response('', $status),
        ));

        Pulse::ingest();
    }
}
