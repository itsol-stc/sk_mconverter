INSERT INTO varied_overtime_employee (
    employee_no, 
    target_date, 
    work_hours, 
    normal_overtime_raw, 
    work_minutes, 
    normal_overtime_adj, 
    contract_workminutes_overtime,
    insert_date, 
    update_date
)
VALUES (
    :employee_no, 
    :target_date, 
    :work_hours, 
    :normal_overtime_raw, 
    :work_minutes, 
    :normal_overtime_adj, 
    :contract_workminutes_overtime, 
    NOW(),
    NOW()
)
ON CONFLICT (employee_no, target_date)
DO UPDATE SET
    employee_no   = EXCLUDED.employee_no,
    target_date   = EXCLUDED.target_date,
    work_hours   = EXCLUDED.work_hours,
    normal_overtime_raw   = EXCLUDED.normal_overtime_raw,
    work_minutes   = EXCLUDED.work_minutes,
    normal_overtime_adj   = EXCLUDED.normal_overtime_adj,
    contract_workminutes_overtime = EXCLUDED.contract_workminutes_overtime,
    update_date = NOW()
RETURNING *
;