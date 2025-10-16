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
            'Campground',
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
        
        // Check if display name already exists
        $display_name = sanitize_text_field($data['display_name']);
        $existing_name = $wpdb->get_var($wpdb->prepare(
            "SELECT site_id FROM {$wpdb->prefix}ccc_wor_sites WHERE display_name = %s",
            $display_name
        ));
        
        if ($existing_name) {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&error=name_exists'));
            exit;
        }
        
        $site_data = array(
            'site_type' => sanitize_text_field($data['site_type']),
            'site_number' => sanitize_text_field($data['site_number']),
            'display_name' => sanitize_text_field($data['display_name']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ccc_wor_sites', 
            $site_data,
            array('%s', '%s', '%s', '%d')
        );
        if ($result) {
            $new_site_id = $wpdb->insert_id;
            ccc_wor_log_transaction('site_added', 0, $new_site_id, 'Site added: ' . $display_name);
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=site_added'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&error=insert_failed'));
            exit;
        }
    }
    
    /**
     * Edit existing site
     */
    private function edit_site($data) {
        global $wpdb;
        
        // Validate non-duplicates
        $site_id = intval($data['site_id']);
        $site_number = sanitize_text_field($data['site_number']);
        $display_name = sanitize_text_field($data['display_name']);
        
        // Check if site number already exists (excluding current site)
        $existing_number = $wpdb->get_var($wpdb->prepare(
            "SELECT site_id FROM {$wpdb->prefix}ccc_wor_sites WHERE site_number = %s AND site_id != %d",
            $site_number, $site_id
        ));
        
        if ($existing_number) {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&edit=' . $site_id . '&error=number_exists'));
            exit;
        }
        
        // Check if display name already exists (excluding current site)
        $existing_name = $wpdb->get_var($wpdb->prepare(
            "SELECT site_id FROM {$wpdb->prefix}ccc_wor_sites WHERE display_name = %s AND site_id != %d",
            $display_name, $site_id
        ));
        
        if ($existing_name) {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&edit=' . $site_id . '&error=name_exists'));
            exit;
        }
        
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
            array('site_id' => intval($data['site_id'])),
            array('%s', '%s', '%s', '%d', '%s'),  // formats for UPDATE data
            array('%d')  // format for WHERE clause
       );
        
        ccc_wor_log_transaction('site_updated', 0, intval($data['site_id']), 'Site updated');
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=site_updated'));
        exit;
    }
    
    /**
     * Delete a site
     */
    private function delete_site($site_id) {
        global $wpdb;
        $site_id = intval($site_id); // Ensure it's an integer
        
        // Check for active reservations
        $reservations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_reservations WHERE site_id = %d AND status = 'confirmed'",
            $site_id
        ));
        
        if ($reservations > 0) {
            wp_redirect(admin_url('admin.php?page=ccc-wor-sites&error=has_reservations'));
            exit;
        }
        
        // Delete site and related data
        $wpdb->delete($wpdb->prefix . 'ccc_wor_sites', array('site_id' => $site_id),array('%d'));
        $wpdb->delete($wpdb->prefix . 'ccc_wor_annual_status', array('site_id' => $site_id),array('%d'));
        
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
            case 'delete_reservation':
            $this->delete_reservation();
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
        
//         $results = $wpdb->get_results($wpdb->prepare("
//             SELECT 
//                 r.payment_completed_date as transaction_date,
//                 r.user_id,
//                 u.display_name as user_name,
//                 u.user_email,
//                 s.site_type,
//                 s.site_id,
//                 r.guest_count,
//                 GROUP_CONCAT(g.guest_name SEPARATOR ', ') as guest_names,
//                 wco.meta_value as total_amount
//             FROM {$wpdb->prefix}ccc_wor_reservations r
//             JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
//             JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
//             LEFT JOIN {$wpdb->prefix}ccc_wor_guest_names g ON r.reservation_id = g.reservation_id
//             LEFT JOIN {$wpdb->prefix}postmeta wco ON r.order_id = wco.post_id AND wco.meta_key = '_order_total'
//             WHERE r.reservation_year = %d AND r.status = 'confirmed'
//             GROUP BY r.reservation_id
//             ORDER BY r.payment_completed_date DESC
//         ", $year));
        
        
            
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.payment_completed_date as transaction_date,
                r.user_id,
                u.display_name as user_name,
                u.user_email,
                s.site_type,
                s.site_id,
                r.guest_count,
                r.order_id,
                GROUP_CONCAT(g.guest_name SEPARATOR ', ') as guest_names
            FROM {$wpdb->prefix}ccc_wor_reservations r
            JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
            JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
            LEFT JOIN {$wpdb->prefix}ccc_wor_guest_names g ON r.reservation_id = g.reservation_id
            WHERE r.reservation_year = %d AND r.status = 'confirmed'
            GROUP BY r.reservation_id
            ORDER BY r.payment_completed_date DESC
        ", $year));
        
        // Add payment amounts using WooCommerce API (HPOS compatible)
        foreach ($results as $result) {
            if ($result->order_id) {
                $order = wc_get_order($result->order_id);
                $result->total_amount = $order ? $order->get_total() : '0.00';
            } else {
                $result->total_amount = '0.00';
            }
        }
    
       
        
        
        
        
        
        
        $filename = $year . '_week_of_rivers_transactions_' . date('y_m_d_H') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array(
            'Transaction Date',
            'User Name',
            'User Email',
            'Site Type',
            'Number of Guests',
            'Guest Names',
            'Total Amount ($)'
        ));
        
        // Data
        foreach ($results as $row) {
            fputcsv($output, array(
                $row->transaction_date,
                $row->user_name,
                $row->user_email,
                $row->site_type,
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
      
      $imported = 0;
      $skipped = array();
      $errors = array();
      //$processed_lines = array();
      $line_number = 0;
      //$debug_info = array(); // ADD THIS FOR DEBUGGING
            
      // Process remaining rows
      while (($data = fgetcsv($handle)) !== false) {
          $line_number++;
          
          // DEBUG: Log what we're processing
          //$debug_info[] = "Line $line_number: " . implode('|', $data);

          
          // Skip header row if it exists
          if ($line_number === 1 && count($data) >= 3 && strtolower(trim($data[0])) === 'site_type') {
              //$debug_info[] = "Line $line_number: SKIPPED as header";
              continue; // Skip header row
          }
          
          if (count($data) < 3) {
              //$debug_info[] = "Line $line_number: SKIPPED - not enough columns";
              continue; 
          }
          
          $result = $this->process_site_row($data, $line_number);
          //$processed_lines[] = $result;
          //$debug_info[] = "Line $line_number: Status = " . $result['status'];
          
          if ($result['status'] === 'imported') $imported++;
          if ($result['status'] === 'skipped') $skipped[] = $result['display_name'];
          if ($result['status'] === 'error') $errors[] = $result['message'];
      }
      
      fclose($handle);
      
      // Store detailed results for display
      //set_transient('ccc_wor_import_results', $processed_lines, 300);
      
      // Store debug info
      //set_transient('ccc_wor_debug_info', $debug_info, 300);
    
      if (!empty($errors)) {
          set_transient('ccc_wor_import_errors', $errors, 300);
      }
      
      if (!empty($skipped)) {
          set_transient('ccc_wor_skipped_sites', $skipped, 300);
      }
      
      ccc_wor_log_transaction('sites_imported', 0, '', "Processed $line_number lines, Imported $imported sites," . count($skipped) . " skipped, " . count($errors) . " errors");
      
      wp_redirect(admin_url('admin.php?page=ccc-wor-sites&message=sites_imported&count=' . $imported . '&skipped=' . count($skipped) . '&errors=' . count($errors)));
      exit;
  }

  private function process_site_row($data, $line_number) {
      global $wpdb;
      
      if (count($data) < 3) {
          return array(
              'line' => $line_number,
              'data' => implode(',', $data),
              'status' => 'error',
              'message' => "Line $line_number: Not enough columns (need at least 3)"
          );
      }
      
      $site_type = trim($data[0]);
      $site_number = trim($data[1]);
      $display_name = trim($data[2]);
      $is_active = isset($data[3]) ? (int)$data[3] : 1;
      
      // Validate site type
      $valid_types = array('Campsite', 'Premium Campsite', 'RV Site', 'Cabin', 'Field Camping');
      if (!in_array($site_type, $valid_types)) {
          return array(
              'line' => $line_number,
              'data' => implode(',', $data),
              'status' => 'error',
              'message' => "Line $line_number: Invalid site type '$site_type'. Valid types: " . implode(', ', $valid_types)
          );
      }
      
      if (empty($site_number) || empty($display_name)) {
          return array(
              'line' => $line_number,
              'data' => implode(',', $data),
              'status' => 'error',
              'message' => "Line $line_number: Site number and display name are required"
          );
      }
      
      // Check if site already exists
      $existing = $wpdb->get_var($wpdb->prepare(
          "SELECT site_id FROM {$wpdb->prefix}ccc_wor_sites WHERE display_name = %s",
          $display_name
      ));
      
      if ($existing) {
          return array(
              'line' => $line_number,
              'data' => implode(',', $data),
              'status' => 'skipped',
              'display_name' => $display_name,
              'message' => "Line $line_number: '$display_name' already exists"
          );
      }
      
      // Insert the site
      $result = $wpdb->insert(
          $wpdb->prefix . 'ccc_wor_sites',
          array(
              'site_type' => $site_type,
              'site_number' => $site_number,
              'display_name' => $display_name,
              'is_active' => $is_active
          ),
          array('%s', '%s', '%s', '%d')
      );
      
      if ($result === false) {
          return array(
              'line' => $line_number,
              'data' => implode(',', $data),
              'status' => 'error',
              'message' => "Line $line_number: Database error - " . $wpdb->last_error
          );
      }
      
      return array(
          'line' => $line_number,
          'data' => implode(',', $data),
          'status' => 'imported',
          'display_name' => $display_name,
          'message' => "Line $line_number: '$display_name' imported successfully"
      );
  }  
}