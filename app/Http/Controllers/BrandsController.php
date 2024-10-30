<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class BrandsController extends Controller
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

    public function store() {
    }

    public function show($id) {
        return Brand::findOrFail($id);
    }

    public function update($id) {
        $brand = Brand::findOrFail($id);
        $brand->update($this->request->all());
        return view('brands.edit', compact('brand'));
    }

    public function destroy($id) {
        $brand = Brand::findOrFail($id);
        $brand->delete();
        return view('brands.edit', compact('brand'));
    }


    public function formatDatatables($content_type, $brand_id = null) {
        if(!in_array(strtolower($content_type), ['ondemand', 'live','all'])) {
            return response('',500);
        }
        if($content_type == 'all') {
            $content_type = '%';
        }
        $query = "
            SELECT DISTINCT
                g.brand_id,
                g.name brand_name,
                g.content_type brand_content_type,
                b.brand_id,
                b.name brand_name,
                b.content_type brand_content_type,
                u.user_id,
                u.name user_name,
                u.email user_email,
                u.is_active user_is_active
            FROM v3_brands g
            LEFT JOIN v3_brands_brands gb ON gb.brand_id = g.brand_id
            LEFT JOIN v3_brands b ON b.brand_id = gb.brand_id
            LEFT JOIN v3_users_brands ug ON ug.brand_id = g.brand_id
            LEFT JOIN v3_users u ON u.user_id = ug.user_id
            WHERE 1=1
            AND g.content_type ilike '$content_type'
        ";
        if(!empty($brand_id)) {
            $query .= "AND g.brand_id = $brand_id";
        }
        $query .= "
            ORDER BY brand_name ASC, brand_content_type ASC, brand_name ASC, user_name ASC
        ";
        $rows = DB::select($query);

        $brands = [];
        for ($i = 0; $i < count($rows); $i++) {
            $row = (array)$rows[$i];
            $brand = null;
            if(isset($brands[$row['brand_id']])) {
                $brand = $brands[$row['brand_id']];
            } else {
                $brand = [
                    'id' => $row['brand_id'],
                    'name' => $row['brand_name'],
                    'content_type' => $row['brand_content_type'],
                    'brands' => [],
                    'users' => [],
                ];
            }
            if(!empty($row['brand_id'])) {
                $brand['brands'][$row['brand_id']] = array(
                    'id' => $row['brand_id'],
                    'name' => $row['brand_name'] . '(' . $row['brand_id'] . ')',
                    'content_type' => $row['brand_content_type'],
                );
            }
            if(!empty($row['user_id'])) {
                $brand['users'][$row['user_id']] = array(
                    'id' => $row['user_id'],
                    'name' => $row['user_name'],
                    'email' => $row['user_email'],
                    'active' => $row['user_is_active']
                );
            }
            $brands[$row['brand_id']] = $brand;
        }

        foreach($brands as $brand) {
            foreach ($brand['brands'] as $brand) {
                $brands[$brand['id']]['brands2'][] = $brand;
            }
            if(!empty($brands[$brand['id']]['brands'])){
                $brands[$brand['id']]['brands'] = $brands[$brand['id']]['brands2'];
                unset($brands[$brand['id']]['brands2']);
            }
            foreach ($brand['users'] as $user) {
                $brands[$brand['id']]['users2'][] = $user;
            }
            if(isset($brands[$brand['id']]['users2'])){
                $brands[$brand['id']]['users'] = $brands[$brand['id']]['users2'];
                unset($brands[$brand['id']]['users2']);
            }
        }
        $response = new stdClass();
        $response->data = [];
        foreach($brands as $brand) {
            $response->data[] = $brand;
        }
        return $response;
    }

    private static function formatSelect2($content_type)
    {
        if(!in_array(strtolower($content_type), ['ondemand', 'live'])) {
            return response('',500);
        }
        $brands = Brand::query()
            ->selectRaw('MIN(brand_id) as brand_id, name, content_type')
            ->where('content_type', 'ilike', $content_type)
            ->groupBy('name')
            ->groupBy('content_type')
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        $options = [];
        foreach($brands as $brand) {
            $options[] = (object)[
                'label' => $brand->name,
                'value' => $brand->brand_id,
            ];
        }
        //return ['data' => $options];
        return $options;
    }

}
