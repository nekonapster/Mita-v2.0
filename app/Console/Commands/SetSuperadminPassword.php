<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetSuperadminPassword extends Command
{
    protected $signature = 'mita:superadmin-password';

    protected $description = 'Establece la contraseña del Superadministrador de MiTa';

    public function handle(): int
    {
        $user = User::where('email', 'neko@mita.com')->first();

        if (!$user || !$user->isSuperAdmin()) {
            $this->error('No existe la cuenta Superadministrador Nekonapster.');

            return self::FAILURE;
        }

        $password = $this->secret('Nueva contraseña de Nekonapster');

        if (!$password || mb_strlen($password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }

        $confirmation = $this->secret('Repetí la contraseña');

        if ($password !== $confirmation) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info('Contraseña de Nekonapster actualizada correctamente.');

        return self::SUCCESS;
    }
}