<?php
/**
 * Frontend Class for CCC WOR Camping Plugin
 * Handles all frontend functionality and user-facing features
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('ccc_wor_reservations', array($this, 'reservations_shortcode'));
        add_action('wp_ajax_ccc_wor_frontend_action', array($this, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_ccc_wor_frontend_action', array($this, 'handle_ajax_request'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script('ccc-wor-frontend', CCC_WOR_PLUGIN_URL . 'assets/frontend.js', array('jquery'), CCC_WOR_VERSION, true);
        wp_enqueue_style('ccc-wor-frontend', CCC_WOR_PLUGIN_URL . 'assets/frontend.css', array(), CCC_WOR_VERSION);
        
        wp_localize_script('ccc-wor-frontend', 'ccc_wor_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ccc_wor_nonce')
        ));
    }
    
    /**
     * Reservations shortcode
     */
    public function reservations_shortcode($atts) {
        // Check if user is logged in and has member role
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view reservations.</p>';
        }
        
        $user = wp_get_current_user();
        if (!in_array('ccc_member', $user->roles)) {
            return '<p>You must be a member to make reservations.</p>';
        }
        
        ob_start();
        include CCC_WOR_PLUGIN_PATH . 'frontend/reservation-form.php';
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request() {
        check_ajax_referer('ccc_wor_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['ccc_action']);
        
        switch ($action) {
            case 'make_reservation':
                $this->make_reservation();
                break;
            case 'get_site_info':
                $this->get_site_info();
                break;
        }
        
        wp_die();
    }
    
    /**
     * Process reservation request
     */
    private function make_reservation() {
        global $wpdb;
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $user_id = get_current_user_id();
        $site_id = intval($_POST['site_id']);
        $guest_count = intval($_POST['guest_count']);
        $guest_names = array_map('sanitize_text_field', explode("\n", $_POST['guest_names']));
        $year = ccc_wor_get_working_year(); // CHANGED
        
        // Validation
        $validation_result = $this->validate_reservation($user_id, $site_id, $year);
        if ($validation_result !== true) {
            wp_send_json_error($validation_result);
        }
        
        // Create pending reservation
        $reservation_data = array(
            'user_id' => $user_id,
            'site_id' => $site_id,
            'reservation_year' => $year,
            'guest_count' => $guest_count,
            'status' => 'pending'
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ccc_wor_reservations', 
            $reservation_data,
            array('%d', '%d', '%d', '%d', '%s')
        );
        $reservation_id = $wpdb->insert_id;
        
        // Add guest names
        foreach ($guest_names as $guest_name) {
            if (!empty(trim($guest_name))) {
                $wpdb->insert(
                    $wpdb->prefix . 'ccc_wor_guest_names', array(
                      'reservation_id' => $reservation_id,
                      'guest_name' => trim($guest_name)
                ), array('%d', '%s'));  
            }
        }
        
        // Add products to cart and redirect to checkout
        $this->add_to_cart_and_redirect($site_id, $guest_count, $reservation_id);
        
        ccc_wor_log_transaction('reservation_created', $user_id, $site_id, 'Pending reservation created');
        
        wp_send_json_success(array('redirect_url' => wc_get_checkout_url()));
    }
    
    /**
     * Validate reservation request
     */
    private function validate_reservation($user_id, $site_id, $year) {
        global $wpdb;
        
        // Check availability period
        $current_period = $this->get_current_availability_period();
        if ($current_period === 'closed') {
            return 'Reservations are not currently open.';
        }
        
        // Check if site is already reserved (skip for field camping)
        if ($site_id !== 'field_camping') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_reservations 
                 WHERE site_id = %s AND reservation_year = %d AND status IN ('confirmed', 'pending')",
                $site_id, $year
            ));
            
            if ($existing > 0) {
                return "This site is already reserved for $year.";
            }
        }
        
        // Check annual period restrictions
        if ($current_period === 'annual') {
            $has_annual_status = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_annual_status 
                 WHERE user_id = %d AND site_id = %d AND status = 'active'",
                $user_id, $site_id
            ));
            
            if ($has_annual_status == 0) {
                return 'This site is only available to annual members during the annual period.';
            }
        }
        
        // Check if user already has a reservation
        $user_reservation = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_reservations 
             WHERE user_id = %d AND reservation_year = %d AND status IN ('confirmed', 'pending')",
            $user_id, $year
        ));
        
        if ($user_reservation > 0) {
            return 'You can only have one reservation per year.';
        }
        
        return true;
    }
    
    /**
     * Get current availability period
     */
    private function get_current_availability_period() {
        $annual_start = get_option('ccc_wor_annual_start_date', 'May 1');
        $general_start = get_option('ccc_wor_general_availability_date', 'June 1');
        $event_end = get_option('ccc_wor_event_end_date');
        
        $current_date = current_time('timestamp');
        $annual_timestamp = strtotime($annual_start . ' ' . date('Y'));
        $general_timestamp = strtotime($general_start . ' ' . date('Y'));
        $event_end_timestamp = $event_end ? strtotime($event_end) : PHP_INT_MAX;
        
        if ($current_date < $annual_timestamp) {
            return 'closed';
        } elseif ($current_date < $general_timestamp) {
            return 'annual';
        } elseif ($current_date <= $event_end_timestamp) {
            return 'general';
        } else {
            return 'closed';
        }
    }
    
    /**
     * Add products to WooCommerce cart
     */
    private function add_to_cart_and_redirect($site_id, $guest_count, $reservation_id) {
        global $wpdb;
        
        // Get site type
        $site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %d",
            $site_id
        ));
        
        // Find WooCommerce product for site type
        $product_id = $this->get_product_by_site_type($site->site_type);
        
        if ($product_id) {
            WC()->cart->empty_cart();
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array('reservation_id' => $reservation_id));
        }
        
        // Add guest products
        if ($guest_count > 0) {
            $guest_product_id = $this->get_product_by_name('Guest');
            if ($guest_product_id) {
                WC()->cart->add_to_cart($guest_product_id, $guest_count, 0, array(), array('reservation_id' => $reservation_id));
            }
        }
    }
    
    /**
     * Get WooCommerce product by site type
     */
    private function get_product_by_site_type($site_type) {
        $products = wc_get_products(array(
            'name' => $site_type,
            'limit' => 1,
            'status' => 'publish'
        ));
        
        return !empty($products) ? $products[0]->get_id() : null;
    }
    
    /**
     * Get WooCommerce product by name
     */
    private function get_product_by_name($name) {
        $products = wc_get_products(array(
            'name' => $name,
            'limit' => 1,
            'status' => 'publish'
        ));
        
        return !empty($products) ? $products[0]->get_id() : null;
    }
}