<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テストではビルド済みのアセットがなくてもビューを表示できるようにする
        $this->withoutVite();
    }
}
