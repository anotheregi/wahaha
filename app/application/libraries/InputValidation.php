<?php
defined('BASEPATH') or exit('No direct script access allowed');

class InputValidation
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Validate and sanitize input data
     */
    public function validate_input()
    {
        // Skip validation for certain controllers/methods if needed
        $controller = $this->CI->router->fetch_class();
        $method = $this->CI->router->fetch_method();

        // Skip validation for API endpoints that handle raw input
        if ($controller === 'api' && in_array($method, ['send_message', 'callback'])) {
            return;
        }

        // Validate GET parameters
        $this->_validate_get_params();

        // Validate POST parameters
        $this->_validate_post_params();

        // Log suspicious activity
        $this->_log_suspicious_activity();
    }

    /**
     * Validate GET parameters
     */
    private function _validate_get_params()
    {
        foreach ($_GET as $key => $value) {
            // Check for suspicious patterns
            if ($this->_is_suspicious_input($value)) {
                log_message('error', 'Suspicious GET parameter detected: ' . $key . ' = ' . $value);
                $this->_handle_suspicious_input();
            }

            // Sanitize the input
            $_GET[$key] = $this->_sanitize_input($value);
        }
    }

    /**
     * Validate POST parameters
     */
    private function _validate_post_params()
    {
        $csrf_token_name = $this->CI->security->get_csrf_token_name();
        foreach ($_POST as $key => $value) {
            // Skip CSRF token validation and sanitization
            if ($key === $csrf_token_name) {
                continue;
            }

            // Check for suspicious patterns
            if ($this->_is_suspicious_input($value)) {
                log_message('error', 'Suspicious POST parameter detected: ' . $key . ' = ' . $value);
                $this->_handle_suspicious_input();
            }

            // Sanitize the input
            $_POST[$key] = $this->_sanitize_input($value);
        }
    }

    /**
     * Check if input contains suspicious patterns
     */
    private function _is_suspicious_input($input)
    {
        if (!is_string($input)) {
            return false;
        }

        // Common attack patterns
        $suspicious_patterns = [
            '/<script[^>]*>.*?<\/script>/is',  // Script tags
            '/javascript:/i',                   // JavaScript URLs
            '/vbscript:/i',                     // VBScript URLs
            '/onload\s*=/i',                    // Event handlers
            '/onerror\s*=/i',                   // Error handlers
            '/union\s+select/i',                // SQL injection
            '/select\s+.*\s+from/i',            // SQL injection
            '/drop\s+table/i',                  // SQL injection
            '/--/',                             // SQL comments
            '/#/',                              // SQL comments
            '/\.\./',                           // Directory traversal
            '/\.\.\//',                         // Directory traversal
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize input data
     */
    private function _sanitize_input($input)
    {
        if (is_array($input)) {
            return array_map([$this, '_sanitize_input'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove potential script content
        $input = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $input);

        return $input;
    }

    /**
     * Handle suspicious input (log and potentially block)
     */
    private function _handle_suspicious_input()
    {
        // Get client IP
        $ip = $this->CI->input->ip_address();

        // Log the incident
        log_message('error', 'Suspicious input detected from IP: ' . $ip);

        // In production, you might want to:
        // - Block the IP temporarily
        // - Send alerts to administrators
        // - Redirect to an error page

        // For now, we'll just log it and continue
        // You can uncomment the following lines to block suspicious requests:
        // show_error('Invalid input detected', 400);
        // exit;
    }

    /**
     * Log suspicious activity for monitoring
     */
    private function _log_suspicious_activity()
    {
        $ip = $this->CI->input->ip_address();
        $user_agent = $this->CI->input->user_agent();
        $uri = $this->CI->uri->uri_string();

        // Log basic request info for monitoring
        log_message('info', 'Request: IP=' . $ip . ', UA=' . $user_agent . ', URI=' . $uri);
    }
}
