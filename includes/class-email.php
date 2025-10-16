<?php
/**
 * Email Class for CCC WOR Camping Plugin
 * Handles all email notifications
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_Email {
    
    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'send_confirmation_email'), 10, 1);
    }
    
    /**
     * Send confirmation email after payment completion
     */
    public function send_confirmation_email($order_id) {
        global $wpdb;
        
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        // Find reservation linked to this order
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_reservations WHERE order_id = %d",
            $order_id
        ));
        
        if (!$reservation) {
            return;
        }
        
        // Get site info
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %s",
            $reservation->site_id
        ));
        
        // Get guest names
        $guests = $wpdb->get_results($wpdb->prepare(
            "SELECT guest_name FROM {$wpdb->prefix}ccc_wor_guest_names WHERE reservation_id = %d",
            $reservation->reservation_id
        ));
        
        $guest_list = array();
        foreach ($guests as $guest) {
            $guest_list[] = $guest->guest_name;
        }
        
        // Get user info
        $user = get_userdata($user_id);
        
        // Get email template
        //$template_key = strtolower(str_replace(' ', '_', $site->site_type));
        //$template = get_option('ccc_wor_email_template_' . $template_key, $this->get_default_template($site->site_type));
        $template = get_option('ccc_wor_email_template_confirmation', $this->get_default_template($site->site_type));
        // Replace variables
        $variables = array(
            '{user_name}' => $user->display_name,
            '{user_email}' => $user->user_email,
            '{site_display_name}' => $site->display_name,
            '{site_type}' => $site->site_type,
            '{event_start_date}' => get_option('ccc_wor_event_start_date', ''),
            '{event_end_date}' => get_option('ccc_wor_event_end_date', ''),
            '{guest_count}' => $reservation->guest_count,
            '{guest_list}' => implode(', ', $guest_list),
            '{reservation_date}' => $reservation->created_date,
            '{year}' => $reservation->reservation_year
        );
        
        $subject = str_replace(array_keys($variables), array_values($variables), 'Week of Rivers Reservation Confirmed - {site_display_name}');
        $message = str_replace(array_keys($variables), array_values($variables), $template);
        
        wp_mail($user->user_email, $subject, $message);
        
        ccc_wor_log_transaction('confirmation_email_sent', $user_id, $site->site_id, 'Confirmation email sent');
    }
    
    /**
     * Send incomplete transaction email
     */
    public function send_incomplete_transaction_email($user_id, $site_id) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %d",
            $site_id
        ));
        
        $template = get_option('ccc_wor_email_template_incomplete', $this->get_default_incomplete_template());
        
        $variables = array(
            '{user_name}' => $user->display_name,
            '{site_display_name}' => $site->display_name,
            '{reservation_url}' => get_option('ccc_wor_reservation_url', '')        
        );
        
        $subject = 'Week of Rivers Reservation Not Completed';
        $message = str_replace(array_keys($variables), array_values($variables), $template);
        
        wp_mail($user->user_email, $subject, $message);
        
        ccc_wor_log_transaction('incomplete_email_sent', $user_id, $site_id, 'Incomplete transaction email sent');
    }
    
    /**
     * Send annual status removed email
     */
    public function send_annual_status_removed_email($user_id, $site_id,  $reason) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %d",
            $site_id
        ));
        
        $template = get_option('ccc_wor_email_template_annual_removed', $this->get_default_annual_removed_template());
        
        // Set context-specific reason
        $reason_text = '';
        switch ($reason) {
            case 'missed_years':
                $reason_text = 'missed reservation periods (2+ years without reserving)';
                break;
            case 'lost_membership':
                $reason_text = 'expired membership';
                break;
            default:
                $reason_text = 'administrative review';
                break;
        }
        
        $variables = array(
            '{user_name}' => $user->display_name,
            '{site_display_name}' => $site->display_name,
            '{general_availability_date}' => get_option('ccc_wor_general_availability_date', 'June 1'),
            '{removal_reason}' => $reason_text,
            '{reservation_url}' => get_option('ccc_wor_reservation_url', '')
        );
        
        $subject = 'Week of Rivers - Annual Site Status Update';
        $message = str_replace(array_keys($variables), array_values($variables), $template);
        
        wp_mail($user->user_email, $subject, $message);
        
        ccc_wor_log_transaction('annual_removed_email_sent', $user_id, $site_id, 'Annual status removed email sent');
    }
    
    /**
     * Get default confirmation template
     */
//     private function get_default_template($site_type) {
//         $defaults = ccc_wor_get_default_email_templates();
//         $template_key = strtolower(str_replace(' ', '_', $site_type));
//         return isset($defaults[$template_key]) ? $defaults[$template_key] : $defaults['campsite'];
//     }    
    private function get_default_template($site_type) {
        $defaults = ccc_wor_get_default_email_templates();
        return $defaults['confirmation'];
    }

    private function get_default_incomplete_template() {
        $defaults = ccc_wor_get_default_email_templates();
        return $defaults['incomplete'];
    }

    private function get_default_annual_removed_template() {
        $defaults = ccc_wor_get_default_email_templates();
        return $defaults['annual_removed'];
    }
}