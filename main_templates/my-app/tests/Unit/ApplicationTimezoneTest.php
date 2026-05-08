<?php

namespace Tests\Unit;

use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_application_defaults_to_bucharest_time(): void
    {
        $this->assertSame('Europe/Bucharest', config('app.timezone'));
        $this->assertSame('Europe/Bucharest', now()->timezoneName);
    }
}
