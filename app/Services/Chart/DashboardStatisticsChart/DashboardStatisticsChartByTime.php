<?php

namespace App\Services\Chart\DashboardStatisticsChart;

use App\Models\OfferOrder;
use App\Models\OfferTraffic;
use DateTime;
use DatePeriod;
use DateInterval;
use DB;
use Illuminate\Database\Eloquent\Collection;

class DashboardStatisticsChartByTime
{
    private $siteId;
    private $offerUser;

    public function __construct(int $siteId, $offerUser)
    {
        $this->siteId = $siteId;
        $this->offerUser = $offerUser;
    }

    public function getChartData(array $dates): Collection
    {
        if ((empty($dates["from"]) || empty($dates["to"])) || $dates["from"] != $dates["to"]) {
            return Collection::make([]);
        }
        $date = $dates["from"];
        
        $axisXDateFormatLabels = $this->getRangeBetweenTime($date, '00:00','23:59');
        $axisXDefaultPoints = array_fill_keys($axisXDateFormatLabels,0);

        return Collection::make([
            'axis_x' => $axisXDateFormatLabels,
            'traffic_revshare' => $this->getTrafficCountByHours($date, $axisXDefaultPoints, 'revshare'),
            'traffic_cpl' => $this->getTrafficCountByHours($date, $axisXDefaultPoints, 'cpl'),
            'conversions_revshare' => $this->getConversionsRevshareCountByHours($date, $axisXDefaultPoints),
            'conversions_cpl' => $this->getConversionsCplCountByHour($date, $axisXDefaultPoints),
            'profit' => $this->getProfit($date, $axisXDefaultPoints),
        ]);
    }

    private function getRangeBetweenTime($date, $from = '00:00', $to = '23:59', $interval = '1 hours')
    {
        $times = new DatePeriod(new DateTime($from), DateInterval::createFromDateString($interval), new DateTime($to));

        $rangeTimes = [];
        foreach ($times as $time) {
            $rangeTimes[] = $time->format("$date H:i:00");
        }
        return $rangeTimes;
    }

    private function getTrafficCountByHours($date, $axisXDefaultPoints, $type = 'revshare'): array
    {
        $key_column_name = ($type == 'revshare') ? 'key_offer' : 'key_cpl';

        $traffic = OfferTraffic::query()
            ->select([
                    DB::raw("COUNT($key_column_name) as count"),
                    DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d %H:00:00') AS date")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_promo', $this->siteId);
                }
            })
            ->where(function ($q) use($key_column_name) {
                if (!empty($this->offerUser->$key_column_name)) {
                    $q->where("$key_column_name", $this->offerUser->$key_column_name);
                }
            })
            ->where('dt', '=', $date)
            ->whereNotIn("$key_column_name", ['0','No data key'])
            ->groupByRaw('HOUR(TIME(cur_time))')
            ->get()
            ->pluck('count','date');

        return array_values(array_merge($axisXDefaultPoints,$traffic->toArray()));
    }

    private function getConversionsRevshareCountByHours($date, $axisXDefaultPoints): array
    {
        $conversions = OfferOrder::query()
            ->select([
                DB::raw("COUNT(order_id) as count"),
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d %H:00:00') AS date")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (isset($this->offerUser) && !empty($this->offerUser->id)) {
                    $q->where("offer_id", $this->offerUser->id);
                }
            })
            ->where('dt', '=', $date)
            ->whereIn('action', ['sale', 'return'])
            ->where('bonus', '=', '')
            ->groupByRaw('HOUR(TIME(cur_time))')
            ->get()
            ->pluck('count','date');

        return array_values(array_merge($axisXDefaultPoints,$conversions->toArray()));
    }

    private function getConversionsCplCountByHour($date, array $axisXDefaultPoints): array
    {
        $conversions = OfferOrder::query()
            ->select([
                DB::raw("COUNT(user_id) as count"),
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d %H:00:00') AS date")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (isset($this->offerUser) && !empty($this->offerUser->cpl_offer)) {
                    $q->where("key_cpl", $this->offerUser->cpl_offer);
                }
            })
            ->where('dt', '=', $date)
            ->whereIn('action', ['lead'])
            ->where('bonus', '=', '')
            ->groupByRaw('HOUR(TIME(cur_time))')
            ->get()
            ->pluck('count','date');

        return array_values(array_merge($axisXDefaultPoints,$conversions->toArray()));
    }

    private function getProfit($date, $axisXDefaultPoints): array
    {
        $profit = OfferOrder::query()
            ->select([
                DB::raw("ROUND(SUM(summa_prc),2) as sum"),
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d %H:00:00') AS date")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (!empty($this->offerUser->cpl_offer)) {
                    $q->where("key_cpl", $this->offerUser->cpl_offer);
                }
            })
            ->where('dt', '=', $date)
            ->whereIn('action', ['sale', 'return'])
            ->where('bonus', '=', '')
            ->groupByRaw('HOUR(TIME(cur_time))')
            ->get()
            ->pluck('sum','date');

        return array_values(array_merge($axisXDefaultPoints,$profit->toArray()));
    }
}