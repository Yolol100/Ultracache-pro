<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rolling CWV/RUM metric summary storage.
 *
 * Keeps the original per-metric counters for backward compatibility and adds a
 * bounded histogram per metric/device for the admin field-data dashboard.
 */
final class UCP_CWV_Metric_Summary {
    const FIELD_KEY = '_field';
    const DEVICES = array('mobile', 'desktop', 'all');
    const METRICS = array('lcp', 'inp', 'cls', 'fcp', 'ttfb');

    /**
     * Update the rolling metric summary stored in the existing CWV option.
     *
     * @param string $metric Metric key.
     * @param float  $value  Metric value. CLS is stored as x1000 for legacy compatibility.
     * @param string $device Device bucket.
     * @param int    $sample_rate Sample rate percentage used by the collector.
     * @return void
     */
    public static function record($metric, $value, $device = 'all', $sample_rate = 25) {
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

        $lock_token = class_exists('UCP_CWV_Option_Lock') ? UCP_CWV_Option_Lock::acquire(UCP_CWV::OPTION_KEY) : '';
        if ('' === $lock_token) {
            return;
        }

        try {
            $data = get_option(UCP_CWV::OPTION_KEY, array());
            if (!is_array($data)) {
                $data = array();
            }

            $legacy_key = strtoupper($metric);
            if (empty($data[$legacy_key]) || !is_array($data[$legacy_key])) {
                $data[$legacy_key] = array('count' => 0, 'sum' => 0, 'max' => 0, 'last' => 0, 'sample_rate' => 0.25);
            }

            $previous_count = absint($data[$legacy_key]['count']);
            $previous_sum = (float) $data[$legacy_key]['sum'];
            if ($previous_count >= UCP_CWV::MAX_SAMPLES_PER_METRIC) {
                $previous_average = $previous_count > 0 ? $previous_sum / $previous_count : 0;
                $data[$legacy_key]['count'] = UCP_CWV::MAX_SAMPLES_PER_METRIC;
                $data[$legacy_key]['sum'] = ($previous_average * (UCP_CWV::MAX_SAMPLES_PER_METRIC - 1)) + $value;
            } else {
                $data[$legacy_key]['count'] = $previous_count + 1;
                $data[$legacy_key]['sum'] = $previous_sum + $value;
            }
            $data[$legacy_key]['max'] = max((float) $data[$legacy_key]['max'], $value);
            $data[$legacy_key]['last'] = time();
            $data[$legacy_key]['sample_rate'] = max(1, min(100, absint($sample_rate))) / 100;

            if (empty($data[self::FIELD_KEY]) || !is_array($data[self::FIELD_KEY])) {
                $data[self::FIELD_KEY] = array();
            }
            if (empty($data[self::FIELD_KEY][$metric][$device]) || !is_array($data[self::FIELD_KEY][$metric][$device])) {
                $data[self::FIELD_KEY][$metric][$device] = array('n' => 0, 'sum' => 0.0, 'hist' => array(), 'last' => 0);
            }

            $bucket = self::bucket_index($metric, $value);
            $row =& $data[self::FIELD_KEY][$metric][$device];
            $row['hist'] = isset($row['hist']) && is_array($row['hist']) ? $row['hist'] : array();
            $row['hist'][$bucket] = isset($row['hist'][$bucket]) ? absint($row['hist'][$bucket]) + 1 : 1;
            $row['hist'] = self::bounded_histogram($row['hist'], UCP_CWV::MAX_SAMPLES_PER_METRIC);
            $row['n'] = self::histogram_total($row['hist']);
            $row['sum'] = self::estimated_sum_from_hist($metric, $row['hist']);
            $row['last'] = time();
            unset($row);

            update_option(UCP_CWV::OPTION_KEY, $data, false);
        } finally {
            UCP_CWV_Option_Lock::release(UCP_CWV::OPTION_KEY, $lock_token);
        }


        if (class_exists('UCP_CWV_Timeseries')) {
            UCP_CWV_Timeseries::record($metric, $value, $device);
        }
    }

    /**
     * Approximate p75 + average per metric/device for display.
     *
     * @return array<string,array<string,array<string,int|float>>>
     */
    public static function get_summary() {
        $data = get_option(UCP_CWV::OPTION_KEY, array());
        $field = is_array($data) && !empty($data[self::FIELD_KEY]) && is_array($data[self::FIELD_KEY]) ? $data[self::FIELD_KEY] : array();
        $out = array();

        foreach (self::METRICS as $metric) {
            foreach (self::DEVICES as $device) {
                if (empty($field[$metric][$device]['n'])) {
                    continue;
                }
                $row = $field[$metric][$device];
                $n = max(1, absint($row['n']));
                $p75 = self::percentile_from_hist($metric, isset($row['hist']) ? $row['hist'] : array(), $n, 0.75);
                $avg = ((float) $row['sum']) / $n;
                $avg = 'cls' === $metric ? round($avg / 1000, 3) : round($avg, 2);
                $out[$metric][$device] = array(
                    'samples' => $n,
                    'avg'     => $avg,
                    'p75'     => $p75,
                );
            }
        }

        return $out;
    }

    /**
     * Clear only stored metric summaries. LCP profile tables remain intact.
     *
     * @return void
     */
    public static function reset() {
        delete_option(UCP_CWV::OPTION_KEY);
    }


    private static function histogram_total($hist) {
        $total = 0;
        foreach ((array) $hist as $count) {
            $total += absint($count);
        }
        return $total;
    }

    private static function bounded_histogram($hist, $max_samples) {
        $max_samples = max(1, absint($max_samples));
        $clean = array();
        foreach ((array) $hist as $bucket => $count) {
            $count = absint($count);
            if ($count <= 0) {
                continue;
            }
            $clean[(int) $bucket] = isset($clean[(int) $bucket]) ? $clean[(int) $bucket] + $count : $count;
        }

        while (self::histogram_total($clean) > $max_samples) {
            $largest_bucket = null;
            $largest_count = 0;
            foreach ($clean as $bucket => $count) {
                if ($count > $largest_count) {
                    $largest_bucket = $bucket;
                    $largest_count = $count;
                }
            }
            if (null === $largest_bucket) {
                break;
            }
            $clean[$largest_bucket]--;
            if ($clean[$largest_bucket] <= 0) {
                unset($clean[$largest_bucket]);
            }
        }

        ksort($clean);
        return $clean;
    }

    private static function estimated_sum_from_hist($metric, $hist) {
        $sum = 0.0;
        foreach ((array) $hist as $bucket => $count) {
            $sum += self::bucket_midpoint($metric, (int) $bucket) * absint($count);
        }
        return $sum;
    }

    private static function bucket_midpoint($metric, $bucket) {
        if ('cls' === $metric) {
            return (((float) $bucket * 25) + 12.5);
        }
        return (((float) $bucket * 250) + 125);
    }

    private static function bucket_index($metric, $value) {
        if ('cls' === $metric) {
            return min(20, (int) floor(((float) $value) / 25));
        }
        return min(24, (int) floor(((float) $value) / 250));
    }

    private static function percentile_from_hist($metric, $hist, $n, $p) {
        if (!is_array($hist) || $n <= 0) {
            return 0;
        }
        ksort($hist);
        $target = (int) ceil($p * $n);
        $cum = 0;
        foreach ($hist as $bucket => $count) {
            $cum += absint($count);
            if ($cum >= $target) {
                if ('cls' === $metric) {
                    return round((($bucket + 1) * 25) / 1000, 3);
                }
                return ($bucket + 1) * 250;
            }
        }
        return 0;
    }
}
