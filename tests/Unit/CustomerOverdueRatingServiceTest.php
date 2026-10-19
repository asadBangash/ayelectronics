<?php

namespace Tests\Unit;

use App\Services\Installments\CustomerOverdueRatingService;
use PHPUnit\Framework\TestCase;

class CustomerOverdueRatingServiceTest extends TestCase
{
    public function test_level_mapping(): void
    {
        $service = new CustomerOverdueRatingService;

        $this->assertSame('green', $service->levelForMonths(0));
        $this->assertSame('blue', $service->levelForMonths(1));
        $this->assertSame('orange', $service->levelForMonths(2));
        $this->assertSame('red', $service->levelForMonths(3));
        $this->assertSame('dark_red', $service->levelForMonths(4));
        $this->assertSame('dark_red', $service->levelForMonths(10));
    }
}
