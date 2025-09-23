<?php
/**
 * Admin Class for CCC WOR Camping Plugin
 * Handles all admin interface functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_ccc_wor_admin_action', array($this, 'handle_ajax_request'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            'Campground Reservations',
            'Campground',
            'manage_options',
            'ccc-wor-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-location-alt',
            30
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Site Management',
            'Site Management',
            'manage_options',
            'ccc-wor-sites',
            array($this, 'sites_page')
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Reservations',
            'Reservations',
            'manage_options',
            'ccc-wor-reservations',
            array($this, 'reservations_page')
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Annual Members',
            'Annual Members',
            'manage_options',
            'ccc-wor-annual',
            array($this, 'annual_page')
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Email Templates',
            'Email Templates',
            'manage_options',
            'ccc-wor-emails',
            array($this, 'emails_page')
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ccc-wor-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ccc-wor-dashboard',
            'Transaction Log',
            'Transaction Log',
            'manage_options',
            'ccc-wor-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ccc-wor') !== false) {
            wp_enqueue_script('ccc-wor-admin', CCC_WOR_PLUGIN_URL . 'assets/admin.js', array('jquery'), CCC_WOR_VERSION, true);
            wp_enqueue_style('ccc-wor-admin', CCC_WOR_PLUGIN_URL . 'assets/admin.css', array(), CCC_WOR_VERSION);
            
            wp_localize_script('ccc-wor-admin', 'ccc_wor_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ccc_wor_nonce')
            ));
        }
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $this->render_admin_page('dashboard');
    }
    
    /**
     * Sites management page
     */
    public function sites_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_sites_nonce')) {
            $this->handle_sites_form();
        }
        $this->render_admin_page('sites');
    }
    
    /**
     * Reservations page
     */
    public function reservations_page() {
        $this->render_admin_page('reservations');
    }
    
    /**
     * Annual members page
     */
    public function annual_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_annual_nonce')) {
            $this->handle_annual_form();
        }
        $this->render_admin_page('annual');
    }
    
    /**
     * Email templates page
     */
    public function emails_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_email_nonce')) {
            $this->handle_email_form();
        }
        $this->render_admin_page('emails');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_settings_nonce')) {
            $this->handle_settings_form();
        }
        $this->render_admin_page('settings');
    }
    
    /**
     * Transaction log page
     */
    public function logs_page() {
        $this->render_admin_page('logs');
    }
    
    /**
     * Render admin page template
     */
    private function render_admin_page($page) {
        include CCC_WOR_PLUGIN_PATH . "admin/pages/{$page}.php";
    }
    
    /**
     * Handle site management form submissions
     */
    private function handle_sites_form() {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_site':
                    $this->add_site($_POST);
                    break;
                case 'edit_site':
                    $this->edit_site($_POST);
                    break;
                case 'delete_site':
                    $this->delete_site($_POST['site_id']);
                    break;
                case 'bulk_import':
                    $this->bulk_import_sites($_FILES['csv_file']);
                    break;
                case 'import_sites':
                    $this->import_sites($_FILES['sites_csv']);
                    break;
            }
        }
    }
    
    /**
     * Add a new site
     */
    private function add_site($data) {
        global $wpdb;
        
        $site_data = array(
            'site_id' => sanitize_text_field($data['site_id']),
            'site_type' => sanitize_text_field($data['site_type']),
            'site_number' => sanitize_text_field($data['site_number']),
            'display_name' => sanitize_text_field($data['display_name']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        $wpdb->insert($wpdb->prefix . 'ccc_wor_sites', $site_data);
        
        ccc_wor_log_transaction('site_added', 0, $site_data['site_id'], 'Site added: ' . $site_data['display_name']);
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=site_added'));
        exit;
    }
    
    /**
     * Edit existing site
     */
    private function edit_site($data) {
        global $wpdb;
        
        $site_data = array(
            'site_type' => sanitize_text_field($data['site_type']),
            'site_number' => sanitize_text_field($data['site_number']),
            'display_name' => sanitize_text_field($data['display_name']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'modified_date' => current_time('mysql')
        );
        
        $wpdb->update(
            $wpdb->prefix . 'ccc_wor_sites',
            $site_data,
            array('site_id' => sanitize_text_field($data['site_id']))
        );
        
        ccc_wor_log_transaction('site_updated', 0, sanitize_text_field($data['site_id']), 'Site updated');
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=site_updated'));
        exit;
    }
    
    /**
     * Delete a site
     */
    private function delete_site($site_id) {
        global $wpdb;
        
        // Check for active reservations
        $reservations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_reservations WHERE site_id = %s AND status = 'confirmed'",
            $site_id
        ));
        
        if ($reservations > 0) {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&error=has_reservations'));
            exit;
        }
        
        // Delete site and related data
        $wpdb->delete($wpdb->prefix . 'ccc_wor_sites', array('site_id' => $site_id));
        $wpdb->delete($wpdb->prefix . 'ccc_wor_annual_status', array('site_id' => $site_id));
        
        ccc_wor_log_transaction('site_deleted', 0, $site_id, 'Site deleted');
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=site_deleted'));
        exit;
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request() {
        check_ajax_referer('ccc_wor_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['ccc_action']);
        
        switch ($action) {
            case 'export_transactions':
                $this->export_transactions();
                break;
            case 'send_reminder_emails':
                $this->send_reminder_emails();
                break;
        }
        
        wp_die();
    }
    
    /**
     * Export transactions to CSV
     */
    private function export_transactions() {
        global $wpdb;
        
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.payment_completed_date as transaction_date,
                r.user_id,
                u.display_name as user_name,
                u.user_email,
                s.site_type,
                s.site_id,
                r.guest_count,
                GROUP_CONCAT(g.guest_name SEPARATOR ', ') as guest_names,
                wco.meta_value as total_amount
            FROM {$wpdb->prefix}ccc_wor_reservations r
            JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
            JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
            LEFT JOIN {$wpdb->prefix}ccc_wor_guest_names g ON r.reservation_id = g.reservation_id
            LEFT JOIN {$wpdb->prefix}postmeta wco ON r.order_id = wco.post_id AND wco.meta_key = '_order_total'
            WHERE r.reservation_year = %d AND r.status = 'confirmed'
            GROUP BY r.reservation_id
            ORDER BY r.payment_completed_date DESC
        ", $year));
        
        $filename = 'week_of_rivers_transactions_' . date('Y-m-d-H-i') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array(
            'Transaction Date',
            'User ID', 
            'User Name',
            'User Email',
            'Site Type',
            'Site ID',
            'Number of Guests',
            'Guest Names',
            'Total Amount ($)'
        ));
        
        // Data
        foreach ($results as $row) {
            fputcsv($output, array(
                $row->transaction_date,
                $row->user_id,
                $row->user_name,
                $row->user_email,
                $row->site_type,
                $row->site_id,
                $row->guest_count,
                $row->guest_names,
                $row->total_amount
            ));
        }
        
        fclose($output);
        
        ccc_wor_log_transaction('transactions_exported', 0, '', "Exported transactions for year: $year");
        
        exit;
    }
    
      private function import_sites($file) {
      if ($file['error'] !== 0) {
          wp_redirect(admin_url('admin.php?page=ccc-wor-sites&error=upload_failed'));
          exit;
      }
      
      global $wpdb;
      $handle = fopen($file['tmp_name'], 'r');
      
      // Skip header row
      fgetcsv($handle);
      
      $imported = 0;
      while (($data = fgetcsv($handle)) !== false) {
          if (count($data) < 5) continue;
          
          $site_data = array(
              'site_id' => sanitize_text_field($data[0]),
              'site_type' => sanitize_text_field($data[1]),
              'site_number' => sanitize_text_field($data[2]),
              'display_name' => sanitize_text_field($data[3]),
              'is_active' => (int)$data[4]
          );
          
          $wpdb->replace($wpdb->prefix . 'ccc_wor_sites', $site_data);
          $imported++;
      }
      
      fclose($handle);
      
      ccc_wor_log_transaction('sites_imported', 0, '', "Imported $imported sites");
      
      wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=sites_imported&count=' . $imported));
      exit;
  }    
}