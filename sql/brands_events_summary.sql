select
    es.brand,
    min(es.event_date) min_event_date,
    max(es.event_date) max_event_date,
    es.content_type,
    count(es.*) event_count
from events_summary_daily es
where 1=1
and event_date >= '2022-01-01'
group by es.brand, es.content_type

