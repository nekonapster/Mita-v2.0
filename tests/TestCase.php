<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app->make('db')->connection();

        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        // Permitir las pruebas habituales en SQLite en memoria.
        if ($driver === 'sqlite' && $database === ':memory:') {
            return $app;
        }

        // Permitir exclusivamente la base PostgreSQL de pruebas.
        if ($driver === 'pgsql') {
            $databaseReal = $connection
                ->selectOne('SELECT current_database() AS nombre')
                ->nombre;

            $host = $connection->getConfig('host');

            if ($databaseReal === 'mita_testing' && $host === 'pgsql') {
                return $app;
            }
        }

        throw new RuntimeException(
            "SEGURIDAD MiTa: pruebas bloqueadas. " .
            "Conexión: {$driver}, base de datos: {$database}. " .
            "Solo se permite SQLite en memoria o PostgreSQL mita_testing."
        );
    }
}
