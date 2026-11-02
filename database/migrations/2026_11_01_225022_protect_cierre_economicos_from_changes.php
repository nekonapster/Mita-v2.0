<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION bloquear_modificacion_cierre_economico()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION
                        'No se permite modificar ni eliminar un cierre económico consolidado';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER proteger_cierre_economicos
                BEFORE UPDATE OR DELETE ON cierre_economicos
                FOR EACH ROW
                EXECUTE FUNCTION bloquear_modificacion_cierre_economico();
            SQL);
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER proteger_cierre_economicos_update
                BEFORE UPDATE ON cierre_economicos
                BEGIN
                    SELECT RAISE(ABORT,
                        'No se permite modificar un cierre económico consolidado'
                    );
                END;

                CREATE TRIGGER proteger_cierre_economicos_delete
                BEFORE DELETE ON cierre_economicos
                BEGIN
                    SELECT RAISE(ABORT,
                        'No se permite eliminar un cierre económico consolidado'
                    );
                END;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS proteger_cierre_economicos
                ON cierre_economicos;

                DROP FUNCTION IF EXISTS bloquear_modificacion_cierre_economico();
            SQL);
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS proteger_cierre_economicos_update;
                DROP TRIGGER IF EXISTS proteger_cierre_economicos_delete;
            SQL);
        }
    }
};