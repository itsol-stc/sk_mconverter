SELECT
    MAX(bh.employee_code) AS employee_code
    , COALESCE(COUNT(*), 0) AS both_half_holiday_count 
FROM
    ( 
        SELECT
            t1.request_start_date
            , MAX(ph.employee_code) AS employee_code
            , COUNT(t1.personal_id) AS half_holiday_count 
        FROM
            public.tmd_holiday_request t1 JOIN public.pft_workflow wf 
                ON t1.workflow = wf.workflow 
            LEFT JOIN ( 
                SELECT DISTINCT
                        ON (holiday_code) holiday_code
                    , holiday_name 
                FROM
                    public.tmm_holiday 
                WHERE
                    delete_flag = 0 
                ORDER BY
                    holiday_code
                    , activate_date DESC
            ) h 
                ON h.holiday_code = t1.holiday_type2 JOIN ( 
                    SELECT DISTINCT
                            ON (personal_id) personal_id
                        , employee_code 
                    FROM
                        public.pfm_human 
                    WHERE
                        delete_flag = 0 
                    ORDER BY
                        personal_id
                        , activate_date DESC
                ) ph 
                ON ph.personal_id = t1.personal_id 
        WHERE
            t1.delete_flag = 0 
            AND wf.workflow_status = '9' 
            AND wf.delete_flag = 0 
            AND t1.use_day = 0.5 
            AND t1.request_start_date >= :start_date
            AND t1.request_start_date <= :end_date 
        GROUP BY
            t1.request_start_date 
        HAVING
            COUNT(t1.personal_id) = 2
    ) bh
