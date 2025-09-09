SELECT
    t.tmm_application_id,
    t.application_code,
    t.activate_date,
    t.application_type,
    t.application_name,
    t.application_abbr,
    t.work_setting_code,
    ws.work_setting_name,
    ws.work_setting_abbr,
    t.schedule_code,
    sc.schedule_name,
    sc.schedule_abbr,
    pn.pattern_name,     
    t.paid_holiday_code,
    ph.paid_holiday_name,
    ph.paid_holiday_abbr,  
    t.work_place_code,
    t.employment_contract_code,
    t.section_code,
    t.position_code,
    t.personal_ids,
    t.inactivate_flag,
    t.delete_flag,
    t.insert_date,
    t.insert_user,
    t.update_date,
    t.update_user
FROM
    (   
        SELECT 
            DISTINCT ON (t.application_code) * 
        FROM
            public.tmm_application t 
        WHERE
            t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date <= :target_date 
        ORDER BY
            t.application_code
            , t.activate_date DESC
    )t
LEFT JOIN (
    SELECT
        t1.work_setting_code,
        t1.work_setting_name,
        t1.work_setting_abbr
    FROM
        tmm_time_setting t1
    WHERE
        t1.delete_flag = 0
        AND t1.inactivate_flag = 0
        AND t1.activate_date = (
            SELECT MAX(a.activate_date)
            FROM tmm_time_setting a
            WHERE a.delete_flag = 0
              AND a.work_setting_code = t1.work_setting_code
              AND a.activate_date <= :target_date
        )
) ws
    ON t.work_setting_code = ws.work_setting_code
LEFT JOIN (
    SELECT
        t2.schedule_code,
        t2.schedule_name,
        t2.schedule_abbr,
        t2.pattern_code
    FROM
        tmm_schedule t2
    WHERE
        t2.delete_flag = 0
        AND t2.inactivate_flag = 0
        AND t2.activate_date = (
            SELECT MAX(a.activate_date)
            FROM tmm_schedule a
            WHERE a.delete_flag = 0
              AND a.schedule_code = t2.schedule_code
              AND a.activate_date <= :target_date
        )
) sc
    ON t.schedule_code = sc.schedule_code
LEFT JOIN (
    SELECT
        t3.paid_holiday_code,
        t3.paid_holiday_name,
        t3.paid_holiday_abbr
    FROM
        tmm_paid_holiday t3
    WHERE
        t3.delete_flag = 0
        AND t3.inactivate_flag = 0
        AND t3.activate_date = (
            SELECT MAX(a.activate_date)
            FROM tmm_paid_holiday a
            WHERE a.delete_flag = 0
              AND a.paid_holiday_code = t3.paid_holiday_code
              AND a.activate_date <= :target_date
        )
) ph
    ON t.paid_holiday_code = ph.paid_holiday_code
LEFT JOIN (
    SELECT
        t4.pattern_code,
        t4.pattern_name
    FROM
        tmm_work_type_pattern t4
    WHERE
        t4.delete_flag = 0
        AND t4.activate_date = (
            SELECT MAX(a.activate_date)
            FROM tmm_work_type_pattern a
            WHERE a.delete_flag = 0
              AND a.pattern_code = t4.pattern_code
              AND a.activate_date <= :target_date
        )
) pn
    ON sc.pattern_code = pn.pattern_code
WHERE
    t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date <= :target_date
ORDER BY
    t.application_code;
