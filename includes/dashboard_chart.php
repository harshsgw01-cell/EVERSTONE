<?php

/**
 * Parse dashboard date filter (today | month | year).
 */
function dashboard_parse_filter(?string $filter): array
{
    $allowed = ['today', 'month', 'year'];
    $filter  = in_array($filter ?? '', $allowed, true) ? $filter : 'month';

    switch ($filter) {
        case 'today':
            return [
                'filter'    => 'today',
                'date_from' => date('Y-m-d'),
                'date_to'   => date('Y-m-d'),
                'label'     => 'Today',
            ];
        case 'year':
            return [
                'filter'    => 'year',
                'date_from' => date('Y-01-01'),
                'date_to'   => date('Y-12-t', mktime(0, 0, 0, 12, 1)),
                'label'     => 'This Year',
            ];
        default:
            return [
                'filter'    => 'month',
                'date_from' => date('Y-m-01'),
                'date_to'   => date('Y-m-t'),
                'label'     => 'This Month',
            ];
    }
}

/**
 * Revenue / cost / profit chart buckets for the given filter.
 */
function dashboard_fetch_chart(mysqli $conn, ?string $filter): array
{
    $p         = dashboard_parse_filter($filter);
    $df        = mysqli_real_escape_string($conn, $p['date_from']);
    $dt        = mysqli_real_escape_string($conn, $p['date_to']);
    $filter    = $p['filter'];
    $date_from = $p['date_from'];

    $chart_labels      = [];
    $chart_revenue     = [];
    $chart_cost        = [];
    $chart_profit      = [];
    $chart_granularity = '';

    if ($filter === 'today') {
        $chart_granularity = 'Hourly';
        for ($h = 0; $h < 24; $h++) {
            $chart_labels[]  = sprintf('%02d:00', $h);
            $chart_revenue[$h] = 0;
            $chart_cost[$h]    = 0;
            $chart_profit[$h]  = 0;
        }
        $chart_q = mysqli_query($conn, "SELECT HOUR(o.created_at) AS bucket,
            IFNULL(SUM(ol.qty * ol.unit_price),0) AS rev,
            IFNULL(SUM(ol.qty * ol.cost_price),0) AS cst
            FROM order_lines ol JOIN orders o ON ol.order_id=o.id
            WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'
            GROUP BY HOUR(o.created_at)");
        while ($row = mysqli_fetch_assoc($chart_q)) {
            $h = (int)$row['bucket'];
            if ($h < 0 || $h > 23) {
                continue;
            }
            $chart_revenue[$h] = round((float)$row['rev'], 2);
            $chart_cost[$h]    = round((float)$row['cst'], 2);
            $chart_profit[$h]  = round((float)$row['rev'] - (float)$row['cst'], 2);
        }
        $chart_revenue = array_values($chart_revenue);
        $chart_cost    = array_values($chart_cost);
        $chart_profit  = array_values($chart_profit);
    } elseif ($filter === 'month') {
        $chart_granularity = 'Daily';
        $days_in_month     = (int)date('t', strtotime($date_from));
        for ($d = 1; $d <= $days_in_month; $d++) {
            $chart_labels[]    = (string)$d;
            $chart_revenue[$d] = 0;
            $chart_cost[$d]    = 0;
            $chart_profit[$d]  = 0;
        }
        $chart_q = mysqli_query($conn, "SELECT DAY(o.created_at) AS bucket,
            IFNULL(SUM(ol.qty * ol.unit_price),0) AS rev,
            IFNULL(SUM(ol.qty * ol.cost_price),0) AS cst
            FROM order_lines ol JOIN orders o ON ol.order_id=o.id
            WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'
            GROUP BY DAY(o.created_at)");
        while ($row = mysqli_fetch_assoc($chart_q)) {
            $d = (int)$row['bucket'];
            if ($d < 1 || $d > $days_in_month) {
                continue;
            }
            $chart_revenue[$d] = round((float)$row['rev'], 2);
            $chart_cost[$d]    = round((float)$row['cst'], 2);
            $chart_profit[$d]  = round((float)$row['rev'] - (float)$row['cst'], 2);
        }
        $chart_revenue = array_values($chart_revenue);
        $chart_cost    = array_values($chart_cost);
        $chart_profit  = array_values($chart_profit);
    } else {
        $chart_granularity = 'Monthly';
        for ($m = 1; $m <= 12; $m++) {
            $ts                = mktime(0, 0, 0, $m, 1, (int)date('Y', strtotime($date_from)));
            $chart_labels[]    = date('M', $ts);
            $chart_revenue[$m] = 0;
            $chart_cost[$m]    = 0;
            $chart_profit[$m]  = 0;
        }
        $chart_q = mysqli_query($conn, "SELECT MONTH(o.created_at) AS bucket,
            IFNULL(SUM(ol.qty * ol.unit_price),0) AS rev,
            IFNULL(SUM(ol.qty * ol.cost_price),0) AS cst
            FROM order_lines ol JOIN orders o ON ol.order_id=o.id
            WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'
            GROUP BY MONTH(o.created_at)");
        while ($row = mysqli_fetch_assoc($chart_q)) {
            $m = (int)$row['bucket'];
            if ($m < 1 || $m > 12) {
                continue;
            }
            $chart_revenue[$m] = round((float)$row['rev'], 2);
            $chart_cost[$m]    = round((float)$row['cst'], 2);
            $chart_profit[$m]  = round((float)$row['rev'] - (float)$row['cst'], 2);
        }
        $chart_revenue = array_values($chart_revenue);
        $chart_cost    = array_values($chart_cost);
        $chart_profit  = array_values($chart_profit);
    }

    $profit_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        IFNULL(SUM(ol.qty * ol.unit_price),0) AS revenue,
        IFNULL(SUM(ol.qty * ol.cost_price),0) AS cost
        FROM order_lines ol JOIN orders o ON ol.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'"));
    $revenue_total = (float)($profit_row['revenue'] ?? 0);
    $cost_total    = (float)($profit_row['cost'] ?? 0);
    $margin_pct    = $revenue_total > 0
        ? round((($revenue_total - $cost_total) / $revenue_total) * 100, 1)
        : 0;

    $xTicks = ['maxRotation' => 0, 'maxTicksLimit' => 12];
    if ($filter === 'today') {
        $xTicks = ['maxRotation' => 45, 'maxTicksLimit' => 12];
    } elseif ($filter === 'month') {
        $xTicks = ['maxRotation' => 0, 'maxTicksLimit' => 15];
    }

    return [
        'filter'      => $filter,
        'label'       => $p['label'],
        'granularity' => $chart_granularity,
        'labels'      => $chart_labels,
        'revenue'     => $chart_revenue,
        'cost'        => $chart_cost,
        'profit'      => $chart_profit,
        'margin_pct'  => $margin_pct,
        'x_ticks'     => $xTicks,
    ];
}
