<?php

namespace App\Services\Chart\DashboardStatisticsChart;


use App\Helpers\OfferHelper;
use DateTime;
use DatePeriod;
use DateInterval;
use App\Models\OfferOrder;
use App\Models\OfferTraffDt;
use App\Models\OfferTraffCplDt;
use DB;
use Illuminate\Database\Eloquent\Collection;

class DashboardStatisticsChartByDate extends AbstractDashboardChart
{
    public function getChartData(array $dates): Collection
    {
        if ((empty($dates["from"]) || empty($dates["to"])) || $dates["from"] == $dates["to"]) {
            return Collection::make([]);
        }

        $dateFrom = $dates["from"];
        $dateTo = $dates["to"];

        $axisXDateFormatLabels = $this->getRangeBetweenDate($dateFrom,$dateTo);
        $axisXDefaultPoints = array_fill_keys($axisXDateFormatLabels,0);

        return Collection::make([
            'axis_x' => $axisXDateFormatLabels,
            'traffic_revshare' => $this->getRevshareTrafficByRange($dateFrom, $dateTo, $axisXDefaultPoints),
            'traffic_cpl' => $this->getCplTrafficByRange($dateFrom, $dateTo, $axisXDefaultPoints),
            'conversions_revshare' => $this->getRevshareConversionsByRange($dateFrom, $dateTo, $axisXDefaultPoints),
            'conversions_cpl' => $this->getCplConversionsByRange($dateFrom, $dateTo, $axisXDefaultPoints),
            'profit' => $this->getProfitByRange($dateFrom, $dateTo, $axisXDefaultPoints),
        ]);
    }

    private function getRangeBetweenDate($dateFrom, $dateTo)
    {
        $interval = new DateInterval('P1D');

        $real_date_to = new DateTime($dateTo);
        $real_date_to->add($interval);

        $period = new DatePeriod(
            new DateTime($dateFrom),
            $interval,
            $real_date_to
        );

        $rangeDates = [];
        foreach ($period as $key => $value) {
            $rangeDates[] = $value->format('Y-m-d 00:00:00');
        }

        return $rangeDates;
    }

    public function getRevshareTrafficByRange($dateFrom, $dateTo, $axisXDefaultPoints): array
    {
        $traffic = OfferTraffDt::query()
            ->select([
                DB::raw("SUM(uniq_cnt) as cnt"),
                DB::raw("CONCAT(dt,' 00:00:00') as dt")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('id_site', $this->siteId);
                }
            })
            ->where(function ($q)  {
                if (!empty($this->offerUser->key_offer)) {
                    $q->where('key_offer', $this->offerUser->key_offer);
                }
            })
            ->whereBetween('dt', [$dateFrom, $dateTo])
            ->whereNotIn('key_offer', ['0','No data key'])
            ->groupBy('dt')
            ->get()
            ->pluck('cnt','dt');

        return array_values(array_merge($axisXDefaultPoints,$traffic->toArray()));
    }

    public function getCplTrafficByRange($dateFrom, $dateTo, $axisXDefaultPoints): array
    {
        $traffic = OfferTraffCplDt::query()
            ->select([
                    DB::raw("SUM(uniq_cnt) as cnt"),
                    DB::raw("CONCAT(dt,' 00:00:00') as dt")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('id_site', $this->siteId);
                }
            })
            ->whereBetween('dt', [$dateFrom, $dateTo])
            ->whereNotIn('key_cpl', ['0','No data key']);

        if (isset($this->offerUser) && !empty($this->offerUser->cpl_offer)) {
            $traffic->where('key_cpl', $this->offerUser->offer_cpl);
        }

        $traffic = $traffic->groupBy('dt')->get()->pluck('cnt','dt');

        return array_values(array_merge($axisXDefaultPoints,$traffic->toArray()));
    }

    private function getRevshareConversionsByRange($dateFrom, $dateTo, $axisXDefaultPoints): array
    {
        $conversions = OfferOrder::query()
            ->select([
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d 00:00:00') AS date"),
                DB::raw("COUNT(id) as count")
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (!empty($this->offerUser->id)) {
                    $q->where('offer_id', $this->offerUser->id);
                }
            })
            ->whereBetween('dt', [$dateFrom, $dateTo])
            ->whereIn('action', [
                OfferHelper::STATUS_SALE,
                OfferHelper::STATUS_RETURN
            ])
            ->where('bonus', '')
            ->groupBy('dt')
            ->get()
            ->pluck('count','date');

        return array_values(array_merge($axisXDefaultPoints,$conversions->toArray()));
    }

    private function getCplConversionsByRange($dateFrom, $dateTo, $axisXDefaultPoints): array
    {
        $conversions = OfferOrder::query()
            ->select([
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d 00:00:00') AS date"),
                DB::raw("COUNT(id) as count"),
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (!empty($this->offerUser->id)) {
                    $q->where('offer_id', $this->offerUser->id);
                }
            })
            ->whereBetween('dt', [$dateFrom, $dateTo])
            ->whereIn('action', [
                OfferHelper::STATUS_LEAD,
                OfferHelper::STATUS_CPL_APPROVED
            ])
            ->where('bonus', '')
            ->groupBy('dt')
            ->get()
            ->pluck('count','date');

        return array_values(array_merge($axisXDefaultPoints,$conversions->toArray()));
    }

    private function getProfitByRange($dateFrom, $dateTo, $axisXDefaultPoints): array
    {
        $conversions = OfferOrder::query()
            ->select([
                DB::raw("DATE_FORMAT(cur_time, '%Y-%m-%d 00:00:00') AS date"),
                DB::raw("ROUND(SUM(summa_prc),2) as sum"),
            ])
            ->where(function ($q) {
                if (!empty($this->siteId)) {
                    $q->where('site_id', $this->siteId);
                }
            })
            ->where(function ($q) {
                if (!empty($this->offerUser->id)) {
                    $q->where('offer_id', $this->offerUser->id);
                }
            })
            ->whereBetween('dt', [$dateFrom, $dateTo])
            ->whereIn('action', ['sale', 'return', 'lead'])
            ->where('bonus', '')
            ->groupBy('dt')
            ->get()
            ->pluck('sum','date');

        return array_values(array_merge($axisXDefaultPoints,$conversions->toArray()));
    }
}