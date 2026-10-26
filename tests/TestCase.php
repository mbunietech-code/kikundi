<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Most tests assert the Swahili wording, which is the source text.
        // English, the shipped default, is covered in LocalizationTest.
        config(['app.locale' => 'sw']);
        app()->setLocale('sw');
    }
}
