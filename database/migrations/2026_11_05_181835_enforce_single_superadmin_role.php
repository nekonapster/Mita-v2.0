<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Crear el rol Superadministrador si todavía no existe.
        DB::table('roles')->insertOrIgnore([
            'name' => 'superadmin',
            'display_name' => 'Superadministrador',
            'description' => 'Propietario de MiTa con acceso total',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Obtener el ID real del rol.
        $roleId = (int) DB::table('roles')
            ->where('name', 'superadmin')
            ->value('id');

        // Impedir que dos usuarios tengan el rol Superadministrador.
        DB::statement(
            "CREATE UNIQUE INDEX role_user_single_superadmin
             ON role_user (role_id)
             WHERE role_id = {$roleId}"
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS role_user_single_superadmin'
        );
    }
};