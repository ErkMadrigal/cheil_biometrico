<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Acceso total al panel web'],
            ['name' => 'supervisor', 'description' => 'Ve empleados y reportes, no administra usuarios ni dispositivos'],
            ['name' => 'employee', 'description' => 'Cuenta usada por la app movil para registrar checadas'],
        ];

        foreach ($roles as $role) {
            $exists = $this->db->table('roles')->where('name', $role['name'])->get()->getRow();
            if (!$exists) {
                $role['created_at'] = date('Y-m-d H:i:s');
                $role['updated_at'] = date('Y-m-d H:i:s');
                $this->db->table('roles')->insert($role);
            }
        }
    }
}
