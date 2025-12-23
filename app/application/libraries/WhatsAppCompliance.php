<?php
defined('BASEPATH') or exit('No direct script access allowed');

class WhatsAppCompliance
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Check if a message complies with WhatsApp Business API policies
     */
    public function validate_message($message, $recipient)
    {
        // Check message length (WhatsApp limit is 4096 characters)
        if (strlen($message) > 4096) {
            return ['valid' => false, 'error' => 'Message exceeds 4096 character limit'];
        }

        // Check for prohibited content
        if ($this->contains_prohibited_content($message)) {
            return ['valid' => false, 'error' => 'Message contains prohibited content'];
        }

        // Check if recipient has opted in (you should implement opt-in tracking)
        if (!$this->has_opted_in($recipient)) {
            return ['valid' => false, 'error' => 'Recipient has not opted in to receive messages'];
        }

        // Check rate limits (implement your own rate limiting logic)
        if ($this->exceeds_rate_limit($recipient)) {
            return ['valid' => false, 'error' => 'Rate limit exceeded for this recipient'];
        }

        return ['valid' => true];
    }

    /**
     * Check for prohibited content types
     */
    private function contains_prohibited_content($message)
    {
        $prohibited_patterns = [
            '/\b(?:viagra|casino|lottery|gambling)\b/i',  // Spam keywords
            '/https?:\/\/[^\s]+/',  // URLs (may be restricted)
            '/\b\d{10,}\b/',  // Long numbers that might be phone numbers
        ];

        foreach ($prohibited_patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if recipient has opted in (placeholder - implement based on your opt-in system)
     */
    private function has_opted_in($recipient)
    {
        // This should check your database for opt-in status
        // For now, we'll assume opt-in if the contact exists
        $this->CI->db->where('number', $recipient);
        $contact = $this->CI->db->get('all_contacts')->row();

        return $contact ? true : false;
    }

    /**
     * Check rate limits (placeholder - implement based on your rate limiting)
     */
    private function exceeds_rate_limit($recipient)
    {
        // WhatsApp Business API has rate limits
        // Implement your own rate limiting logic here
        // For example, limit to 250 messages per day per recipient

        $today = date('Y-m-d');
        $this->CI->db->where('number', $recipient);
        $this->CI->db->where('DATE(created_at)', $today);
        $message_count = $this->CI->db->count_all_results('reports');

        return $message_count >= 250;
    }

    /**
     * Log compliance violations
     */
    public function log_violation($type, $details, $user_id = null)
    {
        $log_data = [
            'type' => $type,
            'details' => json_encode($details),
            'user_id' => $user_id,
            'ip_address' => $this->CI->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        // You should create a compliance_log table for this
        // $this->CI->db->insert('compliance_log', $log_data);

        log_message('error', 'WhatsApp Compliance Violation: ' . $type . ' - ' . json_encode($details));
    }

    /**
     * Validate business profile information
     */
    public function validate_business_profile($profile_data)
    {
        $required_fields = ['business_name', 'business_description', 'contact_email'];

        foreach ($required_fields as $field) {
            if (empty($profile_data[$field])) {
                return ['valid' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Validate email format
        if (!filter_var($profile_data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }

        return ['valid' => true];
    }

    /**
     * Check if message template complies with WhatsApp requirements
     */
    public function validate_template($template)
    {
        // WhatsApp has specific requirements for message templates
        // This is a basic validation - expand based on WhatsApp's requirements

        if (empty($template['name']) || empty($template['content'])) {
            return ['valid' => false, 'error' => 'Template name and content are required'];
        }

        // Check for required opt-in language
        if (!preg_match('/opt.*in|consent|agree/i', $template['content'])) {
            return ['valid' => false, 'error' => 'Template should include opt-in language'];
        }

        return ['valid' => true];
    }
}
