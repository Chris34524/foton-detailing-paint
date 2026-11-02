<?php

namespace App\Enums;

enum Permission: string
{
    case UnitsView = 'units.view';
    case UnitsCreate = 'units.create';
    case UnitsUpdate = 'units.update';
    case DiagnosisView = 'diagnosis.view';
    case DiagnosisCreate = 'diagnosis.create';
    case DiagnosisUpdate = 'diagnosis.update';
    case WorkOrdersView = 'work_orders.view';
    case WorkOrdersCreate = 'work_orders.create';
    case WorkOrdersUpdate = 'work_orders.update';
    case WorkOrdersAssign = 'work_orders.assign';
    case ProcessesView = 'processes.view';
    case ProcessesManage = 'processes.manage';
    case TimeTrackingView = 'time_tracking.view';
    case TimeTrackingUse = 'time_tracking.use';
    case MaterialsView = 'materials.view';
    case MaterialsConsume = 'materials.consume';
    case MaterialsManage = 'materials.manage';
    case InspectionView = 'inspection.view';
    case InspectionExecute = 'inspection.execute';
    case InspectionManage = 'inspection.manage';
    case ReleaseExecute = 'release.execute';
    case TraceabilityView = 'traceability.view';
    case TrainingView = 'training.view';
    case TrainingManage = 'training.manage';
    case DashboardView = 'dashboard.view';
    case AuditView = 'audit.view';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
