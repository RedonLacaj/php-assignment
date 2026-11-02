<?php

namespace Tests;

use App\Services\SheetReader;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeSheetReader;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // never call the real Google API from tests
        $this->app->instance(SheetReader::class, new FakeSheetReader);
    }
}
