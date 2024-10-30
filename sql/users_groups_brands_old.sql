-- Please send me a simple spreadsheet showing all users with
-- those domains and which brands each has access to.

select
    u.id as user_id,
    u.email as user_email,
    u.name as user_name,
    u.is_active as user_active,
    g.id as group_id,
    g.name group_name,
    'Live' as group_content_type,
    g.description
from users u
left join users_user_live_stream_group ug
    on ug.user_id = u.id
left join user_live_stream_group g
    on g.id = ug.user_live_stream_group_id
where 1=1
  and (
            u.email ilike '%cbs%' or
            u.email ilike '%viacom%' or
            u.email ilike '%smithsonian%'
    )
union all
select
    u.id as user_id,
    u.email as user_email,
    u.name as user_name,
    u.is_active as user_active,
    g.id as group_id,
    g.name group_name,
    'OnDemand' as group_content_type,
    g.description
from users u
         left join users_user_group ug
                   on ug.user_id = u.id
         left join user_group g
                   on g.id = ug.user_group_id
where 1=1
  and (
            u.email ilike '%cbs%' or
            u.email ilike '%viacom%' or
            u.email ilike '%smithsonian%'
    )
order by
    user_name,
    group_name,
    description