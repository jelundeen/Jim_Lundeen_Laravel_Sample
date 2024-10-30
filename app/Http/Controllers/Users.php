<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBrand;
use App\Models\UserGroup;
use App\Models\UserRole;
use App\Transformers\RoleTransformer;
use App\Transformers\UserDetailedTransformer;
use App\Transformers\UserTransformer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Serializer\ArraySerializer;
use stdClass;

class Users extends Controller
{
    public function __construct()
    {
        $this->middleware('checktoken')->except([
            //'login',
        ]);
    }

    public function users(array $params = []) {
        $request = new Request();

        $query = User::query();
        if(isset($params['query']['where'])) {
            foreach ($params['query']['where'] as $where) {
                $query->where($where['field'], $where['operator'], $where['value']);
            }
        }

//        return $query
//            ->get()
//            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

        $perPage = isset($params['perPage']) ?: $request->input('perPage', 3);
        $paginator = $query->paginate( $perPage);

        $collection = $paginator->getCollection();

        $response = fractal()
            ->collection($collection, new UserDetailedTransformer())
            ->serializeWith(new ArraySerializer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))
            //->toArray()['data']
        ;
        return response()->json($response);
    }



    //GET    /users                                      controllers.Users.getAllUsers(request: Request)
    public function getAllUsers(Request $request)
    {
        return $this->users();
    }

    //PUT    /users                                      controllers.Users.updateUser(request: Request)
    public function updateUser(Request $request)
    {
        /*
         *
            {
                "vodBrandsConcat": "8919|8919,186|186,9218|9218",
                "name": "Jim Lundeen API",
                "liveStreamBrandsConcat": "8284,8739,8001",
                "created_by_id": null,
                "modified_by_id": null,
                "creation_date": "2022-11-10T20:57:02.605000Z",
                "user_id": 1401,
                "rolesConcat": "API User",
                "modification_date": "2022-11-10T21:16:53.947000Z",
                "vodGroupsConcat": "46,34",
                "liveStreamGroupsConcat": "70",
                "is_active": true,
                "email": "jlundeen+api@insightrocket.com",
                "modifiedByToken": "6ZLkoj90WzVyMNYK1PdG0Ry8nfKqufdwRTjOWlD1"
            }
        */
        $user = User::findOrFail($request->input('user_id'));

        $roles = [];
        if (!empty($request->input('rolesConcat'))) {
            $role_names = explode(',', $request->input('rolesConcat'));
            //Log::debug('role_names: ' . print_r($role_names, true));
            $user->updateRolesByName($role_names);
        }


//        if($user->hasRoleByName('Administrator') && $roles[0] !== 'Administrator') {
//            UserBrand::query()
//                ->where('user_id', '=', $user->user_id)
//                ->delete();
//            UserGroup::query()
//                ->where('user_id', '=', $user->user_id)
//                ->delete();
//        }

        $upd = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'modified_by_id' => auth()->user()->user_id,
            'modification_date' => new DateTime()
        ];
        //Log::debug('upd: ' . print_r($upd, true));
        $user->update($upd);

        /// handle addition and removal of UserBrand pivot table entries
        $brand_ids = array();
        $brand_ids = array_merge($brand_ids, explode(',', $request->input('vodBrandsConcat')));
        $brand_ids = array_merge($brand_ids, explode(',', $request->input('liveStreamBrandsConcat')));
        // our brand_ids entries currently look like 1234|1234, we need to fix to just 1234
        $brand_ids = array_map('intval', $brand_ids);
        $user->updateBrands($brand_ids);

        /// handle addition and removal of UserGroup pivot table entries
        $group_ids = array();
        $group_ids = array_merge($group_ids, explode(',', $request->input('vodGroupsConcat')));
        $group_ids = array_merge($group_ids, explode(',', $request->input('liveStreamGroupsConcat')));
        $user->updateGroups($group_ids);

        // handle Role change
        $roles = explode(',', $request->input('rolesConcat'));
        //Log::debug('roles: '. print_r($roles, true));
        $user->updateRolesByName($roles);

        return response('OK', 204);
    }

    //DELETE /users                                      controllers.Users.deleteUserById(request: Request)
    public function deleteUserById(Request $request)
    {
        return __METHOD__;
    }

    //GET    /users/roles                                controllers.Users.getUserRoles(request: Request)
    public function getUserRoles(Request $request)
    {
        return fractal()
            ->collection(
                Role::all()
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE), new RoleTransformer())
            ->toArray()['data'];
    }

    //POST   /users/paged                                controllers.Users.getPagedUsers(request: Request)
    public function getPagedUsers(Request $request)
    {
        return $this->users();
    }

    //POST   /users/pagecount                            controllers.Users.getUserPageCount(request: Request)
    public function getUserPageCount(Request $request)
    {
        return __METHOD__;
    }

    //POST   /users/find                                 controllers.Users.findUsersByNameOrEmail(request: Request)
    public function findUsersByNameOrEmail(Request $request)
    {
        return $this->users([
            'query' => [
                'where' => [
                    [
                        'field' => 'name',
                        'operator' => 'ILIKE',
                        'value' => '%' . $request->input('name') . '%'
                    ],
                    [
                        'field' => 'email',
                        'operator'=> 'ILIKE',
                        'value' => '%' . $request->input('name ') . '%'
                    ]
                ]
            ]
        ]);
    }


    public static function groups(int $id)
    {
        return User::find($id)->groups;
    }

    public static function roles(int $id)
    {
        return Role::all()->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE);
    }

    public static function findByEmailAndActivity(string $email, bool $is_active = true)
    {
        return User::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$email])
            ->where('is_active', $is_active)
            ->first();
    }

    public function DataTablesPost(Request $request) {
        $logger_message = 'DataTablesPost';
        logger($logger_message . ': begin');

        $rows = $request->request->get('data');
        logger($logger_message . ': rows: count: '. count($rows));
        logger($logger_message . ': rows: '. print_r($rows, true));

        $rows_updated = [];
        logger($logger_message. ': rows_updated: initialized');

        foreach ($rows as $user_id => $row) {
            logger($logger_message . ': row: '. print_r($row, true));
            $user = null;
            if ($user_id>0) {
                $user = User::find($user_id);
                if ($user) {
                    logger($logger_message. ': user: found: ' . print_r($user, true));
                } else {
                    logger($logger_message. ': user: not found');
                    return response('User not found', 404);
                }
            } else {
                if($row['email']) {
                    logger($logger_message. ': user: create');
                    $user = new User();
                    $user->email = $row['email'];
                    $user->creation_date = date('Y-m-d H:i:s');
                    $user->created_by_id = Auth::user()->user_id;
                    $user->save();
                    $user = User::where('email',$row['email'])->first();;
                    logger($logger_message. ': user: created: '. print_r($user, true));
                    if ($user) {
                        $user_id = $user->user_id;
                        logger($logger_message. ': user: create: success');

                        if(isset($row['send_welcome_email'])) {
                            Authentication::sendWelcomeEmail($user->email);
                        }

                    } else {
                        logger($logger_message. ': user: create: failure');
                        return response('Email is required', 422);
                    }
                } else {
                    logger($logger_message. ': user: create: failure: missing: email');
                    return response('Email is required', 422);
                }
            }

            if ($user) {
                logger($logger_message. ': user: save');
                $user->name = $row['name'];
                $user->is_active = $row['is_active'];
                $saved = $user->save();
                if($saved) {
                    logger($logger_message. ': user: save: success');
                } else {
                    logger($logger_message. ': user: save: failure');
                    return response('User not saved', 422);
                }
            }

            // update role for this user_id
            if(isset($row['role'])) {
                logger($logger_message. ': role: update');
                $updated = UserRole::where('user_id', $user_id)->update(['role_id' => $row['role']]);
                if($updated) {
                    logger($logger_message. ': role: update: success');
                } else {
                    logger($logger_message. ': role: update: no prior role found');
                    logger($logger_message. ': role: insert');
                    $inserted = UserRole::insert(['user_id' => $user_id, 'role_id' => $row['role']]);
                    if($inserted) {
                        logger($logger_message. ': role: insert: success');
                    } else {
                        logger($logger_message. ': role: insert: failure');
                        return response('Role insert failed', 422);
                    }
                }
            }

            // update brands
            if(1==1) {
                // update brands for this user_id
                foreach (['OnDemand' => 'vod_brands', 'Live' => 'live_stream_brands'] as $content_type => $content_type_field) {
                    logger($logger_message . ': brands: content_type: ' . $content_type);

                    $old_brands_ids = $user->users_brands_ids($content_type);
                    logger($logger_message . ': brands: content_type: ' . $content_type . ': old_brands_ids: ' . print_r($old_brands_ids, true));

                    $brands_ids = isset($row[$content_type_field]) ? $row[$content_type_field] : [];
                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_ids: ' . print_r($brands_ids, true));

                    if (count($brands_ids) > 0) {
                        if (empty(array_diff($old_brands_ids, $brands_ids)) && empty(array_diff($brands_ids, $old_brands_ids))) {
                            logger($logger_message . ': brands: content_type: ' . $content_type . ': no change detected');
                        } else {
                            foreach (array_diff($brands_ids, $old_brands_ids) as $brand_id) {
                                logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': pivot record: insert');
                                $inserted = UserBrand::insert(['user_id' => $user_id, 'brand_id' => $brand_id]);
                                if ($inserted) {
                                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': insert: success');
                                } else {
                                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': insert failure');
                                    return response('Brand insert failed', 422);
                                }
                            }
                            foreach (array_diff($old_brands_ids, $brands_ids) as $brand_id) {
                                // not only must we remove the pivot record for brand_id, but also
                                // any pivot records associated with possible duplicate brands of the same
                                // name and content_type as the brand associated with brand_id.
                                $brand = Brand::where('brand_id', $brand_id)->first();
                                if ($brand) {
                                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': brand: found');
                                } else {
                                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': brand: not found');
                                    return response('Brand not found', 422);
                                }
                                $brands = Brand::where('name', $brand->name)
                                    ->where('content_type', $brand->content_type)
                                    ->get();
                                foreach ($brands as $brand) {
                                    logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': pivot record: delete');
                                    $deleted = UserBrand::where('user_id', $user_id)
                                        ->where('brand_id', $brand->brand_id)
                                        ->delete();
                                    if ($deleted) {
                                        logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': pivot record: delete: success');
                                    } else {
                                        logger($logger_message . ': brands: content_type: ' . $content_type . ': brand_id: ' . $brand_id . ': pivot record: delete: failure');
                                        return response('Brand remove failed', 422);
                                    }
                                }
                            }
                        }
                    } else {
                        // if no brands specified for this user_id ...
                        logger($logger_message . ': brands: content_type: ' . $content_type . ': no brands specified');
                        $query = UserBrand::query();
                        $query->selectRaw("DISTINCT ub.brand_id")
                            ->fromRaw('v3_users_brands' . ' AS ub')
                            ->join('v3_brands AS b', 'ub.brand_id', '=', 'b.brand_id')
                            ->where('ub.user_id', $user_id)
                            ->where('b.content_type', $content_type);
                        $unused_brands_ids = $query->pluck('brand_id')->toArray();
                        if (count($unused_brands_ids) > 0) {
                            logger($logger_message . ': brands: content_type: ' . $content_type . ': pivot records: delete');
                            $removed = UserBrand::where('user_id', $user_id)->whereIn('brand_id', $unused_brands_ids)->delete();
                            if ($removed) {
                                logger($logger_message . ': brands: content_type: ' . $content_type . ': pivot records: delete: success');
                            } else {
                                logger($logger_message . ': brands: content_type: ' . $content_type . ': pivot records: delete: failure');
                                return response('Pivot delete failed', 422);
                            }
                        } else {
                            logger($logger_message . ': brands: content_type: ' . $content_type . ': pivot records: none found');
                        }
                    }
                }
            }

            // update groups
            if(1==1) {
                // update groups for this user_id
                foreach (['OnDemand' => 'vod_groups', 'Live' => 'live_stream_groups'] as $content_type => $content_type_field) {
                    logger($logger_message . ': groups: content_type: ' . $content_type);

                    $old_groups_ids = $user->users_groups_ids($content_type);
                    logger($logger_message . ': groups: content_type: ' . $content_type . ': old_groups_ids: ' . print_r($old_groups_ids, true));

                    $groups_ids = isset($row[$content_type_field]) ? $row[$content_type_field] : [];
                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_ids: ' . print_r($groups_ids, true));

                    if (count($groups_ids) > 0) {
                        if (empty(array_diff($old_groups_ids, $groups_ids)) && empty(array_diff($groups_ids, $old_groups_ids))) {
                            logger($logger_message . ': groups: content_type: ' . $content_type . ': no change detected');
                        } else {
                            foreach (array_diff($groups_ids, $old_groups_ids) as $group_id) {
                                logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': pivot record: insert');
                                $inserted = UserGroup::insert(['user_id' => $user_id, 'group_id' => $group_id]);
                                if ($inserted) {
                                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': insert: success');
                                } else {
                                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': insert failure');
                                    return response('Group insert failed', 422);
                                }
                            }
                            foreach (array_diff($old_groups_ids, $groups_ids) as $group_id) {
                                // not only must we remove the pivot record for group_id, but also
                                // any pivot records associated with possible duplicate groups of the same
                                // name and content_type as the group associated with group_id.
                                $group = Group::where('group_id', $group_id)->first();
                                if ($group) {
                                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': group: found');
                                } else {
                                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': group: not found');
                                    return response('Group not found', 422);
                                }
                                $groups = Group::where('name', $group->name)
                                    ->where('content_type', $group->content_type)
                                    ->get();
                                foreach ($groups as $group) {
                                    logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': pivot record: delete');
                                    $deleted = UserGroup::where('user_id', $user_id)
                                        ->where('group_id', $group->group_id)
                                        ->delete();
                                    if ($deleted) {
                                        logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': pivot record: delete: success');
                                    } else {
                                        logger($logger_message . ': groups: content_type: ' . $content_type . ': group_id: ' . $group_id . ': pivot record: delete: failure');
                                        return response('Group remove failed', 422);
                                    }
                                }
                            }
                        }
                    } else {
                        // if no groups specified for this user_id ...
                        logger($logger_message . ': groups: content_type: ' . $content_type . ': no groups specified');
                        $query = UserGroup::query();
                        $query->selectRaw("DISTINCT ug.group_id")
                            ->fromRaw('v3_users_groups' . ' AS ug')
                            ->join('v3_groups AS g', 'ug.group_id', '=', 'g.group_id')
                            ->where('ug.user_id', $user_id)
                            ->where('g.content_type', $content_type);
                        $unused_groups_ids = $query->pluck('group_id')->toArray();
                        if (count($unused_groups_ids) > 0) {
                            logger($logger_message . ': groups: content_type: ' . $content_type . ': pivot records: delete');
                            $removed = UserGroup::where('user_id', $user_id)->whereIn('group_id', $unused_groups_ids)->delete();
                            if ($removed) {
                                logger($logger_message . ': groups: content_type: ' . $content_type . ': pivot records: delete: success');
                            } else {
                                logger($logger_message . ': groups: content_type: ' . $content_type . ': pivot records: delete: failure');
                                return response('Pivot delete failed', 422);
                            }
                        } else {
                            logger($logger_message . ': groups: content_type: ' . $content_type . ': pivot records: none found');
                        }
                    }
                }
            }

            // return the updated composite "user" record
            $row_updated = $this->DataTablesGet($request, $user_id)->data[0];
            logger('row_updated: '. print_r($row_updated, true));
            $row_updated['DT_RowId'] = $user_id;
            $rows_updated[] = $row_updated;
        }

        $resp = new stdClass();
        $resp->data = [];
        foreach($rows_updated as $user) {
            $resp->data[] = $user;
        }
        logger($logger_message . ': end');

        return $resp;


    }

    public function DataTablesGet(Request $request, $user_id = null) {
        /*
            Email
            Name
            VOD Brands
            VOD Groups
            Live Stream Brands
            Live Stream Groups
            User Role
         */
        $query = "
            SELECT DISTINCT
                u.user_id,
                u.name,
                u.email,
                u.is_active,
                g.group_id,
                g.name group_name,
                g.content_type group_content_type,
                b.brand_id,
                b.name brand_name,
                b.content_type brand_content_type,
                r.role_id,
                r.name role_name,
                r.label role_label
            FROM v3_users u
            LEFT JOIN v3_users_groups ug ON ug.user_id = u.user_id
            LEFT JOIN v3_users_brands ub ON ub.user_id = u.user_id
            LEFT JOIN v3_brands b ON b.brand_id = ub.brand_id
            LEFT JOIN v3_groups g ON g.group_id = ug.group_id
            LEFT JOIN v3_users_roles ur ON ur.user_id = u.user_id
            LEFT JOIN v3_roles r ON r.role_id = ur.role_id
        ";
        if(!empty($user_id)) {
            $query .= "WHERE u.user_id = $user_id";
        }
        $query .= "
            ORDER BY u.user_id, g.name ASC, b.name ASC
        ";
        $rows = DB::select($query);

        $users = [];
        for ($i = 0; $i < count($rows); $i++) {
            $row = (array)$rows[$i];
            $user = null;
            if(isset($users[$row['user_id']])) {
                $user = $users[$row['user_id']];
            } else {
                $user = [
                    'id' => $row['user_id'],
                    'is_active' => $row['is_active'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => (object) [
                        'id' => $row['role_id'],
                        'name' => $row['role_name'],
                        'label' => $row['role_label']
                    ],
                    'vod_brands' => [],
                    'vod_groups' => [],
                    'live_stream_brands' => [],
                    'live_stream_groups' => []
                ];
            }
            if(!empty($row['brand_id'])) {
                if($row['brand_content_type'] == 'OnDemand') {
                    $user['vod_brands'][$row['brand_id']] = array(
                        'id' => $row['brand_id'],
                        'name' => $row['brand_name'],
                        'content_type' => $row['brand_content_type']
                    );
                }
                if($row['brand_content_type'] == 'Live') {
                    $user['live_stream_brands'][$row['brand_id']] = array(
                        'id' => $row['brand_id'],
                        'name' => $row['brand_name'],
                        'content_type' => $row['brand_content_type']
                    );
                }
            }
            if(!empty($row['group_id'])) {
                if($row['group_content_type'] == 'OnDemand') {
                    $user['vod_groups'][$row['group_id']] = array(
                        'id' => $row['group_id'],
                        'name' => $row['group_name'],
                        'content_type' => $row['group_content_type']
                    );
                }
                if($row['group_content_type'] == 'Live') {
                    $user['live_stream_groups'][$row['group_id']] = array(
                        'id' => $row['group_id'],
                        'name' => $row['group_name'],
                        'content_type' => $row['group_content_type']
                    );
                }
            }
            $users[$row['user_id']] = $user;
        }

        /*
             {
              "data": [
                {
                  "id": "1",
                  "name": "Tiger Nixon",
                  "position": "System Architect",
                  "salary": "$320,800",
                  "start_date": "2011/04/25",
                  "office": "Edinburgh",
                  "extn": "5421"
                },
         */

        foreach($users as $user) {
            if(isset($user['vod_brands'])) {
                foreach ($user['vod_brands'] as $vod_brand) {
                    $users[$user['id']]['vod_brands2'][] = $vod_brand;
                }
                if(isset($users[$user['id']]['vod_brands2'])){
                    $users[$user['id']]['vod_brands'] = $users[$user['id']]['vod_brands2'];
                    unset($users[$user['id']]['vod_brands2']);
                }
            }
//            else {
//                $users[$user['id']]['vod_brands'] = [];
//            }
            if(isset($user['live_stream_brands'])) {
                foreach ($user['live_stream_brands'] as $vod_brand) {
                    $users[$user['id']]['live_stream_brands2'][] = $vod_brand;
                }
                if(isset($users[$user['id']]['live_stream_brands2'])) {
                    $users[$user['id']]['live_stream_brands'] = $users[$user['id']]['live_stream_brands2'];
                    unset($users[$user['id']]['live_stream_brands2']);
                }
            }
//            else {
//                $users[$user['id']]['live_stream_brands'] = [];
//            }
            if(isset($user['vod_groups'])) {
                foreach ($user['vod_groups'] as $vod_brand) {
                    $users[$user['id']]['vod_groups2'][] = $vod_brand;
                }
                if(isset($users[$user['id']]['vod_groups2'])) {
                    $users[$user['id']]['vod_groups'] = $users[$user['id']]['vod_groups2'];
                    unset($users[$user['id']]['vod_groups2']);
                }
            }
//            else {
//                $users[$user['id']]['vod_groups'] = [];
//            }
            if(isset($user['live_stream_groups'])) {
                foreach ($user['live_stream_groups'] as $vod_brand) {
                    $users[$user['id']]['live_stream_groups2'][] = $vod_brand;
                }
                if(isset($users[$user['id']]['live_stream_groups2'])) {
                    $users[$user['id']]['live_stream_groups'] = $users[$user['id']]['live_stream_groups2'];
                    unset($users[$user['id']]['live_stream_groups2']);
                }
            }
//            else {
//                $users[$user['id']]['live_stream_groups'] = [];
//            }
        }

        $resp = new stdClass();
        $resp->data = [];
        foreach($users as $user) {
            $resp->data[] = $user;
        }
        return $resp;
    }

    public function Select2(Request $request) {
        $rows = User::all();
        $items = [];
        foreach($rows as $row) {
            $item = [
                'label' => $row['name'] . ' ('. $row['email']. ')',
                'value' => $row['user_id'],
            ];
            $items[] = (object)$item;
        }
        return $items;
    }


}
