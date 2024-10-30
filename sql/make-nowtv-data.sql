select * from events_summary_daily e
where 1=1
and e.event_date between '2023-05-24' and '2023-05-24'
;

update events_summary_daily
set x1_user_type = ''
where 1=1
and event_date between '2023-05-26' and '2023-05-26'
and brand = 'A&E'
and pct100_viewed = 0
;

update events_summary_daily
set x1_user_type = 'introtv'
where 1=1
  and event_date between '2023-05-26' and '2023-05-28'
  and brand in ('A&E','NBC','CBS','FOX')
  and pct100_viewed = 0
;
update events_summary_daily
set x1_user_type = ''
where 1=1
  and event_date between '2023-05-26' and '2023-05-28'
  and brand in ('A&E','NBC','CBS','FOX')
  and pct100_viewed > 0
;

select * from v3_brands where name in (
'Weather Channel'
-- 'Stingray Music'
-- 'Xumo Play'
-- 'BBC News'
-- 'Discovery Channel'
-- 'Travel Channel'
);

sele