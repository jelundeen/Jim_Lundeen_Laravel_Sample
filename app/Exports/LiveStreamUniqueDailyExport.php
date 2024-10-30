<?php

namespace App\Exports;
ini_set('memory_limit', '2024M');
ini_set('max_execution_time', '600');

use App\Models\EventSummaryDaily;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;


class LiveStreamUniqueDailyExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function headings(): array
    {
        return [
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
            'Unique Visitors',
            'Live Video Starts',
            'Viewing Time',
            'Please note that the daily unique visitor metrics'
            . ' are not additive towards either a weekly or monthly'
            . ' unique visitor metric. For weekly or monthly unique'
            . ' visitor data, please run a weekly or monthly report,'
            . ' which will include de-duplicated weekly and monthly'
            . ' unique metrics.'

        ];
    }

    public function query()
    {
        $params = $this->params;

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
                e.unique_devices,
                e.content_starts,
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


        $query->groupBy('e.event_date');
        $query->groupBy('e.brand');
        $query->groupBy('e.platform');
        $query->groupBy('e.x1_user_type');
        $query->groupBy('e.total_duration');
        $query->groupBy('e.air_date');
        $query->groupBy('e.gracenote_id');
        $query->groupBy('e.content_starts');
        $query->groupBy('e.content_type');
        $query->groupBy('e.total_seconds_viewed');
        $query->groupBy('a.available_date');
        $query->groupBy('a.expiration_date');
        $query->groupBy('a.series_name');
        $query->groupBy('a.episode_name');
        $query->groupBy('a.episode_number');
        $query->groupBy('a.season_number');
        $query->groupBy('a.program_type');
        $query->groupBy('a.rating');
        $query->groupBy('e.unique_devices');

        if (isset($params['products'])) {
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
        return $query;
    }
}
