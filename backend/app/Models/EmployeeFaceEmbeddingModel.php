<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeFaceEmbeddingModel extends Model
{
    protected $table         = 'employee_face_embeddings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['employee_id', 'descriptor', 'source', 'is_active'];

    public function activeForEmployee(int $employeeId): ?array
    {
        return $this->where('employee_id', $employeeId)
            ->where('is_active', 1)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Distancia euclidiana entre dos descriptores faciales de 128 dimensiones.
     * Valores menores a ~0.5-0.6 se consideran la misma persona (umbral configurable).
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return INF;
        }

        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += ($value - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }
}
