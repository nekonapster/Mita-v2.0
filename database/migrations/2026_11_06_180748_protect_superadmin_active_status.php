<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION protect_superadmin_active_status()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.activo = TRUE
                       AND NEW.activo = FALSE
                       AND EXISTS (
                           SELECT 1
                           FROM role_user ru
                           JOIN roles r ON r.id = ru.role_id
                           WHERE ru.user_id = OLD.id
                             AND r.name = 'superadmin'
                       )
                    THEN
                        RAISE EXCEPTION
                            'No se puede desactivar al Superadministrador.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_superadmin_deactivation
                BEFORE UPDATE OF activo ON users
                FOR EACH ROW
                EXECUTE FUNCTION protect_superadmin_active_status();
            SQL);

        } elseif (DB::getDriverName() === 'sqlite') {

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prevent_superadmin_deactivation
                BEFORE UPDATE OF activo ON users
                FOR EACH ROW
                WHEN OLD.activo = 1
                     AND NEW.activo = 0
                     AND EXISTS (
                         SELECT 1
                         FROM role_user ru
                         JOIN roles r ON r.id = ru.role_id
                         WHERE ru.user_id = OLD.id
                           AND r.name = 'superadmin'
                     )
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'No se puede desactivar al Superadministrador.'
                    );
                END;
            SQL);

        } else {
            throw new \RuntimeException(
                'Motor de base de datos no compatible.'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {

            DB::unprepared(
                'DROP TRIGGER IF EXISTS prevent_superadmin_deactivation ON users'
            );

            DB::unprepared(
                'DROP FUNCTION IF EXISTS protect_superadmin_active_status()'
            );

        } elseif (DB::getDriverName() === 'sqlite') {

            DB::unprepared(
                'DROP TRIGGER IF EXISTS prevent_superadmin_deactivation'
            );
        }
    }
};