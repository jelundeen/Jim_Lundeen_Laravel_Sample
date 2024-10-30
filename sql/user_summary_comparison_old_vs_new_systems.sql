select *
from v3_users
where name = 'Phil Seiler'
; -- user_id = 1493

select *
from users_230223
where email = 'sam.redlitz@tastemade.com'
;

select *
from v3_users_groups
where user_id = 1493;

select
    u.user_id,
    u.name,
    u.email,
    case when u.is_active then 'Yes' else 'No' end is_active,
    case when u.password is not null then 'Yes' else 'No' end password_set,
    (SELECT COUNT(our.*) FROM users_user_role our INNER JOIN users_230223 ou on ou.id = our.user_id and ou.email = u.email) old_count_roles,
    (SELECT COUNT(ur.*) FROM v3_users_roles AS ur WHERE ur.user_id = u.user_id) new_count_roles,
    (SELECT COUNT(oug.*) FROM users_user_group oug INNER JOIN users_230223 ou on ou.id = oug.user_id and ou.email = u.email) old_count_groups,
    (SELECT COUNT(ug.*) FROM v3_users_groups AS ug WHERE ug.user_id = u.user_id) new_count_groups,
    (SELECT COUNT(oub.*) FROM users_brands oub INNER JOIN users_230223 ou on ou.id = oub.user_id and ou.email = u.email) old_count_explicit_brands,
    (SELECT COUNT(ub.*) FROM v3_users_brands AS ub WHERE ub.user_id = u.user_id) new_count_explicit_brands
from v3_users u
;

select *
from users_230223 ou
where ou.email = 'Phil.Seiler@cbs.com';
-- id = 418

