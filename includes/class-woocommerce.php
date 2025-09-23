<?php
/**
 * WooCommerce Integration Class for CCC WOR Camping Plugin
 * Handles WooCommerce order processing and reservation confirmation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_WooCommerce {
    
    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'confirm_reservation'), 10, 1);
        add_action('woocommerce_order_status_failed', array($this, 'cancel_reservation'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_reservation'), 10, 1);
    }
    
    /**
     * Confirm reservation when order is completed
     */
    public function confirm_reservation($order_id) {
        global $wpdb;
        
        $order = wc_get_order($order_id);
        
        // Find pending reservation for this order's user
        $user_id = $order->get_user_id();
        $year = date('Y');
        
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_reservations 
             WHERE user_id = %d AND reservation_year = %d AND status = 'pending' 
             ORDER BY created_date DESC LIMIT 1",
            $user_id, $year
        ));
        
        if (!$reservation) {
            return;
        }
        
        // Update reservation to confirmed
        $wpdb->update(
            $wpdb->prefix . 'ccc_wor_reservations',
            array(
                'status' => 'confirmed',
                'order_id' => $order_id,
                'payment_completed_date' => current_time('mysql')
            ),
            array('reservation_id' => $reservation->reservation_id)
        );
        
        // Update or create annual status
        $this->update_annual_status($user_id, $reservation->site_id, $year);
        
        ccc_wor_log_transaction('reservation_confirmed', $user_id, $reservation->site_id, 'Reservation confirmed via order #' . $order_id);
    }
    
    /**
     * Cancel reservation when order fails or is cancelled
     */
    public function cancel_reservation($order_id) {
        global $wpdb;
        
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_reservations WHERE order_id = %d",
            $order_id
        ));
        
        if ($reservation && $reservation->status === 'pending') {
            $wpdb->update(
                $wpdb->prefix . 'ccc_wor_reservations',
                array('status' => 'cancelled'),
                array('reservation_id' => $reservation->reservation_id)
            );
            
            ccc_wor_log_transaction('reservation_cancelled', $reservation->user_id, $reservation->site_id, 'Reservation cancelled via order #' . $order_id);
        }
    }
    
    /**
     * Update annual status for user
     */
    private function update_annual_status($user_id, $site_id, $year) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_annual_status WHERE user_id = %d AND site_id = %s",
            $user_id, $site_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'ccc_wor_annual_status',
                array(
                    'last_reserved_year' => $year,
                    'status' => 'active',
                    'modified_date' => current_time('mysql')
                ),
                array('annual_id' => $existing->annual_id)
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ccc_wor_annual_status',
                array(
                    'user_id' => $user_id,
                    'site_id' => $site_id,
                    'last_reserved_year' => $year,
                    'status' => 'active'
                )
            );
        }
    }
}