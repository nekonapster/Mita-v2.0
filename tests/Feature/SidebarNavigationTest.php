<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_tiene_un_solo_enlace_a_control_de_horas(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin = User::factory()->create();
        $admin->roles()->attach($rol->id);

        $respuesta = $this->actingAs($admin)
            ->get(route('dashboard'));

        $respuesta->assertOk();

        $enlace = 'href="' . route('horas.validacion') . '"';
        $this->assertSame(
            2,
            substr_count($respuesta->getContent(), $enlace),
            'Debe existir un enlace en el menú y otro como acceso rápido del dashboard.'
        );
    }
}
