SELECT
    * 
FROM
    public.contract_worktime 
WHERE
    year = :target_year
    AND month = :target_month
