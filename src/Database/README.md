# Attendance Management Module - Database Schema

## Overview
This module provides comprehensive attendance policy management with GPS tracking, device integration, penalty systems, and employee grouping capabilities.

---

## ✅ Completed Tables (Phase 1)

### 1. **attendance_policies**
Core policy configuration for attendance tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar | Policy name |
| description | text | Policy description |
| tracking_mode | enum | check_in_only / check_in_out / manual |
| is_active | boolean | Active status |
| is_default | boolean | Default policy flag |
| created_by_user_id | bigint FK | Creator user |
| updated_by_user_id | bigint FK | Last updater |

**Relationships:**
- `hasMany` → penalty_policies
- `hasMany` → absence_policies
- `hasMany` → employee_groups

---

### 2. **attendance_grace_settings**
Grace period configurations (global or custom).

| Column | Type | Description |
|--------|------|-------------|
| late_grace_minutes | int | Late arrival tolerance |
| early_leave_grace_minutes | int | Early departure tolerance |
| auto_checkout_after_minutes | int | Auto-checkout delay |
| is_global_default | boolean | Global default flag |

---

### 3. **attendance_methods**
System-level method enablement (GPS/Fingerprint/NFC).

| Column | Type | Description |
|--------|------|-------------|
| method | enum | gps / fingerprint / nfc |
| is_enabled | boolean | Method enabled |
| device_count | int | Active devices count |

**Constraints:** UNIQUE(method)

---

### 4. **attendance_gps_locations**
GPS geofencing locations.

| Column | Type | Description |
|--------|------|-------------|
| name | varchar | Location name |
| address_text | text | Full address |
| lat | decimal(10,7) | Latitude |
| lng | decimal(10,7) | Longitude |
| radius_meters | int | Geofence radius |
| branch_id | bigint FK | Target branch |
| employee_group_id | bigint FK | Target group |
| is_active | boolean | Active status |

**Relationships:**
- `belongsTo` → departments (branch)
- `belongsTo` → employee_groups

**Business Rule:** Only ONE of (branch_id, employee_group_id) can be set.

---

### 5. **attendance_devices**
Fingerprint and NFC devices.

| Column | Type | Description |
|--------|------|-------------|
| device_type | enum | fingerprint / nfc |
| name | varchar | Device name |
| branch_id | bigint FK | Assigned branch |
| location_in_branch | varchar | Specific location |
| serial_no | varchar | Device serial number |
| is_active | boolean | Active status |

**Constraints:** UNIQUE(device_type, serial_no)

---

### 6. **attendance_penalty_policies**
Progressive penalty rules based on violations.

| Column | Type | Description |
|--------|------|-------------|
| policy_id | bigint FK | Parent policy |
| violation_type | enum | late_arrival / early_departure / auto_checkout |
| minutes_from | int | Range start |
| minutes_to | int | Range end |
| recurrence_from | int | Repetition start |
| recurrence_to | int | Repetition end |
| penalty_action | enum | skip / notification / warning_verbal / warning_written / deduction / suspension / termination |
| deduction_type | enum | fixed / percentage |
| deduction_value | decimal | Deduction amount |
| suspension_days | int | Suspension duration |
| notification_message | text | Template message |

**Selection Logic:**
```php
AttendancePenaltyPolicy::findApplicablePenalty($policyId, $violationType, $minutes, $recurrenceCount);
```

---

### 7. **unexcused_absence_policies**
Absence without permission rules.

| Column | Type | Description |
|--------|------|-------------|
| policy_id | bigint FK | Parent policy |
| absence_reason_type | enum | no_notice / repetitive / consecutive / late_early / after_rejection |
| day_selector_type | enum | single / range |
| day_from | int | Day range start |
| day_to | int | Day range end |
| penalty_action | enum | Same as penalty_policies |
| late_minutes | int | Special case: late minutes |
| early_leave_minutes | int | Special case: early leave |
| recurrence_count | int | Special case: repetitions |

---

### 8. **employee_groups**
Employee grouping with policy assignment.

| Column | Type | Description |
|--------|------|-------------|
| name | varchar | Group name |
| description | text | Group description |
| applied_policy_id | bigint FK | Assigned policy |
| grace_source | enum | use_global / custom |
| grace_setting_id | bigint FK | Custom grace settings |

**Relationships:**
- `belongsTo` → attendance_policies
- `belongsTo` → attendance_grace_settings
- `belongsToMany` → employees (via employee_group_members)
- `hasMany` → employee_group_allowed_methods

---

### 9. **employee_group_members**
Pivot table: groups ↔ employees.

| Column | Type | Description |
|--------|------|-------------|
| group_id | bigint FK | Group reference |
| employee_id | bigint FK | Employee reference |
| assigned_at | timestamp | Assignment date |

**Constraints:** UNIQUE(group_id, employee_id)

---

### 10. **employee_group_allowed_methods**
Method permissions per group.

| Column | Type | Description |
|--------|------|-------------|
| group_id | bigint FK | Group reference |
| method | enum | gps / fingerprint / nfc |
| is_allowed | boolean | Method allowed |

**Constraints:** UNIQUE(group_id, method)

---

## 🔄 Business Rules Implementation

### Method Enablement Logic
```php
// System-level check
$systemEnabled = AttendanceMethod::isMethodEnabled('gps');

// Group-level check
$groupAllowed = $group->isMethodAllowed('gps');

// Final permission = $systemEnabled && $groupAllowed
```

### Grace Settings Hierarchy
```php
// Get effective grace settings for a group
$graceSettings = $group->getEffectiveGraceSettings();
```

### Penalty Selection
```php
// Find applicable penalty rule
$penalty = AttendancePenaltyPolicy::findApplicablePenalty(
    $policyId, 
    'late_arrival', 
    $lateMinutes, 
    $recurrenceCount
);
```

### GPS Geofencing
```php
// Check if coordinates are within geofence
$location = AttendanceGpsLocation::find($id);
$isWithin = $location->isWithinGeofence($employeeLat, $employeeLng);
```

---

## 📋 Missing Tables (Phase 2)

### Work Schedules Module
- `work_schedules` (templates with versioning)
- `work_schedule_periods` (daily periods 1-4)
- `work_schedule_exceptions` (date-specific overrides)
- `work_schedule_assignments` (employee/group assignments)

### Operational Logs (Phase 3)
- `attendance_logs` (check-in/out events)
- `attendance_violations` (penalty triggers)
- `attendance_recurrence_counters` (violation tracking)
- `attendance_daily_summaries` (aggregated data)

---

## 🚀 Migration Execution

```bash
# Run migrations
php artisan migrate --path=app/Modules/SystemSettings/Database/Migrations

# Rollback
php artisan migrate:rollback --path=app/Modules/SystemSettings/Database/Migrations
```

---

## 📊 ERD Relationships Summary

```
attendance_policies
├── hasMany → attendance_penalty_policies
├── hasMany → unexcused_absence_policies
└── hasMany → employee_groups

employee_groups
├── belongsTo → attendance_policies
├── belongsTo → attendance_grace_settings
├── belongsToMany → employees
├── hasMany → employee_group_allowed_methods
└── hasMany → attendance_gps_locations

attendance_gps_locations
├── belongsTo → departments (branch)
└── belongsTo → employee_groups

attendance_devices
└── belongsTo → departments (branch)
```

---

## ✅ Validation Rules

### GPS Locations
- Only ONE of (branch_id, employee_group_id) must be set
- Radius: 10-1000 meters
- Lat: -90 to 90
- Lng: -180 to 180

### Penalty Policies
- minutes_from ≤ minutes_to
- recurrence_from ≤ recurrence_to
- deduction_value: required if penalty_action = 'deduction'
- suspension_days: required if penalty_action = 'suspension'

### Absence Policies
- day_from ≤ day_to
- If day_selector_type = 'single': day_from = day_to

---

**Last Updated:** 2026-01-25  
**Module Version:** 1.0.0  
**Status:** ✅ Phase 1 Complete
