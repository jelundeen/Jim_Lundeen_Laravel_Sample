-- Please send me a simple spreadsheet showing all users with
-- those domains and which brands each has access to.

select distinct
    u.user_id user_id,
    u.email user_email,
    u.name user_name,
    u.is_active user_active,
    g.group_id group_id,
    g.name group_name,
    g.content_type group_content_type,
    b.brand_id brand_id,
    b.name brand_name,
    b.content_type brand_content_type
FROM v3_users u
left JOIN v3_users_groups ug
on ug.user_id = u.user_id
left JOIN v3_groups_brands gb
on gb.group_id = ug.group_id
left join v3_brands b
on b.brand_id = gb.brand_id
left join v3_groups g
on g.group_id = ug.group_id
where 1=1
and (
    u.email ilike '%cbs%' or
    u.email ilike '%viacom%' or
    u.email ilike '%smithsonian%'
    )
order by
    user_name,
    brand_name,
    brand_content_type
;