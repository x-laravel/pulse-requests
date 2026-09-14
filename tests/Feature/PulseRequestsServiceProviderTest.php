<?php

namespace XLaravel\PulseRequests\Tests\Feature;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\PulseRequests\Livewire\Requests as RequestsCard;
use XLaravel\PulseRequests\Tests\TestCase;

class PulseRequestsServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_card_as_a_livewire_component(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test('pulse.requests')->assertOk();
    }

    #[Test]
    public function it_renders_the_card(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(RequestsCard::class)->assertOk();
    }
}
