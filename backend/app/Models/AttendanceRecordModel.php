<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceRecordModel extends Model
{
    protected $table         = 'attendance_records';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false; // solo created_at, se maneja manual junto con recorded_at
    protected $allowedFields = [
        'employee_id', 'source_type', 'device_id', 'latitude', 'longitude',
        'accuracy_meters', 'location_label', 'photo_path', 'face_match_score',
        'verify_mode', 'recorded_at', 'raw_payload', 'created_at',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    /**
     * El "dia laboral" NO es medianoche-medianoche: arranca a las 5:00am y termina a
     * las 4:59:59am del dia siguiente. Un turno nocturno que sale a las 2am sigue
     * contando como parte del dia anterior (no se le "corta" la salida a la mitad).
     * Toda la logica de "primer/ultimo registro del dia", el mapa de hoy, el dashboard
     * y los reportes usan esta misma ventana via workDateSql()/isWithinTodaySql().
     */
    private const WORK_DAY_START_HOUR = 5;

    /**
     * Expresion SQL que regresa la "fecha laboral" de una columna datetime, recorriendo
     * el corte de medianoche a las 5am. Ej: 2024-05-10 02:30:00 -> work_date 2024-05-09.
     */
    private function workDateSql(string $column = 'recorded_at'): string
    {
        return 'DATE(' . $column . ' - INTERVAL ' . self::WORK_DAY_START_HOUR . ' HOUR)';
    }

    /**
     * Condicion raw "esta columna cae en el dia laboral de HOY" (usa NOW() del lado de
     * MySQL, que ya corregimos para que sea hora Mexico City real, no UTC).
     */
    private function isTodaySql(string $column = 'recorded_at'): string
    {
        return $this->workDateSql($column) . ' = ' . $this->workDateSql('NOW()');
    }

    protected function stampCreatedAt(array $data): array
    {
        if (empty($data['data']['created_at'])) {
            $data['data']['created_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    /**
     * Listado paginado con filtros para el web panel.
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $builder = $this->select('attendance_records.*, employees.first_name, employees.paternal_last_name, employees.maternal_last_name, employees.employee_number')
            ->join('employees', 'employees.id = attendance_records.employee_id');

        if (!empty($filters['employee_id'])) {
            $builder->where('attendance_records.employee_id', $filters['employee_id']);
        }
        if (!empty($filters['date_from'])) {
            $builder->where($this->workDateSql('attendance_records.recorded_at') . ' >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $builder->where($this->workDateSql('attendance_records.recorded_at') . ' <=', $filters['date_to']);
        }
        if (!empty($filters['source_type'])) {
            $builder->where('attendance_records.source_type', $filters['source_type']);
        }

        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('attendance_records.recorded_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Resumen diario por empleado: entrada = primer registro del dia,
     * salida = ultimo registro del dia, checkpoints = todo lo intermedio.
     */
    public function dailySummary(?int $employeeId, string $dateFrom, string $dateTo): array
    {
        $workDate = $this->workDateSql('ar.recorded_at');

        $builder = $this->db->table('attendance_records ar')
            ->select('ar.employee_id, e.employee_number, e.first_name, e.paternal_last_name, e.maternal_last_name,
                      ' . $workDate . ' as work_date,
                      MIN(ar.recorded_at) as entrada,
                      MAX(ar.recorded_at) as salida,
                      COUNT(*) as total_checkpoints')
            ->join('employees e', 'e.id = ar.employee_id')
            ->where($workDate . ' >=', $dateFrom)
            ->where($workDate . ' <=', $dateTo)
            ->groupBy('ar.employee_id, ' . $workDate)
            ->orderBy('work_date', 'DESC');

        if ($employeeId) {
            $builder->where('ar.employee_id', $employeeId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Todos los checkpoints de un empleado en un dia especifico, en orden,
     * marcando cual es entrada, cual salida y cuales son visitas intermedias.
     */
    public function dayDetail(int $employeeId, string $date): array
    {
        $rows = $this->where('employee_id', $employeeId)
            ->where($this->workDateSql('recorded_at'), $date)
            ->orderBy('recorded_at', 'ASC')
            ->findAll();

        $count = count($rows);
        foreach ($rows as $i => &$row) {
            if ($i === 0) {
                $row['type'] = 'entrada';
            } elseif ($i === $count - 1 && $count > 1) {
                $row['type'] = 'salida';
            } else {
                $row['type'] = 'checkpoint';
            }
        }

        return $rows;
    }

    /**
     * Para el "mapa en vivo": donde esta cada empleado que ya checo hoy.
     * Agrupa TODAS las checadas de hoy por empleado (para marcar bien entrada/
     * checkpoint/salida aunque alguna no tenga coordenadas), y por cada empleado
     * regresa solo los puntos que SI tienen lat/long, en orden, para poder dibujar
     * su recorrido del dia (ej. Ana: oficina -> evento en Polanco -> cliente en Santa Fe).
     */
    public function todayLocations(): array
    {
        $rows = $this->select('attendance_records.*, employees.first_name, employees.paternal_last_name,
                                employees.maternal_last_name, employees.employee_number, employees.photo_path')
            ->join('employees', 'employees.id = attendance_records.employee_id')
            ->where($this->isTodaySql('attendance_records.recorded_at'), null, false)
            ->orderBy('attendance_records.employee_id', 'ASC')
            ->orderBy('attendance_records.recorded_at', 'ASC')
            ->findAll();

        $byEmployee = [];
        foreach ($rows as $row) {
            $byEmployee[$row['employee_id']][] = $row;
        }

        $result = [];
        foreach ($byEmployee as $employeeId => $employeeRows) {
            $count = count($employeeRows);
            $points = [];

            foreach ($employeeRows as $i => $row) {
                if ($i === 0) {
                    $row['type'] = 'entrada';
                } elseif ($i === $count - 1 && $count > 1) {
                    $row['type'] = 'salida';
                } else {
                    $row['type'] = 'checkpoint';
                }

                // Solo se puede dibujar en el mapa si tiene coordenadas
                if ($row['latitude'] !== null && $row['longitude'] !== null) {
                    $points[] = $row;
                }
            }

            if (empty($points)) {
                continue; // este empleado checo hoy pero ningun registro trae ubicacion
            }

            $first = $employeeRows[0];
            $result[] = [
                'employee_id'     => $employeeId,
                'employee_number' => $first['employee_number'],
                'first_name'      => $first['first_name'],
                'paternal_last_name' => $first['paternal_last_name'],
                'maternal_last_name' => $first['maternal_last_name'],
                'photo_path'      => $first['photo_path'],
                'points'          => $points,
                'last_point'      => $points[count($points) - 1],
            ];
        }

        return $result;
    }

    public function todayCount(): int
    {
        return $this->where($this->isTodaySql(), null, false)->countAllResults();
    }

    public function distinctEmployeesToday(): int
    {
        return (int) $this->db->table('attendance_records')
            ->select('COUNT(DISTINCT employee_id) as c')
            ->where($this->isTodaySql(), null, false)
            ->get()->getRow()->c;
    }

    public function last7DaysTrend(): array
    {
        $workDate = $this->workDateSql();

        return $this->db->table('attendance_records')
            ->select($workDate . ' as day, COUNT(*) as total')
            ->where($workDate . ' >= DATE_SUB(' . $this->workDateSql('NOW()') . ', INTERVAL 6 DAY)', null, false)
            ->groupBy($workDate)
            ->orderBy('day', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * ¿Este empleado ya tiene algun registro en el dia laboral de HOY? Lo usan
     * KioskController/MobileController para saber si la checada que se esta por
     * guardar es "entrada" (la primera) o "checkpoint"/"salida" (ya hubo otra antes).
     */
    public function hasCheckedInToday(int $employeeId): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where($this->isTodaySql(), null, false)
            ->countAllResults() > 0;
    }
}
