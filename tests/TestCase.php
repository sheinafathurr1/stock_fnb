<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia pages render through the Vite helper, which throws when the
        // manifest is missing. Building assets is not a precondition for
        // running the test suite, so stub the helper out.
        $this->withoutVite();
    }
}
