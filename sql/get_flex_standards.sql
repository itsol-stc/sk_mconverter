SELECT
    * 
FROM
    public.flex_standards
WHERE
    year = :target_year
    AND month = :target_month