SELECT payment_date
FROM public.salary_payments
WHERE target_year = :year
  AND target_month = :month
LIMIT 1;