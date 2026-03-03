<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure encryption key is set for tests that use session/CSRF (e.g. driver portal)
        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:2fl+Ktvk6gH5zHzhM3+HtNFVfmO+7/y2bJkLh1KP2xQ=']);
        }
    }
}
