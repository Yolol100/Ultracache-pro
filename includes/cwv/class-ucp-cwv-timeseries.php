<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hourly time-series storage for CWV/RUM samples.
 *
 * Sibling to UCP_CWV_Metric_Summary. The existing summary stores an aggregate
 * histogram + P75; this class stores per-hour aggregates so the admin / external
 * dashboards can render a trend chart over the last N days.
 *
 * Stored in its own option (autoload=false) to keep the legacy summary option
 * unchanged and the autoloaded-options budget small.
 */
final class UCP_CWV_Timeseries {
    const OPTION_KEY              = 'ucp_cwv_timeseries';
    const METRICS                 = array('lcp', 'inp', 'cls', 'fcp', 'ttfb');
    const DEVICES                 = array('mobile', 'desktop', 'all');
    const MIN_RETENTION_DAYS      = 1;
    const MAX_RETENTION_DAYS      = 30;
    const DEFAULT_RETENTION_DAYS  = 7;
    const MAX_BUCKETS_PER_SERIES  = 720; // hard ceiling per metric/device (~30 days @ 1h)

    /**
     * Append one sample to the current hour bucket for a metric/device.
     *
     * @param string $metric Metric key (lcp|inp|cls|fcp|ttfb).
     * @param float  $value  Metric value. CLS is expected in the legacy x1000 scale.
     * @param string $device Device bucket (mobile|desktop|all).
     * @return void
     */
    public static function record($metric, $value, $device = 'all') {
        if (!is_scalar($metric) && null !== $metric) {
            $metric = '';
        }
        if (!is_scalar($device) && null !== $device) {
            $device = 'all';
        }
        $metric = strtolower(sanitize_key((string) $metric));
        $device = sanitize_key((string) $device);
        if (!in_array($metric, self::METRICS, true)) {
            return;
        }
        if (!in_array($device, self::DEVICES, true)) {
            $device = 'all';
        }

        $lock_token = class_exists('UCP_CWV_Option_Lock') ? UCP_CWV_Option_Lock::acquire(self::OPTION_KEY) : '';
        if ('' === $lock_token) {
            return;
        }

        try {
            $data = get_option(self::OPTION_KEY, array());
            if (!is_array($data)) {
                $data = array();
            }

            $hour_key = (int) (time() - (time() % 3600)); // floor to hour
            if (empty($data[$metric][$device][$hour_key]) || !is_array($data[$metric][$device][$hour_key])) {
                $data[$metric][$device][$hour_key] = array('n' => 0, 'sum' => 0.0, 'max' => 0.0);
            }

            $bucket =& $data[$metric][$device][$hour_key];
            $bucket['n']   = absint($bucket['n']) + 1;
            $bucket['sum'] = (float) $bucket['sum'] + (float) $value;
            $bucket['max'] = max((float) $bucket['max'], (float) $value);
            unset($bucket);

            $data = self::prune($data, self::current_retention_hours());
            update_option(self::OPTION_KEY, $data, false);
        } finally {
            UCP_CWV_Option_Lock::release(self::OPTION_KEY, $lock_token);
        }
    }

    /**
     * Return time-series buckets within the requested window.
     *
     * @param string|null $metric Optional metric filter.
     * @param string|null $device Optional device filter.
     * @param int         $days   Window length in days (clamped to 1..30).
     * @return array<string,array<string,array<int,array<string,int|float>>>>
     */
    public static function get_series($metric = null, $device = null, $days = self::DEFAULT_RETENTION_DAYS) {
        if (!is_scalar($metric) && null !== $metric) {
            $metric = null;
        }
        if (!is_scalar($device) && null !== $device) {
            $device = null;
        }
        if (!is_scalar($days) && null !== $days) {
            $days = 7;
        }
        $data = get_option(self::OPTION_KEY, array());
        if (!is_array($data)) {
            return array();
        }
        $days = max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, absint($days)));
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        $metric_filter = (null === $metric) ? null : strtolower(sanitize_key((string) $metric));
        $device_filter = (null === $device) ? null : sanitize_key((string) $device);

        $out = array();
        foreach (self::METRICS as $m) {
            if (null !== $metric_filter && $m !== $metric_filter) {
                continue;
            }
            foreach (self::DEVICES as $d) {
                if (null !== $device_filter && $d !== $device_filter) {
                    continue;
                }
                if (empty($data[$m][$d]) || !is_array($data[$m][$d])) {
                    continue;
                }
                $buckets = array();
                foreach ($data[$m][$d] as $h => $bk) {
                    $h = (int) $h;
                    if ($h < $cutoff) {
                        continue;
                    }
                    $n = absint(isset($bk['n']) ? $bk['n'] : 0);
                    if ($n <= 0) {
                        continue;
                    }
                    $sum = (float) (isset($bk['sum']) ? $bk['sum'] : 0);
                    $max = (float) (isset($bk['max']) ? $bk['max'] : 0);
                    $avg = $sum / $n;
                    if ('cls' === $m) {
                        $avg = round($avg / 1000, 3);
                        $max = round($max / 1000, 3);
                    } else {
                        $avg = round($avg, 1);
                        $max = round($max, 1);
                    }
                    $buckets[] = array(
                        'hour' => $h,
                        'n'    => $n,
                        'avg'  => $avg,
                        'max'  => $max,
                    );
                }
                if (!empty($buckets)) {
                    usort($buckets, static function($a, $b) {
                        return (int) $a['hour'] - (int) $b['hour'];
                    });
                    $out[$m][$d] = $buckets;
                }
            }
        }
        return $out;
    }

    /**
     * Reset all stored series.
     *
     * @return void
     */
    public static function clear() {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Total stored bucket count across all metrics/devices.
     *
     * @return int
     */
    public static function bucket_count() {
        $data = get_option(self::OPTION_KEY, array());
        if (!is_array($data)) {
            return 0;
        }
        $count = 0;
        foreach ($data as $devs) {
            if (!is_array($devs)) {
                continue;
            }
            foreach ($devs as $buckets) {
                if (is_array($buckets)) {
                    $count += count($buckets);
                }
            }
        }
        return $count;
    }

    /**
     * Drop buckets older than the cutoff and apply the per-series ceiling.
     *
     * @param array<string,array<string,array<int,array<string,int|float>>>> $data
     * @param int $retention_hours Hours back from now to keep.
     * @return array<string,array<string,array<int,array<string,int|float>>>>
     */
    private static function prune($data, $retention_hours) {
        $cutoff = time() - ((int) $retention_hours * HOUR_IN_SECONDS);
        foreach ($data as $m => $devs) {
            if (!is_array($devs)) {
                unset($data[$m]);
                continue;
            }
            foreach ($devs as $d => $buckets) {
                if (!is_array($buckets)) {
                    unset($data[$m][$d]);
                    continue;
                }
                foreach ($buckets as $h => $bk) {
                    if ((int) $h < $cutoff) {
                        unset($data[$m][$d][$h]);
                    }
                }
                if (count($data[$m][$d]) > self::MAX_BUCKETS_PER_SERIES) {
                    ksort($data[$m][$d]);
                    $data[$m][$d] = array_slice(
                        $data[$m][$d],
                        -self::MAX_BUCKETS_PER_SERIES,
                        null,
                        true
                    );
                }
                if (empty($data[$m][$d])) {
                    unset($data[$m][$d]);
                }
            }
            if (empty($data[$m])) {
                unset($data[$m]);
            }
        }
        return $data;
    }

    /**
     * Read the current retention window in hours, clamped to safe limits.
     *
     * @return int
     */
    private static function current_retention_hours() {
        $days = (int) (class_exists('UCP_Options') ? UCP_Options::get('cwv_timeseries_retention_days', self::DEFAULT_RETENTION_DAYS) : self::DEFAULT_RETENTION_DAYS);
        $days = max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $days));
        return $days * 24;
    }
}
