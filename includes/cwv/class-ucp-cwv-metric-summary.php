<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rolling CWV metric summary storage.
 */
final class UCP_CWV_Metric_Summary {
    /**
     * Update the rolling metric summary stored in the existing CWV option.
     *
     * @param string $metric Metric key.
     * @param float  $value  Metric value.
     * @return void
     */
    public static function record($metric, $value) {
        $data = get_option(UCP_CWV::OPTION_KEY, array());
        if (!is_array($data)) {
            $data = array();
        }
        if (empty($data[$metric]) || !is_array($data[$metric])) {
            $data[$metric] = array('count' => 0, 'sum' => 0, 'max' => 0, 'last' => 0, 'sample_rate' => 0.25);
        }

        $previous_count = absint($data[$metric]['count']);
        $previous_sum = (float) $data[$metric]['sum'];
        if ($previous_count >= UCP_CWV::MAX_SAMPLES_PER_METRIC) {
            $previous_average = $previous_count > 0 ? $previous_sum / $previous_count : 0;
            $data[$metric]['count'] = UCP_CWV::MAX_SAMPLES_PER_METRIC;
            $data[$metric]['sum'] = ($previous_average * (UCP_CWV::MAX_SAMPLES_PER_METRIC - 1)) + $value;
        } else {
            $data[$metric]['count'] = $previous_count + 1;
            $data[$metric]['sum'] = $previous_sum + $value;
        }
        $data[$metric]['max'] = max((float) $data[$metric]['max'], $value);
        $data[$metric]['last'] = time();
        $data[$metric]['sample_rate'] = 0.25;
        update_option(UCP_CWV::OPTION_KEY, $data, false);
    }
}
