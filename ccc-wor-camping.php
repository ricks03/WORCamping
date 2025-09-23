<?php
/**
 * Plugin Name: CCC WOR Camping
 * Description: Campground reservations with annual customers for Week of Rivers event
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: ccc-wor-camping
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CCC_WOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CCC_WOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CCC_WOR_VERSION', '1.0.0');

class CCC_WOR_Camping {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        new CCC_WOR_Admin();
        new CCC_WOR_Frontend();
        new CCC_WOR_Email();
        new CCC_WOR_WooCommerce();
        new CCC_WOR_Cron();
    }
    
    private function load_dependencies() {
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-database.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-admin.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-frontend.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-email.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-woocommerce.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-cron.php';
        require_once CCC_WOR_PLUGIN_PATH . 'includes/functions.php';
    }
    
    public function activate() {
        // Load database class
        require_once CCC_WOR_PLUGIN_PATH . 'includes/class-database.php';
        
        // Create database tables
        $database = new CCC_WOR_Database();
        $database->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('ccc_wor_cleanup_pending_reservations')) {
            wp_schedule_event(time(), 'hourly', 'ccc_wor_cleanup_pending_reservations');
        }
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ccc_wor_cleanup_pending_reservations');
    }
    
    private function set_default_options() {
        $defaults = array(
            'annual_start_date' => 'May 1',
            'general_availability_date' => 'June 1',
            'event_start_date' => '',
            'event_end_date' => '',
            'transaction_timeout' => 30,
            'campground_image_url' => ''
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('ccc_wor_' . $key) === false) {
                update_option('ccc_wor_' . $key, $value);
            }
        }
    }
}

// Initialize the plugin
new CCC_WOR_Camping();