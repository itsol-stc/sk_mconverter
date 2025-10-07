SELECT
    s.schedule_code,  -- カレンダコード
    sd.schedule_date,  -- 勤務日
    sd.work_type_code,  -- 勤務形態コード
    sd.works,  -- 勤務回数
    sd.remark,  -- 備考

    wti_workstart.work_type_item_value AS workstart,  -- 出勤時刻
    wti_workend.work_type_item_value AS workend,  -- 退勤時刻
    wti_worktime.work_type_item_value AS worktime,  -- 勤務時間
    wti_resttime.work_type_item_value AS resttime,  -- 休憩時間
    wti_reststart1.work_type_item_value AS reststart1,  -- 休憩1開始
    wti_restend1.work_type_item_value AS restend1,  -- 休憩1終了
    wti_reststart2.work_type_item_value AS reststart2,  -- 休憩2開始
    wti_restend2.work_type_item_value AS restend2,  -- 休憩2終了
    wti_reststart3.work_type_item_value AS reststart3,  -- 休憩3開始
    wti_restend3.work_type_item_value AS restend3,  -- 休憩3終了
    wti_reststart4.work_type_item_value AS reststart4,  -- 休憩4開始
    wti_restend4.work_type_item_value AS restend4,  -- 休憩4終了
    wti_frontstart.work_type_item_value AS frontstart,  -- 午前休開始
    wti_frontend.work_type_item_value AS frontend,  -- 午前休終了
    wti_backstart.work_type_item_value AS backstart,  -- 午後休開始
    wti_backend.work_type_item_value AS backend,  -- 午後休終了
    wti_overbefore.work_type_item_value AS overbefore,  -- 残前休憩
    wti_overper.work_type_item_value AS overper,  -- 残業休憩（〇時間〇分のうち）
    wti_overrest.work_type_item_value AS overrest,  -- 残業休憩（〇時間〇分）
    wti_halfrest.work_type_item_value AS halfrest,  -- 半日取得時休憩（〇時〇分まで勤務を行うと）
    wti_halfreststart.work_type_item_value AS halfreststart,  -- 半日取得時休憩（〇時〇分から）
    wti_halfrestend.work_type_item_value AS halfrestend,  -- 半日取得時休憩（〇時〇分までは休憩時間となる）
    wti_directstart.work_type_item_value AS directstart,  -- 直行開始
    wti_directend.work_type_item_value AS directend,  -- 直帰終了
    wti_excludenightrest.work_type_item_value AS excludenightrest,  -- 深夜休憩除外
    wti_short1start.work_type_item_value AS short1start,  -- 第1短時間開始
    wti_short1end.work_type_item_value AS short1end,  -- 第1短時間終了
    wti_short2start.work_type_item_value AS short2start,  -- 第2短時間開始
    wti_short2end.work_type_item_value AS short2end,  -- 第2短時間終了
    wti_autobefoverwork.work_type_item_value AS autobefoverwork  -- 自動前残業
FROM (
    -- カレンダテーブル
    SELECT t.*
    FROM tmm_schedule t
    WHERE
        t.delete_flag = 0
        AND t.inactivate_flag = 0
        AND t.activate_date = (
            SELECT MAX(a.activate_date)
            FROM tmm_schedule a
            WHERE
                a.schedule_code = t.schedule_code
                AND a.delete_flag = 0
                AND a.activate_date <= :target_date
        )
) s
LEFT JOIN (
    -- カレンダ日付テーブル
    SELECT d.*
    FROM tmm_schedule_date d
    WHERE
        d.delete_flag = 0
        AND d.inactivate_flag = 0
        AND d.activate_date = (
            SELECT MAX(d2.activate_date)
            FROM tmm_schedule_date d2
            WHERE
                d2.schedule_code = d.schedule_code
                AND d2.schedule_date = d.schedule_date
                AND d2.delete_flag = 0
                AND d2.activate_date <= :target_date
                AND schedule_date >= :termStart_date::date
                AND schedule_date <= :termEnd_date::date
        )
) sd
ON s.schedule_code = sd.schedule_code

LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'WorkStart'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_workstart ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'WorkEnd'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_workend ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'WorkTime'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_worktime ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestTime'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_resttime ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestStart1'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_reststart1 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestEnd1'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_restend1 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestStart2'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_reststart2 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestEnd2'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_restend2 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestStart3'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_reststart3 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestEnd3'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_restend3 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestStart4'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_reststart4 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'RestEnd4'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_restend4 ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'FrontStart'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_frontstart ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'FrontEnd'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_frontend ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'BackStart'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_backstart ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'BackEnd'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_backend ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'OverBefore'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_overbefore ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'OverPer'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_overper ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'OverRest'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_overrest ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'HalfRest'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_halfrest ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'HalfRestStart'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_halfreststart ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'HalfRestEnd'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_halfrestend ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'DirectStart'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_directstart ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'DirectEnd'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_directend ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'ExcludeNightRest'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_excludenightrest ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'Short1Start'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_short1start ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'Short1End'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_short1end ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'Short2Start'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_short2start ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'Short2End'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_short2end ON true
LEFT JOIN LATERAL (
  SELECT work_type_item_value
  FROM tmm_work_type_item t
  WHERE t.work_type_code = sd.work_type_code
    AND t.work_type_item_code = 'AutoBefOverWork'
    AND t.delete_flag = 0
    AND t.inactivate_flag = 0
    AND t.activate_date = (
      SELECT MAX(a.activate_date)
      FROM tmm_work_type_item a
      WHERE a.work_type_code = t.work_type_code
        AND a.work_type_item_code = t.work_type_item_code
        AND a.delete_flag = 0
        AND a.activate_date <= :target_date
    )
  LIMIT 1
) wti_autobefoverwork ON true
ORDER BY schedule_code, schedule_date