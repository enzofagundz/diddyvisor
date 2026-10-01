<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Os testes de Feature sobem a aplicação pelo Tests\TestCase, que também
| protege o banco PostgreSQL de testes. Os testes de Unit seguem no
| PHPUnit\Framework\TestCase puro.
|
*/

pest()->extend(TestCase::class)->in('Feature');
