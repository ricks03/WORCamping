<?php
/**
 * Cron Jobs Class for CCC WOR Camping Plugin
 * Handles scheduled tasks like cleaning up pending reservations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_Cron {
    
    public function __construct() {
        add_action('ccc_wor_cleanup_pending_reservations', array($this, 'cleanup_pending_reservations'));
    }
    
    /**
     * Clean up expired pending reservations
     */
    public function cleanup_pending_reservations() {
        global $wpdb;
        
        $timeout_minutes = get_option('ccc_wor_transaction_timeout', 30);
        
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$timeout_minutes} minutes"));
        
        // Find expired pending reservations
        $expired = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_reservations 
             WHERE status = 'pending' AND created_date < %s",
            $cutoff_time
        ));
        
        foreach ($expired as $reservation) {
            // Send incomplete transaction email
            $email = new CCC_WOR_Email();
            $email->send_incomplete_transaction_email($reservation->user_id, $reservation->site_id);
            
            // Delete reservation
            $wpdb->delete(
                $wpdb->prefix . 'ccc_wor_reservations',
                array('reservation_id' => $reservation->reservation_id),
                array('%d')
            );
            
            ccc_wor_log_transaction('reservation_expired', $reservation->user_id, $reservation->site_id, 'Pending reservation expired and removed');
        }
    }
}