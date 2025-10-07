INSERT INTO employee_attendance_rate (
    employee_code, 
    ym, 
    planned_working_days, 
    planned_holidays, 
    actual_working_days, 
    actual_attendance_rate, 
    paid_leave_calc_working_days,
    paid_leave_calc_attendance_rate,
    insert_date, 
    update_date
)
VALUES (
    :employee_code, 
    :ym, 
    :planned_working_days, 
    :planned_holidays, 
    :actual_working_days, 
    :actual_attendance_rate, 
    :paid_leave_calc_working_days, 
    :paid_leave_calc_attendance_rate, 
    NOW(),
    NOW()
)
ON CONFLICT (employee_code, ym)
DO UPDATE SET
    employee_code   = EXCLUDED.employee_code,
    ym   = EXCLUDED.ym,
    planned_working_days   = EXCLUDED.planned_working_days,
    planned_holidays   = EXCLUDED.planned_holidays,
    actual_working_days   = EXCLUDED.actual_working_days,
    actual_attendance_rate   = EXCLUDED.actual_attendance_rate,
    paid_leave_calc_working_days = EXCLUDED.paid_leave_calc_working_days,
    paid_leave_calc_attendance_rate = EXCLUDED.paid_leave_calc_attendance_rate,
    update_date = NOW()
RETURNING *
;