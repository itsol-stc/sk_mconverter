INSERT INTO prosrv_import (
    customer_no
    , company_no
    , category
    , payment_date
    , process_type
    , process_class
    , employee_no
    , work_days
    , paid_leave
    , work_hours
    , overtime_normal
    , midnight_hours
    , midnight_overtime
    , legal_overtime
    , holiday_work_hours
    , deduction_hours
    , sick_100
    , sick_150
    , recogn_sick_75
    , substitute_leave
    , exchange_leave
    , holiday
    , prev_paid_leave
    , next_paid_leave
    , prev_term_leave
    , next_term_leave
    , public_holiday
    , prev_substitute
    , condolence_leave
    , marriage_leave
    , maternity_paid
    , maternity_unpaid
    , childcare_leave
    , nursing_leave
    , unpaid_absence
    , unpaid_nursing
    , unpaid_birth
    , industrial_accident
    , disaster_leave
    , childcare_work
    , nursing_work
    , target_date
    , insert_date
    , update_date
)
VALUES (
    :customer_no
    , :company_no
    , :category
    , :payment_date
    , :process_type
    , :process_class
    , :employee_no
    , :work_days
    , :paid_leave
    , :work_hours
    , :overtime_normal
    , :midnight_hours
    , :midnight_overtime
    , :legal_overtime
    , :holiday_work_hours
    , :deduction_hours
    , :sick_100
    , :sick_150
    , :recogn_sick_75
    , :substitute_leave
    , :exchange_leave
    , :holiday
    , :prev_paid_leave
    , :next_paid_leave
    , :prev_term_leave
    , :next_term_leave
    , :public_holiday
    , :prev_substitute
    , :condolence_leave
    , :marriage_leave
    , :maternity_paid
    , :maternity_unpaid
    , :childcare_leave
    , :nursing_leave
    , :unpaid_absence
    , :unpaid_nursing
    , :unpaid_birth
    , :industrial_accident
    , :disaster_leave
    , :childcare_work
    , :nursing_work
    , :target_date
    , NOW()
    , NOW()
)
ON CONFLICT (employee_no, target_date)
DO UPDATE SET
    customer_no   = EXCLUDED.customer_no,
    company_no   = EXCLUDED.company_no,
    category   = EXCLUDED.category,
    payment_date   = EXCLUDED.payment_date,
    process_type   = EXCLUDED.process_type,
    process_class   = EXCLUDED.process_class,
    employee_no   = EXCLUDED.employee_no,
    work_days   = EXCLUDED.work_days,
    paid_leave   = EXCLUDED.paid_leave,
    work_hours   = EXCLUDED.work_hours,
    overtime_normal   = EXCLUDED.overtime_normal,
    midnight_hours   = EXCLUDED.midnight_hours,
    midnight_overtime   = EXCLUDED.midnight_overtime,
    legal_overtime   = EXCLUDED.legal_overtime,
    holiday_work_hours   = EXCLUDED.holiday_work_hours,
    deduction_hours   = EXCLUDED.deduction_hours,
    sick_100   = EXCLUDED.sick_100,
    sick_150   = EXCLUDED.sick_150,
    recogn_sick_75   = EXCLUDED.recogn_sick_75,
    substitute_leave   = EXCLUDED.substitute_leave,
    exchange_leave   = EXCLUDED.exchange_leave,
    holiday   = EXCLUDED.holiday,
    prev_paid_leave   = EXCLUDED.prev_paid_leave,
    prev_term_leave   = EXCLUDED.prev_term_leave,
    next_term_leave   = EXCLUDED.next_term_leave,
    public_holiday   = EXCLUDED.public_holiday,
    prev_substitute   = EXCLUDED.prev_substitute,
    condolence_leave   = EXCLUDED.condolence_leave,
    marriage_leave   = EXCLUDED.marriage_leave,
    maternity_paid   = EXCLUDED.maternity_paid,
    maternity_unpaid   = EXCLUDED.maternity_unpaid,
    childcare_leave   = EXCLUDED.childcare_leave,
    nursing_leave   = EXCLUDED.nursing_leave,
    unpaid_absence   = EXCLUDED.unpaid_absence,
    unpaid_nursing   = EXCLUDED.unpaid_nursing,
    unpaid_birth   = EXCLUDED.unpaid_birth,
    industrial_accident   = EXCLUDED.industrial_accident,
    childcare_work   = EXCLUDED.childcare_work,
    nursing_work   = EXCLUDED.nursing_work,
    target_date   = EXCLUDED.target_date,
    update_date = NOW()
RETURNING *
;