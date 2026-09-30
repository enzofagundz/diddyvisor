<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        if ($connection !== 'pgsql' || $app['config']->get('database.connections.pgsql.database') !== 'diddyvisor_testing') {
            throw new \RuntimeException('Os testes só podem usar PostgreSQL diddyvisor_testing.');
        }

        return $app;
    }
}
