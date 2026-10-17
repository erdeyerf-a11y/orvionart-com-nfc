<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each feature test starts from the non-production baseline; production
        // sitemap/robots cases opt in explicitly inside their own test.
        URL::forceRootUrl(null);
        config(['app.env' => 'testing', 'app.url' => 'http://localhost', 'orvionart.public_indexing_enabled' => false]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }
}
