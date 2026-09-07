<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceModel extends Model
{
    protected $table         = 'devices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name', 'serial_number', 'type', 'location_label',
        'latitude', 'longitude', 'ip_address', 'is_active', 'last_seen_at',
    ];

    protected $validationRules = [
        // Ver el mismo comentario en EmployeeModel: {id} necesita su propia regla.
        'id'            => 'permit_empty|is_natural',
        'name'          => 'required|max_length[100]',
        'serial_number' => 'required|max_length[50]|is_unique[devices.serial_number,id,{id}]',
        'type'          => 'required|in_list[fingerprint_entry,face_exit,other]',
    ];

    public function findBySerial(string $sn): ?array
    {
        return $this->where('serial_number', $sn)->first();
    }

    /**
     * Da de alta automaticamente un dispositivo desconocido cuando hace su primer
     * handshake ADMS, para que el admin solo tenga que renombrarlo en el panel.
     */
    public function autoRegister(string $sn, ?string $ip = null): array
    {
        $existing = $this->findBySerial($sn);
        if ($existing) {
            $this->update($existing['id'], ['last_seen_at' => date('Y-m-d H:i:s'), 'ip_address' => $ip]);
            return $existing;
        }

        $id = $this->insert([
            'name'          => 'Dispositivo sin nombre (' . $sn . ')',
            'serial_number' => $sn,
            'type'          => 'other',
            'ip_address'    => $ip,
            'is_active'     => 0,
            'last_seen_at'  => date('Y-m-d H:i:s'),
        ], true);

        return $this->find($id);
    }
}
