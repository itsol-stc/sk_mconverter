SELECT
    t1.request_date
    , t1.personal_id
    , t1.work_type_code
    , t1.request_reason
    , t1.workflow
    , wti_workstart.work_type_item_value AS workstart
    , wti_workend.work_type_item_value AS workend
    , wti_worktime.work_type_item_value AS worktime
    , wti_resttime.work_type_item_value AS resttime
    , wti_reststart1.work_type_item_value AS reststart1
    , wti_restend1.work_type_item_value AS restend1
    , wti_reststart2.work_type_item_value AS reststart2
    , wti_restend2.work_type_item_value AS restend2
    , wti_reststart3.work_type_item_value AS reststart3
    , wti_restend3.work_type_item_value AS restend3
    , wti_reststart4.work_type_item_value AS reststart4
    , wti_restend4.work_type_item_value AS restend4
    , wti_frontstart.work_type_item_value AS frontstart
    , wti_frontend.work_type_item_value AS frontend
    , wti_backstart.work_type_item_value AS backstart
    , wti_backend.work_type_item_value AS backend
    , wti_overbefore.work_type_item_value AS overbefore
    , wti_overper.work_type_item_value AS overper
    , wti_overrest.work_type_item_value AS overrest
    , wti_halfrest.work_type_item_value AS halfrest
    , wti_halfreststart.work_type_item_value AS halfreststart
    , wti_halfrestend.work_type_item_value AS halfrestend
    , wti_directstart.work_type_item_value AS directstart
    , wti_directend.work_type_item_value AS directend
    , wti_excludenightrest.work_type_item_value AS excludenightrest
    , wti_short1start.work_type_item_value AS short1start
    , wti_short1end.work_type_item_value AS short1end
    , wti_short2start.work_type_item_value AS short2start
    , wti_short2end.work_type_item_value AS short2end
    , wti_autobefoverwork.work_type_item_value AS autobefoverwork 
FROM
    public.tmd_work_type_change_request t1 JOIN public.pft_workflow wf 
        ON t1.workflow = wf.workflow 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'WorkStart' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_workstart 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'WorkEnd' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_workend 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'WorkTime' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_worktime 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestTime' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_resttime 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestStart1' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_reststart1 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestEnd1' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_restend1 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestStart2' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_reststart2 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestEnd2' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_restend2 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestStart3' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_reststart3 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestEnd3' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_restend3 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestStart4' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_reststart4 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'RestEnd4' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_restend4 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'FrontStart' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_frontstart 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'FrontEnd' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_frontend 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'BackStart' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_backstart 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'BackEnd' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_backend 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'OverBefore' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_overbefore 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'OverPer' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_overper 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'OverRest' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_overrest 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'HalfRest' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_halfrest 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'HalfRestStart' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_halfreststart 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'HalfRestEnd' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_halfrestend 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'DirectStart' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_directstart 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'DirectEnd' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_directend 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'ExcludeNightRest' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_excludenightrest 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'Short1Start' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_short1start 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'Short1End' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_short1end 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'Short2Start' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_short2start 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'Short2End' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_short2end 
        ON true 
    LEFT JOIN LATERAL ( 
        SELECT
            work_type_item_value 
        FROM
            tmm_work_type_item t 
        WHERE
            t.work_type_code = t1.work_type_code 
            AND t.work_type_item_code = 'AutoBefOverWork' 
            AND t.delete_flag = 0 
            AND t.inactivate_flag = 0 
            AND t.activate_date = ( 
                SELECT
                    MAX(a.activate_date) 
                FROM
                    tmm_work_type_item a 
                WHERE
                    a.work_type_code = t.work_type_code 
                    AND a.work_type_item_code = t.work_type_item_code 
                    AND a.delete_flag = 0 
                    AND a.activate_date <= :target_date
            ) 
        LIMIT
            1
    ) wti_autobefoverwork 
        ON true 
WHERE
    t1.delete_flag = 0 
    AND wf.workflow_status = '9' 
    AND wf.delete_flag = 0 
    AND t1.insert_date = ( 
        SELECT
            MAX(t2.insert_date) 
        FROM
            public.tmd_work_type_change_request t2 
        WHERE
            t2.personal_id = t1.personal_id 
            AND t2.request_date = t1.request_date 
            AND t2.delete_flag = 0
    );
