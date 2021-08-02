<?php

namespace App\Services\Chart\DashboardStatisticsChart;

abstract class AbstractDashboardChart
{
    protected $siteId;
    protected $offerUser;

    abstract public function getChartData(array $dates): \Illuminate\Database\Eloquent\Collection;

    /**
     * AbstractDashboardChart constructor.
     * @param int $siteId
     * @param $offerUser
     */
    public function __construct(?int $siteId, $offerUser)
    {
        $this->siteId = $siteId;
        $this->offerUser = $offerUser;
    }
}