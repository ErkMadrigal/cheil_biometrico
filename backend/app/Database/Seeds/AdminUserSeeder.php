<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el primer usuario administrador para poder entrar al web panel.
     * Credenciales por defecto (CAMBIALAS despues del primer login):
     *   email:    admin@cheil.local
     *   password: Admin12345!
     */
    public function run()
    {
        $adminRole = $this->db->table('roles')->where('name', 'admin')->get()->getRow();
        if (!$adminRole) {
            echo "No existe el rol 'admin'. Corre primero: php spark db:seed RoleSeeder\n";
            return;
        }

        $exists = $this->db->table('users')->where('email', 'admin@cheil.local')->get()->getRow();
        if ($exists) {
            echo "El usuario admin ya existe, no se vuelve a crear.\n";
            return;
        }

        $this->db->table('users')->insert([
            'name'          => 'Administrador',
            'email'         => 'admin@cheil.local',
            'password_hash' => password_hash('Admin12345!', PASSWORD_BCRYPT),
            'role_id'       => $adminRole->id,
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        echo "Usuario admin creado -> admin@cheil.local / Admin12345!\n";
    }
}
