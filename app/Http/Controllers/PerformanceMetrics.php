<?php

namespace App\Http\Controllers;

use App\Exports\VodPerformanceExport;
use App\Models\Brand;
use App\Models\EventSummaryDaily;
use App\Transformers\EventSummaryDailyTransformer;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Serializer\ArraySerializer;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\Process\Process;
use App\CsvToExcel\CsvToExcel;

class PerformanceMetrics extends Controller
{
    public function __construct()
    {
        $this->middleware('checktoken')->except([
            //'login',
        ]);
    }


    //POST   /api/metrics/vod/performance                controllers.PerformanceMetrics.getPagedPerformanceMetrics(request: Request)
    //POST   /metrics/vod/performance                    controllers.PerformanceMetrics.getPagedPerformanceMetrics(request: Request)
    public function getPagedPerformanceMetrics(Request $request)
    {
        $mapOldNew = [
            'platform'          => 'platform',
            'brand'             => 'brand',
            'dateKey'           => 'event_date',
            'series_name'       => 'series_name',
            'title'             => 'episode_name',
            'videoStarts'       => 'content_starts',
            'video25Complete'   => 'pct25_viewed',
            'video50Complete'   => 'pct50_viewed',
            'video75Complete'   => 'pct75_viewed',
            'video95Complete'   => 'pct95_viewed',
            'videoCompletes'    => 'pct100_viewed',
            'secondsWatched'    => 'total_seconds_viewed',
            'downloads'         => 'content_starts',
            'x1_user_type'      => 'x1_user_type',
        ];

        $order_by = explode(' ', $request->input('orderBy'));
        $order_by['field'] = $mapOldNew[$order_by[0]];
        $order_by['direction'] = $order_by[1];

        $platforms = preg_split('/[\s,]+/', $request->input('platforms'), null, PREG_SPLIT_NO_EMPTY);
        if(count($platforms)===0){
            $platforms = array('Web','Mobile');
        }

        $params = [
            'platforms'         => $platforms,
            'content_types'     => ['OnDemand'],
            'start_date'        => $request->input('startDate'),
            'end_date'          => $request->input('endDate'),
            'order_by'          => $order_by,
        ];

        if(strpos($params['start_date'],'T00:00:00') === false){
            $params['start_date'] = new DateTime('@' . strtotime($params['start_date']));
            $params['start_date'] = $params['start_date']->format('Y-m-d 00:00:00.000');
        }

        if(strpos($params['end_date'],'T23:59:59') === false){
            $params['end_date'] = new DateTime('@' . strtotime($params['end_date']));
            $params['end_date'] = date_modify( $params['end_date'],'-1 day')->format('Y-m-d 23:59:59');
        }

//        if(!is_null($request->input('vodBrandsConcat'))) {
//            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('vodBrandsConcat')));
//        } else {
//            $brands = auth()->user()->brands(['content_type' => ['OnDemand'], 'products' => $request->input('products','TVE')]);
//            foreach ($brands as $brand) {
//                $params['brand_names'][] = $brand['brand'];
//            }
//        }

        if(!empty($request->input('vodBrandsConcat'))) {
            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('vodBrandsConcat')));
        }
        else {
            $brands = auth()->user()->brands(['content_type' =>['OnDemand'], 'products' => $request->input('products','TVE')]);
            foreach ($brands as $brand) {
                $params['brand_names'][] = $brand['brand'];
            }
        }
        $query = EventSummaryDaily::query()
            ->selectRaw("
                case when lower(e.x1_user_type) = 'introtv' then 'NOWTV' else 'TVE' end as product,
                e.platform,
                a.brand,
                e.event_date,
                a.series_name,
                a.episode_name,
                a.asset_id,
                SUM(e.content_starts) AS content_starts,
                SUM(e.pct25_viewed) AS pct25_viewed,
                SUM(e.pct50_viewed) AS pct50_viewed,
                SUM(e.pct75_viewed) AS pct75_viewed,
                SUM(e.pct95_viewed) AS pct95_viewed,
                SUM(e.pct100_viewed) AS pct100_viewed,
                SUM(e.total_seconds_viewed) AS total_seconds_viewed,
                e.x1_user_type
                ")
            ->fromRaw('events_summary_daily_hashed e')
            ->join('assets AS a', 'a.asset_id', '=', 'e.asset_id');

        if(!is_null($request->input('brands'))) {
            $query->whereIn('a.brand', $params['brand_names']);
        }

        $query
            ->whereIn('e.content_type', $params['content_types'])
            ->whereIn('e.platform', $params['platforms'])
            ->whereDate('e.event_date', '>=', $params['start_date'])
            ->whereDate('e.event_date', '<=', $params['end_date'])
            ->whereNull('e.gracenote_id');

        if(!is_null($request->input('videoTitle')) ) {
            $query->where('a.episode_name', 'ilike', '%' . $request->input('videoTitle') . '%');
        }
        if(!is_null($request->input('seriesTitle')) ) {
            $query->where('a.series_name', 'ilike', '%' . $request->input('seriesTitle') . '%');
        }

        $query->groupBy('e.platform')
            ->groupBy('a.brand')
            ->groupBy('e.event_date')
            ->groupBy('a.series_name')
            ->groupBy('a.episode_name')
            ->groupBy('e.content_starts')
            ->groupBy('e.x1_user_type')
            ->groupBy('a.asset_id');

        if(!is_null($request->input('products',null)) ) {
            $query->having('product', 'ilike', $request->input('products'));
        }

            $query->orderBy($params['order_by']['field'], $params['order_by']['direction'])
            ;

//        $paginator = $query->paginate($params['pageSize'], 15);
        $paginator = $query->paginate( 100);

        $eventSummary = $paginator->getCollection();
logger('sql:' . print_r($query->toSql(), true));
        $response = fractal()
            ->collection($eventSummary, new EventSummaryDailyTransformer())
            ->serializeWith(new ArraySerializer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))
            ->toArray()['data'];
        return response()->json($response);
    }

    //POST   /metrics/vod/performance/chart              controllers.PerformanceMetrics.getChartData(request: Request)
    public function getChartData(Request $request)
    {
        $platforms = preg_split('/[\s,]+/', $request->input('platforms'), null, PREG_SPLIT_NO_EMPTY);
        if(count($platforms)===0){
            $platforms = array('Web','Mobile');
        }

        $params = [
            'platforms'         => $platforms,
            'content_types'     => ['OnDemand'],
            'start_date'        => $request->input('startDate'),
            'end_date'          => $request->input('endDate'),
        ];


        if(strpos($params['start_date'],'T00:00:00') === false){
            $params['start_date'] = new DateTime('@' . strtotime($params['start_date']));
            $params['start_date'] = $params['start_date']->format('Y-m-d 00:00:00.000');
        }

        if(strpos($params['end_date'],'T23:59:59') === false){
            $params['end_date'] = new DateTime('@' . strtotime($params['end_date']));
            $params['end_date'] = date_modify( $params['end_date'],'-1 day')->format('Y-m-d 23:59:59');
        }

//        if(!is_null($request->input('vodBrandsConcat'))) {
//            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('vodBrandsConcat')));
//        } else {
//            $brands = auth()->user()->brands(['content_type' => ['OnDemand'], 'products' => $request->input('products','TVE')]);
//            foreach ($brands as $brand) {
//                $params['brand_names'][] = $brand['brand'];
//            }
//        }

        if(!empty($request->input('vodBrandsConcat'))) {
            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('vodBrandsConcat')));
        }
        else {
            $brands = auth()->user()->brands(['content_type' => ['OnDemand'], 'products' => $request->input('products','TVE')]);
            foreach ($brands as $brand) {
                $params['brand_names'][] = $brand['brand'];
            }
        }
        $query = EventSummaryDaily::query()
            ->selectRaw("
                case when lower(e.x1_user_type) = 'introtv' then 'NOWTV' else 'TVE' end as product,
                CAST(REPLACE(e.event_date, '-', '') AS int) AS date_key,
                SUM(e.content_starts) AS totalStarts")
            ->fromRaw('events_summary_daily_hashed e')
            ->join('assets AS a', 'a.asset_id', '=', 'e.asset_id')
            ->whereIn('a.brand', $params['brand_names'])
            ->whereIn('e.content_type', $params['content_types'])
            ->whereIn('e.platform', $params['platforms'])
            ->whereDate('e.event_date', '>=', $params['start_date'])
            ->whereDate('e.event_date', '<=', $params['end_date'])
            ->whereNull('e.gracenote_id');

        if(!is_null($request->input('videoTitle')) ) {
            $query->where('a.episode_name', 'ilike', '%' . $request->input('videoTitle') . '%');
        }
        if(!is_null($request->input('seriesTitle')) ) {
            $query->where('a.series_name', 'ilike', '%' . $request->input('seriesTitle') . '%');
        }

        $query
            ->groupBy('e.event_date')
            ->groupBy('product');

        if(!is_null($request->input('products')) ) {
            $query->having('product', 'ilike', $request->input('products'));
        }

        $query
            ->orderBy('e.event_date')
        ;

        logger('query: '. print_r($query->toSql(), true));
        return $query->get();
    }

    public function exportPerformanceMetricExcel(Request $request)
    {

        ini_set('memory_limit', '2024M');
        ini_set('max_execution_time', '600');

        $platforms = preg_split('/[\s,]+/', $request->input('platforms'), null, PREG_SPLIT_NO_EMPTY);
        if (count($platforms) === 0) {
            $platforms = array('Web', 'Mobile', 'Connected');
        }

        $params = [
            'platforms' => $platforms,
            'products'  => $request->input('products','TVE'),
            'content_type' => 'OnDemand',
            'series_name' => $request->input('seriesTitle'),
            'episode_name' => $request->input('videoTitle'),
            'start_date' => new DateTime('@' . substr($request->input('startDate'), 0, 10)),
            'end_date' => new DateTime('@' . substr($request->input('endDate'), 0, 10)),
        ];

        if(strpos($params['start_date']->format('Y-m-d H:i:s'),'00:00:00') === false){
            $params['start_date'] = new DateTime($params['start_date']->format('Y-m-d 00:00:00'));
        }

        if(strpos($params['end_date']->format('Y-m-d H:i:s'),'23:59:59') === false){
            $params['end_date'] = new DateTime(date_modify( $params['end_date'],'-1 day')->format('Y-m-d 23:59:59'));
        }

//        if (!empty($request->input('brands'))) {
//            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('brands')));
//        } else {
//            $brands = auth()->user()->brands(['content_type' => [$params['content_type']], 'products' => $request->input('products','TVE')]);
//
//            foreach ($brands as $brand) {
//                $params['brand_names'][] = $brand['brand'];
//            }
//        }

        if(!empty($request->input('brands'))) {
            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('brands')));
        }
        else {
            $brands = auth()->user()->brands(['content_type' => [$params['content_type']], 'products' => $request->input('products','TVE')]);
            foreach ($brands as $brand) {
                $params['brand_names'][] = $brand['brand'];
            }
        }

        $query = EventSummaryDaily::query()
            ->selectRaw("
                case when lower(e.x1_user_type) = 'introtv' then 'NOW TV' else 'TVE' end as product,
                e.platform,
                e.brand,
                e.event_date,
                a.series_name,
                a.episode_name,
                a.episode_number,
                a.season_number,
                a.program_type,
                a.rating,
                e.total_duration,
                a.available_date,
                e.air_date,
                a.expiration_date,
                e.gracenote_id,
                e.content_starts,
                e.pct25_viewed,
                e.pct50_viewed,
                e.pct75_viewed,
                e.pct95_viewed,
                e.pct100_viewed,
                e.total_seconds_viewed")
            ->fromRaw('events_summary_daily_hashed AS e')
            ->join('assets AS a', 'a.asset_id', '=', 'e.asset_id');

        if (!empty($params['brand_names'])) {
            $query->whereIn('a.brand', $params['brand_names']);
        }
        if (!empty($params['episode_name'])) {
            $query->where('a.episode_name', 'ilike', '%' . $params['episode_name'] . '%');
        }
        if (!empty($params['series_name'])) {
            $query->where('a.series_name', 'ilike', '%' . $params['series_name'] . '%');
        }

        $query
            ->where('e.content_type', '=', $params['content_type'])
            ->whereIn('e.platform', $params['platforms'])
            ->whereDate('e.event_date', '>=', $params['start_date'])
            ->whereDate('e.event_date', '<=', $params['end_date'])
            ->whereNotNull('e.gracenote_id');
        $query->groupBy('product');
        $query->groupBy('e.platform');
        $query->groupBy('e.brand');
        $query->groupBy('e.event_date');
        $query->groupBy('e.content_type');
        $query->groupBy('e.platform');
        $query->groupBy('e.total_duration');
        $query->groupBy('e.air_date');
        $query->groupBy('e.gracenote_id');
        $query->groupBy('e.pct25_viewed');
        $query->groupBy('e.pct50_viewed');
        $query->groupBy('e.pct75_viewed');
        $query->groupBy('e.pct95_viewed');
        $query->groupBy('e.pct100_viewed');
        $query->groupBy('e.total_seconds_viewed');
        $query->groupBy('e.content_starts');
        $query->groupBy('a.series_name');
        $query->groupBy('a.episode_name');
        $query->groupBy('a.episode_number');
        $query->groupBy('a.season_number');
        $query->groupBy('a.program_type');
        $query->groupBy('a.expiration_date');
        $query->groupBy('a.available_date');
        $query->groupBy('a.rating');

        if(!is_null($request->input('products')) ) {
            if (strtolower($params['products']) == 'nowtv') {
                $params['products'] = 'now tv';
            }
            $query->having('product', 'ilike', $params['products']);
        }

        $query
            ->orderBy('e.brand')
            ->orderBy('e.content_type')
            ->orderBy('e.platform')
            ->orderBy('e.event_date');


        $columns = [
            'Product',
            'Platform',
            'Brand',
            'Date',
            'Series Title',
            'Video Title',
            'Episode #',
            'Season #',
            'XTV Video Category',
            'Rating',
            'Length',
            'Available Date',
            'Air Date',
            'Expiration Date',
            'Provider External ID',
            'Video Starts',
            '25% Complete',
            '50% Complete',
            '75% Complete',
            '95% Complete',
            'Completes',
            'Viewing Time'
        ];

        $exportFilename = 'metrics_';
        $exportFilename.= $params['start_date']->format('Ymd');
        $exportFilename.= '-';
        $exportFilename.= $params['end_date']->format('Ymd');

        $c2e = new CsvToExcel();
        $ExportFileInfo = $c2e->exportExcel([
        // $ExportFileInfo = $c2e->exportCsv([
            'query' => $query,
            'columns' => $columns,
            'export_filename' => $exportFilename,
        ]);
        unlink($ExportFileInfo['export_directory']. '/'. $exportFilename . '.csv');
        return response()
            ->download($ExportFileInfo['export_directory']. '/'. $ExportFileInfo['export_filename'])
            ->deleteFileAfterSend(true);
    }

    public function exportPerformanceMetricExcel0(Request $request) {
        /*
            brands=7889
            platforms=
            seriesTitle=rookie
            videoTitle=
            startDate=1667260800000
            endDate=1669852799999
         */

        $platforms = preg_split('/[\s,]+/', $request->input('platforms'), null, PREG_SPLIT_NO_EMPTY);
        if(count($platforms)===0){
            $platforms = array('Web','Mobile','Connected');
        }

        $params = [
            'platforms'         => $platforms,
            'content_type'      => 'OnDemand',
            'series_name'       => $request->input('seriesTitle'),
            'episode_name'      => $request->input('videoTitle'),
            'start_date'        => new DateTime('@' . substr($request->input('startDate'),0,10)),
            'end_date'          => new DateTime('@' . substr($request->input('endDate'),0,10)),
        ];

        if(!empty($request->input('brands'))) {
            $params['brand_names'] = $this->getBrandNamesById(explode(',', $request->input('brands')));
        } else {
            $brands = auth()->user()->brands(['content_type' => [$params['content_type']]]);
            foreach ($brands as $brand) {
                $params['brand_names'][] = $brand['brand'];
            }
        }

        $export = new VodPerformanceExport($params);
        // metrics_20221101-20221110 (1)
        $downloadFilename = 'metrics_';
        $downloadFilename.= $params['start_date']->format('Ymd');
        $downloadFilename.= '-';
        $downloadFilename.= $params['end_date']->format('Ymd');
        $downloadFilename.= '.xlsx';
        return $export->download($downloadFilename);
    }

    public function getBrandsById($brandIds) : Collection
    {
        $brands = Brand::query()
            ->whereIn('brand_id', $brandIds)
            ->orderBy('name')
            ->get();
        return $brands;
    }

    public function getBrandNamesById($brandIds) : array {
        $brands = $this->getBrandsById($brandIds);
        $brandNames = array();
        foreach ($brands as $brand){
            $brandNames[] = $brand->name;
        }
        return $brandNames;
    }
}
