SELECT h.*
FROM tmm_holiday h
INNER JOIN (
    SELECT holiday_code, MAX(activate_date) AS max_date
    FROM tmm_holiday
    WHERE activate_date <= :target_date
      AND delete_flag != 1
    GROUP BY holiday_code
) m
  ON h.holiday_code = m.holiday_code
 AND h.activate_date = m.max_date
WHERE h.delete_flag != 1;