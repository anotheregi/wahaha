<?php
defined('BASEPATH') or exit('No direct script access allowed');

class PerformanceMonitor
{
    protected $CI;
    protected $start_time;
    protected $memory_start;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->start_time = microtime(true);
        $this->memory_start = memory_get_usage();
    }

    /**
     * Start performance monitoring
     */
    public function start_monitoring()
    {
        $this->start_time = microtime(true);
        $this->memory_start = memory_get_usage();
    }

    /**
     * End performance monitoring and log metrics
     */
    public function end_monitoring($operation = 'unknown')
    {
        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        $execution_time = ($end_time - $this->start_time) * 1000; // Convert to milliseconds
        $memory_used = $end_memory - $this->memory_start;

        // Log performance metrics
        $log_data = [
            'operation' => $operation,
            'execution_time_ms' => round($execution_time, 2),
            'memory_used_bytes' => $memory_used,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $this->CI->input->ip_address(),
            'uri' => $this->CI->uri->uri_string()
        ];

        // Log slow queries (>100ms) or high memory usage (>10MB)
        if ($execution_time > 100 || $memory_used > 10485760) {
            log_message('info', 'Performance Alert: ' . json_encode($log_data));
        }

        // Store in cache for monitoring dashboard
        $this->store_metrics($log_data);

        return $log_data;
    }

    /**
     * Store performance metrics for monitoring
     */
    private function store_metrics($data)
    {
        $cache_key = 'performance_metrics_' . date('Y-m-d-H');
        $metrics = $this->CI->cache->get($cache_key);

        if (!$metrics) {
            $metrics = [];
        }

        $metrics[] = $data;

        // Keep only last 1000 entries per hour
        if (count($metrics) > 1000) {
            array_shift($metrics);
        }

        $this->CI->cache->save($cache_key, $metrics, 86400); // Cache for 24 hours
    }

    /**
     * Get performance metrics for monitoring dashboard
     */
    public function get_metrics($hours = 24)
    {
        $metrics = [];
        $current_time = time();

        for ($i = 0; $i < $hours; $i++) {
            $hour_key = date('Y-m-d-H', $current_time - ($i * 3600));
            $cache_key = 'performance_metrics_' . $hour_key;
            $hour_metrics = $this->CI->cache->get($cache_key);

            if ($hour_metrics) {
                $metrics = array_merge($metrics, $hour_metrics);
            }
        }

        return $metrics;
    }

    /**
     * Monitor database query performance
     */
    public function monitor_query($query, $execution_time)
    {
        // Log slow queries (>50ms)
        if ($execution_time > 50) {
            log_message('info', 'Slow Query (' . round($execution_time, 2) . 'ms): ' . $query);

            $slow_query_data = [
                'query' => substr($query, 0, 500), // Truncate long queries
                'execution_time_ms' => round($execution_time, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->store_slow_query($slow_query_data);
        }
    }

    /**
     * Store slow query data
     */
    private function store_slow_query($data)
    {
        $cache_key = 'slow_queries_' . date('Y-m-d');
        $queries = $this->CI->cache->get($cache_key);

        if (!$queries) {
            $queries = [];
        }

        $queries[] = $data;

        // Keep only last 100 slow queries per day
        if (count($queries) > 100) {
            array_shift($queries);
        }

        $this->CI->cache->save($cache_key, $queries, 86400); // Cache for 24 hours
    }

    /**
     * Get slow queries for monitoring
     */
    public function get_slow_queries($days = 7)
    {
        $queries = [];
        $current_time = time();

        for ($i = 0; $i < $days; $i++) {
            $day_key = date('Y-m-d', $current_time - ($i * 86400));
            $cache_key = 'slow_queries_' . $day_key;
            $day_queries = $this->CI->cache->get($cache_key);

            if ($day_queries) {
                $queries = array_merge($queries, $day_queries);
            }
        }

        return $queries;
    }

    /**
     * Check system health
     */
    public function check_system_health()
    {
        $health = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $this->check_database_health(),
            'cache' => $this->check_cache_health(),
            'disk_space' => $this->check_disk_space(),
            'memory_usage' => $this->check_memory_usage()
        ];

        // Log health issues
        if (!$health['database']['status'] || !$health['cache']['status']) {
            log_message('error', 'System Health Alert: ' . json_encode($health));
        }

        return $health;
    }

    /**
     * Check database connectivity and performance
     */
    private function check_database_health()
    {
        $start = microtime(true);

        try {
            $this->CI->db->query('SELECT 1');
            $query_time = (microtime(true) - $start) * 1000;

            return [
                'status' => true,
                'query_time_ms' => round($query_time, 2),
                'connections' => $this->CI->db->conn_id ? 'connected' : 'disconnected'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check cache system health
     */
    private function check_cache_health()
    {
        try {
            $test_key = 'health_check_' . time();
            $this->CI->cache->save($test_key, 'test', 60);
            $result = $this->CI->cache->get($test_key);
            $this->CI->cache->delete($test_key);

            return [
                'status' => ($result === 'test'),
                'adapter' => $this->CI->cache->get_adapter()
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check disk space availability
     */
    private function check_disk_space()
    {
        $disk_free = disk_free_space('/');
        $disk_total = disk_total_space('/');
        $disk_used = $disk_total - $disk_free;
        $usage_percent = round(($disk_used / $disk_total) * 100, 2);

        return [
            'free_bytes' => $disk_free,
            'total_bytes' => $disk_total,
            'used_percent' => $usage_percent,
            'status' => ($usage_percent < 90) // Alert if >90% used
        ];
    }

    /**
     * Check memory usage
     */
    private function check_memory_usage()
    {
        $memory_used = memory_get_usage();
        $memory_peak = memory_get_peak_usage();
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));

        return [
            'current_bytes' => $memory_used,
            'peak_bytes' => $memory_peak,
            'limit_bytes' => $memory_limit,
            'usage_percent' => $memory_limit > 0 ? round(($memory_used / $memory_limit) * 100, 2) : 0
        ];
    }

    /**
     * Convert memory size string to bytes
     */
    private function convert_to_bytes($size_str)
    {
        $size_str = trim($size_str);
        $unit = strtolower($size_str[strlen($size_str) - 1]);
        $size = (int)$size_str;

        switch ($unit) {
            case 'g': $size *= 1024 * 1024 * 1024; break;
            case 'm': $size *= 1024 * 1024; break;
            case 'k': $size *= 1024; break;
        }

        return $size;
    }
}
