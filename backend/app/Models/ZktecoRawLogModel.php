<?php

namespace App\Models;

use CodeIgniter\Model;

class ZktecoRawLogModel extends Model
{
    protected $table         = 'zkteco_raw_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'device_serial', 'table_name', 'raw_line', 'line_hash',
        'processed', 'attendance_record_id', 'error_message', 'created_at',
    ];

    public function alreadyProcessed(string $hash): bool
    {
        return (bool) $this->where('line_hash', $hash)->first();
    }
}
