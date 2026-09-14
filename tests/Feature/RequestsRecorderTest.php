<?php

namespace XLaravel\PulseRequests\Tests\Feature;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Pulse\Facades\Pulse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\PulseRequests\Recorders\Requests;
use XLaravel\PulseRequests\Tests\TestCase;

class RequestsRecorderTest extends TestCase
{
    #[Test]
    public function it_counts_every_request_under_a_single_series_when_no_groups_are_configured(): void
    {
        $this->handle('/api/tickets');
        $this->handle('/dashboard');

        $this->assertSame(['all' => 2], $this->counts());
    }

    #[Test]
    public function it_labels_each_request_with_the_group_it_matches(): void
    {
        $this->configureGroups([
            '#^/api/#' => 'api',
            '#^/common/#' => 'common',
        ]);

        $this->handle('/api/tickets');
        $this->handle('/api/events');
        $this->handle('/common/cities');

        $this->assertSame(['api' => 2, 'common' => 1], $this->counts());
    }

    #[Test]
    public function it_labels_requests_matching_no_group_with_the_fallback(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);

        $this->handle('/api/tickets');
        $this->handle('/dashboard');

        $this->assertSame(['api' => 1, 'other' => 1], $this->counts());
    }

    #[Test]
    public function it_drops_requests_matching_no_group_when_the_fallback_is_null(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);
        Config::set('pulse.recorders.'.Requests::class.'.fallback', null);

        $this->handle('/api/tickets');
        $this->handle('/dashboard');

        $this->assertSame(['api' => 1], $this->counts());
    }

    #[Test]
    public function it_records_only_requests_matching_the_whitelist(): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.only', ['#^/api/#', '#^/common/#']);

        $this->handle('/api/tickets');
        $this->handle('/common/cities');
        $this->handle('/dashboard');

        $this->assertSame(['all' => 2], $this->counts());
    }

    #[Test]
    public function it_requires_a_request_to_pass_the_whitelist_and_the_blacklist(): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.only', ['#^/api/#']);
        Config::set('pulse.recorders.'.Requests::class.'.ignore', ['#^/api/health$#']);

        $this->handle('/api/tickets');
        $this->handle('/api/health');
        $this->handle('/dashboard');

        $this->assertSame(['all' => 1], $this->counts());
    }

    #[Test]
    public function it_uses_the_first_matching_group(): void
    {
        $this->configureGroups([
            '#^/api/internal/#' => 'api-internal',
            '#^/api/#' => 'api',
        ]);

        $this->handle('/api/internal/health');

        $this->assertSame(['api-internal' => 1], $this->counts());
    }

    #[Test]
    public function it_drops_ignored_paths_before_grouping(): void
    {
        $this->configureGroups(['#^/api/#' => 'api']);
        Config::set('pulse.recorders.'.Requests::class.'.ignore', ['#^/api/health$#']);

        $this->handle('/api/health');
        $this->handle('/api/tickets');

        $this->assertSame(['api' => 1], $this->counts());
    }

    #[Test]
    #[DataProvider('statusClasses')]
    public function it_classifies_responses_by_status_code(int $status, string $type): void
    {
        $this->handle('/api/tickets', $status);

        $this->assertSame([$type => 1], $this->countsByType());
    }

    public static function statusClasses(): array
    {
        return [
            [100, 'informational'],
            [200, 'successful'],
            [204, 'successful'],
            [301, 'redirection'],
            [404, 'client_error'],
            [422, 'client_error'],
            [500, 'server_error'],
            [503, 'server_error'],
        ];
    }

    #[Test]
    public function it_matches_patterns_against_the_host_and_path_when_configured(): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.match', 'host_path');
        $this->configureGroups([
            '#^api\.#' => 'api',
            '#^ticket\..*/download$#' => 'download',
        ]);

        $this->handle('http://api.example.com/events');
        $this->handle('http://ticket.example.com/ABC123/download');
        $this->handle('http://ticket.example.com/ABC123');

        $this->assertSame(['api' => 1, 'download' => 1, 'other' => 1], $this->counts());
    }

    #[Test]
    public function it_hides_the_host_from_patterns_by_default(): void
    {
        $this->configureGroups(['#^api\.#' => 'api']);

        $this->handle('http://api.example.com/events');

        $this->assertSame(['other' => 1], $this->counts());
    }

    #[Test]
    public function it_applies_the_whitelist_to_the_host_when_configured(): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.match', 'host_path');
        Config::set('pulse.recorders.'.Requests::class.'.only', ['#^api\.#']);

        $this->handle('http://api.example.com/events');
        $this->handle('http://ticket.example.com/ABC123');

        $this->assertSame(['all' => 1], $this->counts());
    }

    #[Test]
    public function it_records_nothing_when_the_sample_rate_is_zero(): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.sample_rate', 0);

        $this->handle('/api/tickets');

        $this->assertSame([], $this->counts());
    }

    private function configureGroups(array $groups): void
    {
        Config::set('pulse.recorders.'.Requests::class.'.groups', $groups);
    }

    private function handle(string $path, int $status = 200): void
    {
        Event::dispatch(new RequestHandled(
            Request::create($path),
            new Response('', $status),
        ));
    }

    private function counts(): array
    {
        return $this->aggregates()->groupBy('key')->map->sum('value')->map(fn ($value) => (int) $value)->all();
    }

    private function countsByType(): array
    {
        return $this->aggregates()->groupBy('type')->map->sum('value')->map(fn ($value) => (int) $value)->all();
    }

    private function aggregates()
    {
        Pulse::ingest();

        return DB::table('pulse_aggregates')->where('period', 60)->get();
    }
}
