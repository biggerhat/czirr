<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register MySQL FIELD() equivalent for SQLite testing
        if (config('database.default') === 'sqlite') {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('FIELD', function ($value, ...$list) {
                $index = array_search($value, $list);

                return $index === false ? 0 : $index + 1;
            });
        }
    }
}
