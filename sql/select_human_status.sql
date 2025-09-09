WITH latest_human AS (
            SELECT
                h.pfm_human_id,
                h.personal_id,
                h.activate_date,
                LPAD(h.employee_code, 7, '0') AS employee_code,
                h.last_name,
                h.first_name,
                h.last_kana,
                h.first_kana,
                h.employment_contract_code,
                h.section_code,
                h.position_code,
                h.work_place_code
            FROM
                pfm_human h
            JOIN (
                SELECT
                    pfm_human.personal_id,
                    MAX(pfm_human.activate_date) AS max_activate_date
                FROM
                    pfm_human
                WHERE
                    pfm_human.delete_flag = 0
                    AND pfm_human.activate_date <= :target_date
                GROUP BY
                    pfm_human.personal_id
            ) latest
                ON h.personal_id = latest.personal_id
                AND h.activate_date = latest.max_activate_date
            WHERE
                h.delete_flag = 0 AND work_place_code <> '9999'
        ),
        entrance AS (
            SELECT
                e1.personal_id,
                MAX(e1.entrance_date) AS entrance_date
            FROM
                pfa_human_entrance e1
            WHERE
                e1.delete_flag = 0
                AND e1.entrance_date <= :target_date
            GROUP BY
                e1.personal_id
        ),
        suspension AS (
            SELECT DISTINCT
                s.personal_id
            FROM
                pfa_human_suspension s
            WHERE
                s.delete_flag = 0
                AND s.start_date <= :target_date
                AND (
                    (s.end_date IS NULL AND s.schedule_end_date >= :target_date)
                    OR (s.end_date IS NOT NULL AND s.end_date >= :target_date)
                )
        ),
        retirement AS (
            SELECT
                r.personal_id
            FROM
                pfa_human_retirement r
            WHERE
                r.delete_flag = 0
                AND r.retirement_date < :target_date
        ),
        latest_section AS (
            SELECT
                s1.section_code,
                s1.section_name,
                s1.activate_date,
                s1.section_abbr
            FROM
                pfm_section s1
            JOIN (
                SELECT
                    section_code,
                    MAX(activate_date) AS max_activate_date
                FROM
                    pfm_section
                WHERE
                    delete_flag = 0
                    AND activate_date <= :target_date
                GROUP BY
                    section_code
            ) latest
                ON s1.section_code = latest.section_code
                AND s1.activate_date = latest.max_activate_date
            WHERE
                s1.delete_flag = 0
        ),
        latest_position AS (
            SELECT
                p1.position_code,
                p1.position_name,
                p1.activate_date
            FROM
                pfm_position p1
            JOIN (
                SELECT
                    position_code,
                    MAX(activate_date) AS max_activate_date
                FROM
                    pfm_position
                WHERE
                    delete_flag = 0
                    AND activate_date <= :target_date
                GROUP BY
                    position_code
            ) latest
                ON p1.position_code = latest.position_code
                AND p1.activate_date = latest.max_activate_date
            WHERE
                p1.delete_flag = 0
        ),
        latest_employment_contract AS (
            SELECT
                ec1.employment_contract_code,
                ec1.employment_contract_name,
                ec1.activate_date
            FROM
                pfm_employment_contract ec1
            JOIN (
                SELECT
                    employment_contract_code,
                    MAX(activate_date) AS max_activate_date
                FROM
                    pfm_employment_contract
                WHERE
                    delete_flag = 0
                    AND activate_date <= :target_date
                GROUP BY
                    employment_contract_code
            ) latest
                ON ec1.employment_contract_code = latest.employment_contract_code
                AND ec1.activate_date = latest.max_activate_date
            WHERE
                ec1.delete_flag = 0
        ),
        latest_work_place AS (
            SELECT
                wp1.work_place_code,
                wp1.work_place_name,
                wp1.activate_date
            FROM
                pfm_work_place wp1
            JOIN (
                SELECT
                    work_place_code,
                    MAX(activate_date) AS max_activate_date
                FROM
                    pfm_work_place
                WHERE
                    delete_flag = 0
                    AND activate_date <= :target_date
                GROUP BY
                    work_place_code
            ) latest
                ON wp1.work_place_code = latest.work_place_code
                AND wp1.activate_date = latest.max_activate_date
            WHERE
                wp1.delete_flag = 0
        )
        SELECT
            l.pfm_human_id,
            l.personal_id,
            l.activate_date,
            e.entrance_date,
            l.employee_code,
            l.last_name,
            l.first_name,
            l.last_kana,
            l.first_kana,
            l.employment_contract_code,
            ec.employment_contract_name,
            l.section_code,
            sec.section_name,
            l.position_code,
            pos.position_name,
            l.work_place_code,
            wp.work_place_name,
            sec.section_abbr
        FROM latest_human l 
        LEFT JOIN entrance e ON l.personal_id = e.personal_id 
        LEFT JOIN suspension s ON l.personal_id = s.personal_id 
        LEFT JOIN retirement r ON l.personal_id = r.personal_id 
        LEFT JOIN latest_section sec ON l.section_code = sec.section_code 
        LEFT JOIN latest_position pos ON l.position_code = pos.position_code 
        LEFT JOIN latest_employment_contract ec ON l.employment_contract_code = ec.employment_contract_code 
        LEFT JOIN latest_work_place wp ON l.work_place_code = wp.work_place_code 
        WHERE e.personal_id IS NOT NULL
            AND s.personal_id IS NULL
            AND r.personal_id IS NULL
        ORDER BY l.employee_code