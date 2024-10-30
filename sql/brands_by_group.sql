select
    g.group_id group_id,
    g.name group_name,
    g.content_type group_content_type,
    (SELECT COUNT(ug.*) FROM v3_users_groups ug WHERE ug.group_id = g.group_id) count_group_users,
    b.brand_id brand_id,
    b.name brand_name,
    b.content_type brand_content_type,
    (SELECT COUNT(ub.user_id) FROM v3_users_brands ub WHERE ub.brand_id = b.brand_id) count_brand_users,
    (SELECT COUNT(esd.*) FROM events_summary_daily esd WHERE esd.brand = b.name and esd.content_type = b.content_type and esd.event_date >= '2022-12-01') count_events_last_90d
from v3_brands b
left join v3_groups_brands gb
on gb.brand_id = b.brand_id
-- inner join v3_users_groups ug
-- on ug.group_id = gb.group_id
left join v3_groups g
on g.group_id = gb.group_id;

select
    p.*,
    b.*
from v3_providers_brands pb
inner join v3_brands b on b.brand_id = pb.brand_id
inner join v3_providers p on p.provider_id = pb.provider_id