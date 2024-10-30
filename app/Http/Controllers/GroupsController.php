<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Group;
use App\Models\GroupBrand;
use App\Models\User;
use App\Models\UserBrand;
use App\Models\UserGroup;
use App\Models\UserRole;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PHPUnit\TextUI\XmlConfiguration\Groups;
use stdClass;

class GroupsController extends Controller
{
    public function __construct() {
        $this->middleware('checktoken');
    }

    public function index($content_type, $format) {
        if(!in_array(strtolower($content_type), ['ondemand', 'live','all'])) {
            return response('',500);
        }
        $method = 'format'. ucfirst(strtolower($format));
        if(method_exists($this, $method)) {
            return self::$method($content_type);
        }
        return response('',500);
    }

    public function store(Request $request ) {
        $logger_message = 'DataTables GroupsController::store';
        logger($logger_message . ': begin');

        $rows = $request->input('data');
        logger($logger_message . ': rows: count: '. count($rows));
        logger($logger_message . ': rows: '. print_r($rows, true));

        /*
            data[0][id]:
            data[0][name]: Test Group 1
            data[0][content_type]: Live
            data[0][brands][]: 7851
            data[0][brands][]: 7889
            data[0][users][]: 1405
            data[0][users][]: 849
            action: create
         */

      //  return response('sorry error', 500);

       // return 1;
//        $rows_updated = [];
//        logger($logger_message. ': rows_updated: initialized');

        foreach ($rows as $group_id => $row) {
            if(!isset($row['users'])) {
                $row['users'] = [];
            }
            if(!isset($row['brands'])) {
                $row['brands'] = [];
            }
            logger($logger_message . ': row: '. print_r($row, true));
            $group = null;
            if ($group_id>0) {
                $group = Group::find($group_id);
                $group->update($row);
                if ($group) {
                    logger($logger_message. ': group: found: ' . print_r($group, true));
                } else {
                    logger($logger_message. ': group: not found');
                    return response('Group not found', 404);
                }
            } else {
                if(!empty(trim($row['name']))) {
                    logger($logger_message. ': group: create');
                    $group = new Group();
                    $group->name = trim($row['name']);
                    $group->content_type = $row['content_type'];
                    $group->save();
                    $group = Group::query()
                        ->where('name', $group->name)
                        ->where('content_type', $row['content_type'])
                        ->first();
                    logger($logger_message. ': group: created: group_id: '. $group->group_id);
                } else {
                    logger($logger_message. ': group: create: failure: missing: name');
                    return response('Name is required', 422);
                }
            }
            $brands = $group->wasRecentlyCreated ?
                [] :
                GroupBrand::query()
                    ->where('group_id', '=', $group->group_id)
                    ->pluck('brand_id')
                    ->toArray();
            if (empty(array_diff($brands, $row['brands'])) && empty(array_diff($row['brands'], $brands))) {
                logger($logger_message . ': brands: no change detected');
            }
            else {
                foreach (array_diff($row['brands'], $brands) as $brand_id) {
                    logger($logger_message . ': brands: brand_id: ' . $brand_id . ': pivot record: insert');
                    $inserted = GroupBrand::insert(['group_id' => $group->group_id, 'brand_id' => $brand_id]);
                    if ($inserted) {
                        logger($logger_message . ': brands: brand_id: ' . $brand_id . ': insert: pivot record insert success');
                    } else {
                        logger($logger_message . ': brands: brand_id: ' . $brand_id . ': insert: pivot record insert failure');
                        return response('Brand insert failed', 422);
                    }
                }
                foreach (array_diff($brands, $row['brands']) as $brand_id) {
                    $deleted = GroupBrand::query()
                        ->where('group_id', '=', $group->group_id)
                        ->where('brand_id', '=', $brand_id)
                        ->delete();
                    if (!$deleted) {
                        logger($logger_message. ': brands: brand_id: Failed to remove pivot record for brand_id: '. $brand_id);
                        return response('Brand remove failed', 422);
                    }
                }
            }
            $users = $group->wasRecentlyCreated ?
                [] :
                UserGroup::query()
                    ->where('group_id', $group->group_id)
                    ->pluck('user_id')
                    ->toArray();
            if (empty(array_diff($users, $row['users'])) && empty(array_diff($row['users'], $users))) {
                logger($logger_message . ': users: no change detected');
            }
            else {
                $groups = Group::query()
                    ->where('name', $group->name)
                    ->where('content_type', $group->content_type)
                    ->pluck('group_id')
                    ->toArray();
                foreach (array_diff($row['users'], $users) as $user_id) {
                    logger($logger_message . ': users: user_id: ' . $user_id . ': pivot record: insert');
                    $inserted = UserGroup::insert(['group_id' => $group->group_id, 'user_id' => $user_id]);
                    if ($inserted) {
                        logger($logger_message . ': users: user_id: ' . $user_id . ': insert: pivot record insert success');
                    } else {
                        logger($logger_message . ': users: user_id: ' . $user_id . ': insert: pivot record insert failure');
                        return response('User pivot record insert failed', 422);
                    }
                }
                foreach (array_diff($users, $row['users']) as $user_id) {
                    UserGroup::query()
                        ->where('user_id', $user_id)
                        ->whereIn('group_id', $groups)
                        ->delete();
                }
            }
            $row_updated = $this->formatDatatables($group->content_type, $group->group_id)->data[0];
            $row_updated['DT_RowId'] = $group_id;
            $rows_updated[] = $row_updated;
        }
        $resp = (object)[];
        $resp->data = [];
        foreach($rows_updated as $row) {
            $resp->data[] = $row;
        }
        return $resp;
    }

    public function formatDatatables($content_type, $group_id = null) {
        if(!in_array(strtolower($content_type), ['ondemand', 'live','all'])) {
            return response('',500);
        }
        if($content_type == 'all') {
            $content_type = '%';
        }
        $query = "
            SELECT DISTINCT
                g.group_id,
                g.name group_name,
                g.content_type group_content_type,
                b.brand_id,
                b.name brand_name,
                b.content_type brand_content_type,
                u.user_id,
                u.name user_name,
                u.email user_email,
                u.is_active user_is_active
            FROM v3_groups g
            LEFT JOIN v3_groups_brands gb ON gb.group_id = g.group_id
            LEFT JOIN v3_brands b ON b.brand_id = gb.brand_id
            LEFT JOIN v3_users_groups ug ON ug.group_id = g.group_id
            LEFT JOIN v3_users u ON u.user_id = ug.user_id
            WHERE 1=1
            AND g.content_type ilike '$content_type'
        ";
        if(!empty($group_id)) {
            $query .= "AND g.group_id = $group_id";
        }
        $query .= "
            ORDER BY group_name ASC, group_content_type ASC, brand_name ASC, user_name ASC
        ";
        $rows = DB::select($query);

        $groups = [];
        for ($i = 0; $i < count($rows); $i++) {
            $row = (array)$rows[$i];
            $group = null;
            if(isset($groups[$row['group_id']])) {
                $group = $groups[$row['group_id']];
            } else {
                $group = [
                    'id' => $row['group_id'],
                    'name' => $row['group_name'],
                    'content_type' => $row['group_content_type'],
                    'brands' => [],
                    'users' => [],
                ];
            }
            if(!empty($row['brand_id'])) {
                $group['brands'][$row['brand_id']] = array(
                    'id' => $row['brand_id'],
                    'name' => $row['brand_name'] . '(' . $row['brand_id'] . ')',
                    'content_type' => $row['brand_content_type'],
                );
            }
            if(!empty($row['user_id'])) {
                $group['users'][$row['user_id']] = array(
                    'id' => $row['user_id'],
                    'name' => $row['user_name'],
                    'email' => $row['user_email'],
                    'active' => $row['user_is_active']
                );
            }
            $groups[$row['group_id']] = $group;
        }

        foreach($groups as $group) {
            foreach ($group['brands'] as $brand) {
                $groups[$group['id']]['brands2'][] = $brand;
            }
            if(!empty($groups[$group['id']]['brands'])){
                $groups[$group['id']]['brands'] = $groups[$group['id']]['brands2'];
                unset($groups[$group['id']]['brands2']);
            }
            foreach ($group['users'] as $user) {
                $groups[$group['id']]['users2'][] = $user;
            }
            if(isset($groups[$group['id']]['users2'])){
                $groups[$group['id']]['users'] = $groups[$group['id']]['users2'];
                unset($groups[$group['id']]['users2']);
            }
        }
        $response = new stdClass();
        $response->data = [];
        foreach($groups as $group) {
            $response->data[] = $group;
        }
        return $response;
    }

    private static function formatSelect2($content_type)
    {
        if(!in_array(strtolower($content_type), ['ondemand', 'live'])) {
            return response('',500);
        }
        $groups = Group::query()
            ->where('content_type', 'ilike', $content_type)
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        $options = [];
        foreach($groups as $group) {
            $options[] = (object)[
                'label' => $group->name,
                'value' => $group->group_id,
            ];
        }
        return $options;
    }
}
