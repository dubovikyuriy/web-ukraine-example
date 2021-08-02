<?php

namespace App\Services\Chart;

use App\Helpers\DateHelper;
use App\Models\OfferOrder;
use App\Models\OfferTraffCplDt;
use App\Models\OfferTraffDt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PartnerStatisticsChart
{
    public function getChartData($offerUser, int $offerUserId)
    {
        $date = $this->getChartDate(14);

        $traffic = $this->getPartnerTrafficByDate($offerUser, $date);
        $orders  = $this->getOrdersByDate($date, $offerUserId);

        return Collection::make([
            'date'        => $date,
            'traffic'     => $this->getChartSumValueByDate($traffic->toArray(), $date),
            'profit'      => $this->getChartProfitGroupByDate($orders->toArray(), $date),
            'conversions' => $this->getChartConversionGroupByDate($orders->toArray(), $date)
        ]);
    }

    public function getChartDate(int $day = 7): array
    {
        $adminTime = DateHelper::getAdminOffset();

        $result = [];

        for ($x = $day - 1; $x >= 0; $x = $x - 1) {
            $result[] = date("Y-m-d", time() - (86400 * $x) + $adminTime);
        }

        return $result;
    }

    public function getChartConversionGroupByDate(array $data, array $dates): array
    {
        $result = [];

        if (empty($data)) {
            return $result;
        }

        $adminTime = DateHelper::getAdminOffset();

        foreach ($dates as $date) {
            $cnt = 0;
            foreach ($data as $value) {
                $diffDate = Carbon::make($date)
                    ->diffInDays(
                        Carbon::make($value['cur_time'])
                            ->addSeconds($adminTime)
                    );

                if ($diffDate === 0) {
                    $cnt++;
                }
            }

            $result[] = (int) $cnt;
        }

        return $result;
    }

    public function getChartProfitGroupByDate(array $data, array $dates): array
    {
        $result = [];

        if (empty($data)) {
            return $result;
        }

        $adminTime = DateHelper::getAdminOffset();

        foreach ($dates as $date) {
            $sum = 0;
            foreach ($data as $value) {
                $diffDate = Carbon::make($date)
                    ->diffInDays(
                        Carbon::make($value['cur_time'])
                            ->addSeconds($adminTime)
                    );

                if ($diffDate === 0) {
                    $sum += $value['summa_prc'];
                }
            }

            $result[] = round($sum, 2);
        }

        return $result;
    }

    public function getChartSumValueByDate(array $data, array $dates): array
    {
        $result = [];

        if (empty($data) || empty($dates)) {
            return $result;
        }

        foreach ($dates as $date) {
            $cnt = 0;

            foreach ($data as $value) {
                if (Carbon::make($date)->diffInDays($value['dt']) === 0) {
                    $cnt += $value['uniq_cnt'];
                }
            }

            $result[] = (int) $cnt;
        }

        return $result;
    }

    public function getChartCountGroupByDate(array $data, array $dates): array
    {
        $result = [];

        if (empty($data) || empty($dates)) {
            return $result;
        }

        foreach ($dates as $date) {
            $cnt = 0;

            foreach ($data as $value) {
                if (Carbon::make($date)->diffInDays($value['dt']) === 0) {
                    $cnt++;
                }
            }

            $result[] = (int) $cnt;
        }

        return $result;
    }

    public function getOrdersByDate(array $date, int $offerUserId)
    {
        return OfferOrder::select(['action', 'summa_prc', 'cur_time'])
            ->whereBetween('cur_time', [$date[0], $date[count($date) - 1]])
            ->whereIn('action', ['sale', 'return'])
            ->where('offer_id', '=', $offerUserId)
            ->where('bonus', '')
            ->get();
    }

    public function getPartnerTrafficByDate($offerUser, array $date): Collection
    {
        if (empty($date)) {
            return Collection::make([]);
        }

        $trafficOffer = OfferTraffDt::select(['dt', 'uniq_cnt', 'key_offer AS partner_key'])
            ->whereBetween('dt', [$date[0], $date[count($date) - 1]])
            ->where('key_offer', '=', $offerUser->key_offer);

        $trafficCpl = OfferTraffCplDt::select(['dt', 'uniq_cnt', 'key_cpl AS partner_key'])
            ->whereBetween('dt', [$date[0], $date[count($date) - 1]])
            ->where('key_cpl', '=', $offerUser->cpl_offer);

        $unionTable = $trafficOffer->union($trafficCpl);

        return $unionTable->get();
    }
}