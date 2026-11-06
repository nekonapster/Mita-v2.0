<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'superadmin')
            ->value('id');

        if (!$roleId) {
            throw new RuntimeException(
                'No existe el rol superadmin.'
            );
        }

        $roleId = (int) $roleId;

        if (DB::getDriverName() === 'pgsql') {

            DB::unprepared("
                CREATE FUNCTION protect_superadmin_assignment()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    IF OLD.role_id = {$roleId} THEN
                        RAISE EXCEPTION
                            'No se puede retirar el rol Superadministrador.';
                    END IF;

                    RETURN OLD;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_superadmin_detach
                BEFORE DELETE OR UPDATE ON role_user
                FOR EACH ROW
                EXECUTE FUNCTION protect_superadmin_assignment();
            ");

        } elseif (DB::getDriverName() === 'sqlite') {

            DB::unprepared("
                CREATE TRIGGER prevent_superadmin_detach
                BEFORE DELETE ON role_user
                FOR EACH ROW
                WHEN OLD.role_id = {$roleId}
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'No se puede retirar el rol Superadministrador.'
                    );
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_superadmin_update
                BEFORE UPDATE ON role_user
                FOR EACH ROW
                WHEN OLD.role_id = {$roleId}
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'No se puede modificar la asignación Superadministrador.'
                    );
                END;
            ");

        } else {
            throw new RuntimeException(
                'Motor de base de datos no compatible.'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {

            DB::unprepared(
                'DROP TRIGGER IF EXISTS prevent_superadmin_detach ON role_user'
            );

            DB::unprepared(
                'DROP FUNCTION IF EXISTS protect_superadmin_assignment()'
            );

        } elseif (DB::getDriverName() === 'sqlite') {

            DB::unprepared(
                'DROP TRIGGER IF EXISTS prevent_superadmin_detach'
            );

            DB::unprepared(
                'DROP TRIGGER IF EXISTS prevent_superadmin_update'
            );
        }
    }
};