SELECT
    e.request_start_date
    , e.personal_id
    , e.request_reason
    , e.workflow
    , e.holiday_range
    , e.holiday_type1
    , e.holiday_type2
    , CASE e.holiday_type1 
        WHEN 1 THEN '有給休暇' 
        WHEN 2 THEN '特別休暇' 
        WHEN 3 THEN 'その他' 
        WHEN 4 THEN '欠勤' 
        ELSE '' 
        END AS holiday_type1_name
    , h.holiday_name AS holiday_type2_name
    , h.holiday_abbr 
FROM
    ( 
        SELECT
            t1.tmd_holiday_request_id
            , t1.personal_id
            , (gs) ::date AS request_start_date
            , (gs) ::date AS request_end_date
            , t1.holiday_type1
            , t1.holiday_type2
            , t1.holiday_range
            , (gs) ::timestamp AS start_time
            , (gs) ::timestamp AS end_time
            , t1.holiday_acquisition_date
            , t1.use_day
            , t1.use_hour
            , t1.request_reason
            , t1.workflow
            , t1.delete_flag
            , t1.insert_date
            , t1.insert_user
            , t1.update_date
            , t1.update_user 
        FROM
            public.tmd_holiday_request t1       -- 承認済みの休暇申請のみ対象にする
            JOIN public.pft_workflow wf 
                ON wf.workflow = t1.workflow 
                AND wf.workflow_status = '9' 
                AND wf.delete_flag = 0          -- 休暇の開始日～終了日を1日ごとに展開する（連続休暇を日単位に分解）
            CROSS JOIN LATERAL generate_series( 
                GREATEST(t1.request_start_date, :termStart_date ) -- 対象月の開始以降に制限
                , LEAST(t1.request_end_date, :termEnd_date ) -- 対象月の終了以前に制限
                , INTERVAL '1 day'              -- 1日刻みで生成
            ) AS gs 
        WHERE
            t1.delete_flag = 0 
            AND t1.request_start_date <= :termEnd_date -- 期間の上限条件
            AND t1.request_end_date >=  :termStart_date-- 期間の下限条件
    ) e                                         -- 休暇種別2コードに対応する最新のマスタ情報を取得
    LEFT JOIN ( 
        SELECT DISTINCT
                ON (holiday_code) holiday_code
            , holiday_name
            , holiday_abbr 
        FROM
            public.tmm_holiday 
        WHERE
            delete_flag = 0 
        ORDER BY
            holiday_code
            , activate_date DESC
    ) h 
        ON h.holiday_code = e.holiday_type2     -- 展開後の1日単位データを対象月に限定
WHERE
    e.request_start_date BETWEEN :termStart_date AND :termEnd_date
ORDER BY
    e.request_start_date
    , e.tmd_holiday_request_id;
