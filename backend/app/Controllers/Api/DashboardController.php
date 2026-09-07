<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AttendanceRecordModel;
use App\Models\DeviceModel;
use App\Models\EmployeeModel;

class DashboardController extends BaseController
{
    /**
     * GET /api/v1/dashboard/summary
     * Numeros clave + tendencia de los ultimos 7 dias para el dashboard del web panel.
     */
    public function summary()
    {
        $attendance = new AttendanceRecordModel();
        $employees  = new EmployeeModel();
        $devices    = new DeviceModel();

        return $this->ok([
            'total_employees_active'   => $employees->where('status', 'active')->countAllResults(),
            'checkins_today'           => $attendance->todayCount(),
            'employees_checked_today'  => $attendance->distinctEmployeesToday(),
            'active_devices'           => $devices->where('is_active', 1)->countAllResults(),
            'trend_last_7_days'        => $attendance->last7DaysTrend(),
        ]);
    }
}
