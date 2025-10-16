<?php
/**
 * Helper Functions for CCC WOR Camping Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log a transaction to the audit trail
 */
function ccc_wor_log_transaction($action, $target_user_id, $site_id, $details) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 0;
    }
    
    $wpdb->insert(
        $wpdb->prefix . 'ccc_wor_transaction_log',
        array(
            'user_id' => $user_id,
            'action' => $action,
            'target_user_id' => $target_user_id,
            'site_id' => $site_id,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ),
        array('%d', '%s', '%d', '%s', '%s', '%s')  // ADD THIS
    );
}

/**
 * Get sites based on criteria
 */
function ccc_wor_get_sites($args = array()) {
    global $wpdb;
    
    $where = array('1=1');
    
    if (isset($args['site_type'])) {
        $where[] = $wpdb->prepare('site_type = %s', $args['site_type']);
    }
    
    if (isset($args['is_active'])) {
        $where[] = $wpdb->prepare('is_active = %d', $args['is_active']);
    }
    
    $where_sql = implode(' AND ', $where);
    
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE $where_sql ORDER BY site_type, CAST(site_number AS UNSIGNED), site_number");
}

/**
 * Get reservations for a specific year
 */
function ccc_wor_get_reservations($year = null) {
    global $wpdb;
    
    if (!$year) {
        $year = date('Y');
    }
    
//     return $wpdb->get_results($wpdb->prepare(
//         "SELECT r.*, s.display_name, s.site_type, u.display_name as user_name, u.user_email
//          FROM {$wpdb->prefix}ccc_wor_reservations r
//          JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
//          JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
//          WHERE r.reservation_year = %d
//          ORDER BY r.created_date DESC",
//         $year
//     ));   
    
//    return $wpdb->get_results($wpdb->prepare(
//         "SELECT r.*, s.display_name, s.site_type, u.display_name as user_name, u.user_email,
//                 wco.meta_value as total_amount
//          FROM {$wpdb->prefix}ccc_wor_reservations r
//          JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
//          JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
//          LEFT JOIN {$wpdb->prefix}postmeta wco ON r.order_id = wco.post_id AND wco.meta_key = '_order_total'
//          WHERE r.reservation_year = %d
//          ORDER BY r.created_date DESC",
//         $year
//     ));    

//     return $wpdb->get_results($wpdb->prepare(
//         "SELECT r.*, s.display_name, s.site_type, u.display_name as user_name, u.user_email,
//                 wco.meta_value as total_amount
//          FROM {$wpdb->prefix}ccc_wor_reservations r
//          JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
//          JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
//          LEFT JOIN {$wpdb->prefix}postmeta wco ON r.order_id = wco.post_id AND wco.meta_key = '_order_total'
//          WHERE r.reservation_year = %d
//          ORDER BY r.created_date DESC",
//         $year
//     ));

      $reservations = $wpdb->get_results($wpdb->prepare(
          "SELECT r.*, s.display_name, s.site_type, u.display_name as user_name, u.user_email
           FROM {$wpdb->prefix}ccc_wor_reservations r
           JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
           JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
           WHERE r.reservation_year = %d
           ORDER BY r.created_date DESC",
          $year
      ));
      
      // Add payment amounts using WooCommerce API (HPOS compatible)
      foreach ($reservations as $reservation) {
          if ($reservation->order_id) {
              $order = wc_get_order($reservation->order_id);
              $reservation->total_amount = $order ? $order->get_total() : null;
          } else {
              $reservation->total_amount = null;
          }
      }
      
      return $reservations;
}



/**
 * Check if all required WooCommerce products exist
 */
function ccc_wor_check_woocommerce_products() {
    $required_products = array('Campsite', 'Premium Campsite', 'RV Site', 'Cabin', 'Field Camping', 'Guest');
    $missing = array();
    $visibility_issues = array();
    
    foreach ($required_products as $product_name) {
        // Try exact match first
        $products = wc_get_products(array(
            'name' => $product_name,
            'limit' => 1,
            'status' => 'publish'
        ));
        
        // If not found, try search
        if (empty($products)) {
            $products = wc_get_products(array(
                's' => $product_name,
                'limit' => 1,
                'status' => 'publish'
            ));
        }
        
        if (empty($products)) {
            $missing[] = $product_name;
        } else {
            // Check if product is properly hidden
            $product = $products[0];
            if ($product->get_catalog_visibility() !== 'hidden') {
                $visibility_issues[] = $product_name;
            }
        }
    }
    
    // Combine issues for the warning
    $issues = array();
    if (!empty($missing)) {
        $issues = array_merge($issues, $missing);
    }
    if (!empty($visibility_issues)) {
        foreach ($visibility_issues as $product) {
            $issues[] = $product . ' (not hidden)';
        }
    }
    
    return $issues;
}

/**
 * Get current availability period
 */
function ccc_wor_get_current_period() {
    $annual_start = get_option('ccc_wor_annual_start_date', date('Y') . '-05-01');
    $general_start = get_option('ccc_wor_general_availability_date', date('Y') . '-06-01');
    $event_end = get_option('ccc_wor_event_end_date');
    
    $current_date = current_time('timestamp');
    $annual_timestamp = strtotime($annual_start);
    $general_timestamp = strtotime($general_start);
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
 * Get status message based on current period
 */
function ccc_wor_get_status_message($period) {
    $annual_start = get_option('ccc_wor_annual_start_date', date('Y') . '-05-01');
    $general_start = get_option('ccc_wor_general_availability_date', date('Y') . '-06-01');
    $event_start = get_option('ccc_wor_event_start_date');
    $event_end = get_option('ccc_wor_event_end_date');
    
    // Format dates nicely for display
    $annual_display = date('F j, Y', strtotime($annual_start));
    $general_display = date('F j, Y', strtotime($general_start));

    switch ($period) {
        case 'closed':
            if (current_time('timestamp') < strtotime($annual_start)) {
                return "Reservations are not yet open. <br>Annual/Perm site reservations open on " . date('F j, Y', strtotime($annual_start)) . ".<br>General site reservations open on " . date('F j, Y', strtotime($general_start)) . ".";
            } else {
                return "Reservations are closed for this year.";
            }
        case 'annual':
            return "Annual site reservation period is open from " . date('F j, Y', strtotime($annual_start)) . " until " . date('F j, Y', strtotime($general_start)) . ".<br>General reservations open on " . date('F j, Y', strtotime($general_start)) . ".";
        case 'general':
            return "General site reservation period is open. All available sites can be reserved.";
            //<br>Event dates: " . date('F j, Y', strtotime($event_start)) . " to " . date('F j, Y', strtotime($event_end));
    }
}

/**
 * Handle settings form submission
 */
add_action('admin_init', 'ccc_wor_handle_settings_submit');
function ccc_wor_handle_settings_submit() {
    if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_settings_nonce')) {
        update_option('ccc_wor_event_start_date', sanitize_text_field($_POST['event_start_date']));
        update_option('ccc_wor_event_end_date', sanitize_text_field($_POST['event_end_date']));
        update_option('ccc_wor_annual_start_date', sanitize_text_field($_POST['annual_start_date']));
        update_option('ccc_wor_general_availability_date', sanitize_text_field($_POST['general_availability_date']));
        update_option('ccc_wor_transaction_timeout', intval($_POST['transaction_timeout']));
        update_option('ccc_wor_campground_image_url', esc_url($_POST['campground_image_url']));
        update_option('ccc_wor_reservation_url', esc_url($_POST['reservation_url']));
        
        ccc_wor_log_transaction('settings_updated', 0, '', 'Plugin settings updated');
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-settings&message=saved'));
        exit;
    }
}

/**
 * Handle email template form submission
 */
add_action('admin_init', 'ccc_wor_handle_email_submit');
function ccc_wor_handle_email_submit() {
    if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_email_nonce')) {
    
//         $site_types = array('campsite', 'premium_campsite', 'rv_site', 'cabin');
//         foreach ($site_types as $type) {
//             if (isset($_POST['template_' . $type])) {
//                 update_option('ccc_wor_email_template_' . $type, wp_kses_post($_POST['template_' . $type]));
//             }
//         }
 
        if (isset($_POST['template_confirmation'])) {
            update_option('ccc_wor_email_template_confirmation', wp_kses_post($_POST['template_confirmation']));
        }
        
        update_option('ccc_wor_email_template_incomplete', wp_kses_post($_POST['template_incomplete']));
        update_option('ccc_wor_email_template_annual_removed', wp_kses_post($_POST['template_annual_removed']));
        update_option('ccc_wor_email_template_reminder', wp_kses_post($_POST['template_reminder']));
        
        ccc_wor_log_transaction('email_templates_updated', 0, '', 'Email templates updated');
        
        wp_redirect(admin_url('admin.php?page=ccc-wor-emails&message=saved'));
        exit;
    }
}

/**
 * Handle annual member form submission
 */
add_action('admin_init', 'ccc_wor_handle_annual_submit');
function ccc_wor_handle_annual_submit() {
    if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_annual_nonce')) {
        if (isset($_POST['action']) && $_POST['action'] === 'add_annual') {
            global $wpdb;
            
            $user_id = intval($_POST['user_id']);
            $site_id = intval($_POST['site_id']);
            $last_reserved_year = intval($_POST['last_reserved_year']);
            
            // Check if this user already has annual status for this site
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT annual_id FROM {$wpdb->prefix}ccc_wor_annual_status 
                 WHERE user_id = %d AND site_id = %d",
                $user_id,
                $site_id
            ));
            
            if ($existing) {
                wp_redirect(admin_url('admin.php?page=ccc-wor-annual&error=already_exists'));
                exit;
            }
            
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ccc_wor_annual_status',
                array(
                    'user_id' => intval($_POST['user_id']),
                    'site_id' => intval($_POST['site_id']),
                    'last_reserved_year' => intval($_POST['last_reserved_year']),
                    'status' => 'active'
                ),
                array('%d', '%d', '%d', '%s')  // ADD THIS LINE
            );            
            if ($result) {
                ccc_wor_log_transaction('annual_status_added', intval($_POST['user_id']), sanitize_text_field($_POST['site_id']), 'Annual status manually added');
                wp_redirect(admin_url('admin.php?page=ccc-wor-annual&message=annual_added'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=ccc-wor-annual&error=insert_failed'));
                exit;
            }
        }
    }
}
/**
 * Handle CSV import for historical data
 */
add_action('admin_init', 'ccc_wor_handle_csv_import');
function ccc_wor_handle_csv_import() {
    if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ccc_wor_csv_import_nonce')) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            global $wpdb;
            $imported = 0;
            $errors = array();
            $line_number = 0;
            $has_header = false;
            
            while (($data = fgetcsv($handle)) !== false) {
                $line_number++;

                // Skip header row if it exists
                if ($line_number === 1 && count($data) >= 3 && strtolower(trim($data[0])) === 'member_email') {
                    $has_header = true;
                    continue; // Skip header row
                }
                
                if (count($data) < 3) {
                    continue;
                }
                
                $email = sanitize_email($data[0]);
                $site_number = sanitize_text_field($data[1]);
                $year = intval($data[2]);
                
                // Find user by email
                $user = get_user_by('email', $email);
                if (!$user) {
                    $errors[] = "User not found: $email";
                    continue;
                }
                
                // Check if user has ccc_member role
//                 if (!in_array('ccc_member', $user->roles)) {
//                     $errors[] = "User $email does not have ccc_member role";
//                     continue;
//                 }
                
                // Find site by site_number
                $site = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_number = %s",
                    $site_number
                ));

                $site_id = $site ? $site->site_id : null;

                
                if (!$site_id) {
                    $errors[] = "Site not found: $site_number";
                    continue;
                }
                
                // Insert or update annual status
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ccc_wor_annual_status WHERE user_id = %d AND site_id = %d",
                    $user->ID, $site_id
                ));
                
                if ($existing) {
                    if ($year > $existing->last_reserved_year) {
                        $wpdb->update(
                            $wpdb->prefix . 'ccc_wor_annual_status',
                            array('last_reserved_year' => $year),
                            array('annual_id' => $existing->annual_id),
                            array('%d'),  // ADD THIS - for data
                            array('%d')   // ADD THIS - for WHERE
                        );
                        $imported++; // count as processed
                    }
                } else {
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'ccc_wor_annual_status',
                        array(
                            'user_id' => $user->ID,
                            'site_id' => $site_id,
                            'last_reserved_year' => $year,
                            'status' => 'active'
                        ),
                        array('%d', '%d', '%d', '%s')
                    );
                    if ($result) {
                        $imported++; // Count as processed
                    }
                }
            }
            
            fclose($handle);
            
            ccc_wor_log_transaction('csv_imported', 0, '', "Imported $imported records");

            if (!empty($errors)) {
                set_transient('ccc_wor_import_errors', $errors, 60);
            }
            
            wp_redirect(admin_url('admin.php?page=ccc-wor-annual&message=imported&count=' . $imported));
            exit;
        }
    }
}

/**
 * Export annual data to CSV
 */
add_action('wp_ajax_ccc_wor_export_annual', 'ccc_wor_export_annual_data');
function ccc_wor_export_annual_data() {
    check_ajax_referer('ccc_wor_annual', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    
    // Get all annual status records with user emails and site numbers
    $annual_data = $wpdb->get_results("
        SELECT 
            u.user_email,
            s.site_number,
            a.last_reserved_year
        FROM {$wpdb->prefix}ccc_wor_annual_status a
        JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}ccc_wor_sites s ON a.site_id = s.site_id
        WHERE a.status = 'active'
        ORDER BY s.site_type, s.site_number
    ");
    
    $filename = 'ccc_wor_annual_data_' . date('Y-m-d-H-i') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');

    // Add header row
    fputcsv($output, array('member_email', 'site_number', 'last_reserved_year'));

    foreach ($annual_data as $row) {        
            fputcsv($output, array(
            $row->user_email,
            $row->site_number,
            $row->last_reserved_year
        ));
    }
    
    fclose($output);
    
    ccc_wor_log_transaction('annual_data_exported', 0, '', 'Annual data exported');
    
    exit;
}

/**
 * AJAX handler for deleting annual member
 */
add_action('wp_ajax_ccc_wor_delete_annual_member', 'ccc_wor_ajax_delete_annual_member');
function ccc_wor_ajax_delete_annual_member() {
    check_ajax_referer('ccc_wor_annual', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    
    $annual_id = intval($_POST['annual_id']);
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == 1;
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : 'general';
    
    $annual = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ccc_wor_annual_status WHERE annual_id = %d",
        $annual_id
    ));
    
    if (!$annual) {
        wp_send_json_error('Annual status not found');
    }

    // Send email if requested
    if ($send_email) {
        $email = new CCC_WOR_Email();
        $email->send_annual_status_removed_email($annual->user_id, $annual->site_id, $reason);
    }
     
    $wpdb->delete(
        $wpdb->prefix . 'ccc_wor_annual_status',
        array('annual_id' => $annual_id),
        array('%d')  // ADD THIS
    );
    
    ccc_wor_log_transaction('annual_member_deleted', $annual->user_id, $annual->site_id, 'Annual member deleted');
    
    wp_send_json_success();
}

/**
 * AJAX handler for sending reminder emails
 */
add_action('wp_ajax_ccc_wor_admin_action', 'ccc_wor_ajax_admin_actions');
function ccc_wor_ajax_admin_actions() {
    check_ajax_referer('ccc_wor_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $action = sanitize_text_field($_POST['ccc_action']);
    
    switch ($action) {
        case 'send_reminder_emails':
            ccc_wor_send_reminder_emails();
            break;
        case 'flush_transaction_log':
            ccc_wor_flush_transaction_log();
            break;
    }
}

function ccc_wor_flush_transaction_log() {
    global $wpdb;
    
    $deleted_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_transaction_log");
    
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ccc_wor_transaction_log");
    
    // Log the flush action (ironic, but important for security)
    ccc_wor_log_transaction('transaction_log_flushed', 0, '', "Cleared $deleted_count transaction log entries");
    
    wp_send_json_success(array('message' => "Cleared $deleted_count log entries"));
}

function ccc_wor_send_reminder_emails() {
    global $wpdb;
    
    $current_year = ccc_wor_get_working_year(); // CHANGED
    
    // Find annual members who haven't reserved this year
    $members = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT a.user_id, a.site_id, u.user_email, s.display_name
        FROM {$wpdb->prefix}ccc_wor_annual_status a
        JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}ccc_wor_sites s ON a.site_id = s.site_id
        LEFT JOIN {$wpdb->prefix}ccc_wor_reservations r ON a.user_id = r.user_id 
            AND r.reservation_year = %d AND r.status IN ('confirmed', 'pending')
        WHERE a.status = 'active' AND r.reservation_id IS NULL
    ", $current_year));

      
    $count = 0;
    foreach ($members as $member) {
        $user = get_userdata($member->user_id);
        
        // Skip if user no longer has ccc_member role
        if (!in_array('ccc_member', $user->roles)) {
            continue;
        }
        
        // Skip if they missed too many years (optional - depends on your policy)
        // This would skip users who haven't reserved in 2+ years
        $annual_record = $wpdb->get_row($wpdb->prepare(
            "SELECT last_reserved_year FROM {$wpdb->prefix}ccc_wor_annual_status 
             WHERE user_id = %d AND site_id = %d", 
            $member->user_id, $member->site_id
        ));
        // If working year is 2026, skip people who last reserved in 2023 or earlier
        // (they missed both 2024 and 2025)
        if ($annual_record && $annual_record->last_reserved_year < ($current_year - 2)) {
            continue;
        }
        
        $reservation_url = get_option('ccc_wor_reservation_url', '');
        $general_availability_date = get_option('ccc_wor_general_availability_date', '');
        
        $subject = 'Week of Rivers - Reserve Your Annual Site';
        // Get the template
        $template = get_option('ccc_wor_email_template_reminder', '');
        if (empty($template)) {
            $template = "Dear {user_name},\n\nYou have annual status for {site_display_name} but have not yet made your reservation for this year.\n\nPlease log in to reserve your site before the general availability date on {general_availability_date}.\n\n{reservation_url}\n\nThank you!";
        }

        // Replace variables
        $variables = array(
            '{user_name}' => $user->display_name,
            '{site_display_name}' => $member->display_name,
            '{general_availability_date}' => $general_availability_date ? date('F j, Y', strtotime($general_availability_date)) : 'TBD',
            '{reservation_url}' => $reservation_url ?: 'the reservation page'
        );

        $message = str_replace(array_keys($variables), array_values($variables), $template);
               
        if (wp_mail($user->user_email, $subject, $message)) {
            $count++;
        }
    }
    
    ccc_wor_log_transaction('reminder_emails_sent', 0, '', "Sent $count reminder emails");
    
    wp_send_json_success(array('message' => "Sent $count reminder emails"));
}

add_action('wp_ajax_ccc_wor_send_individual_reminder', 'ccc_wor_send_individual_reminder');
function ccc_wor_send_individual_reminder() {
    check_ajax_referer('ccc_wor_annual', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    
    $annual_id = intval($_POST['annual_id']);
    
    // Get annual member info
    $annual = $wpdb->get_row($wpdb->prepare("
        SELECT a.*, s.display_name as site_display_name, u.user_email, u.display_name as user_name
        FROM {$wpdb->prefix}ccc_wor_annual_status a
        JOIN {$wpdb->prefix}ccc_wor_sites s ON a.site_id = s.site_id  
        JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        WHERE a.annual_id = %d
    ", $annual_id));
    
    if (!$annual) {
        wp_send_json_error('Annual member not found');
    }
    
    // Check if user has ccc_member role
    $user = get_userdata($annual->user_id);
    if (!in_array('ccc_member', $user->roles)) {
        wp_send_json_error('User no longer has member role and should be reviewed for annual status removal instead');
    }
    
    // Check if they already have a reservation this year
    $current_year = ccc_wor_get_working_year();
    $existing_reservation = $wpdb->get_var($wpdb->prepare(
        "SELECT reservation_id FROM {$wpdb->prefix}ccc_wor_reservations 
         WHERE user_id = %d AND reservation_year = %d AND status IN ('confirmed', 'pending')",
        $annual->user_id, $current_year
    ));
    
    if ($existing_reservation) {
        wp_send_json_error('User already has a reservation for this year');
    }
    
    // Skip if they missed 2+ years (same logic as bulk reminder function)
    if ($annual->last_reserved_year < ($current_year - 2)) {
        wp_send_json_error('User has missed 2+ years and should be reviewed for annual status removal instead');
    }
    
    // Send the reminder email (reuse the same email logic)
    $reservation_url = get_option('ccc_wor_reservation_url', '');
    $general_availability_date = get_option('ccc_wor_general_availability_date', '');
    
    $subject = 'Week of Rivers - Reserve Your Annual Site';
    // Get the template
    $template = get_option('ccc_wor_email_template_reminder', '');
    if (empty($template)) {
        $template = "Dear {user_name},\n\nYou have annual status for {site_display_name} but have not yet made your reservation for this year.\n\nPlease log in to reserve your site before the general availability date on {general_availability_date}.\n\n{reservation_url}\n\nThank you!";
    }

    // Replace variables
    $variables = array(
        '{user_name}' => $annual->user_name,
        '{site_display_name}' => $annual->site_display_name, // Changed from display_name
        '{general_availability_date}' => $general_availability_date ? date('F j, Y', strtotime($general_availability_date)) : 'TBD',
        '{reservation_url}' => $reservation_url ? "{$reservation_url}" : ''
    );
    
    $message = str_replace(array_keys($variables), array_values($variables), $template);
    
    if (wp_mail($annual->user_email, $subject, $message)) {
        ccc_wor_log_transaction('individual_reminder_sent', $annual->user_id, $annual->site_id, 'Individual reminder email sent');
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to send email');
    }
}

add_action('wp_ajax_ccc_wor_export_sites', 'ccc_wor_export_sites');
function ccc_wor_export_sites() {
    check_ajax_referer('ccc_wor_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $sites = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ccc_wor_sites ORDER BY site_type, site_number");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ccc_wor_sites_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('site_type', 'site_number', 'display_name', 'is_active'));
    
    foreach ($sites as $site) {
        fputcsv($output, array(
            $site->site_type,
            $site->site_number,
            $site->display_name,
            $site->is_active
        ));
    }
    
    fclose($output);
    exit;
}

/**
 * Get the current working year for reservations
 * Returns current year before event ends, next year after event ends
 */
function ccc_wor_get_working_year() {
    $event_end = get_option('ccc_wor_event_end_date');
    $event_start = get_option('ccc_wor_event_start_date');
    
    // If no event dates configured, use current year
    if (!$event_end) {
        return date('Y');
    }
    
    // Extract the year from the event start date
    $event_year = date('Y', strtotime($event_start));
    $current_timestamp = current_time('timestamp');
    
    // If we have an end date and we're past it, work on next year
    if ($event_end) {
        $event_end_timestamp = strtotime($event_end);
        if ($current_timestamp > $event_end_timestamp) {
            return $event_year + 1;
        }
    }
    
    // Otherwise, work on the year the event is scheduled for
    return $event_year;
}

add_action('wp_ajax_ccc_wor_reset_email_template', 'ccc_wor_reset_email_template');
function ccc_wor_reset_email_template() {
    check_ajax_referer('ccc_wor_email_reset', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $template_type = sanitize_text_field($_POST['template_type']);
    $defaults = ccc_wor_get_default_email_templates(); // Use the central function
    
    if (!isset($defaults[$template_type])) {
        wp_send_json_error('Invalid template type');
    }
    
    update_option('ccc_wor_email_template_' . $template_type, $defaults[$template_type]);
    wp_send_json_success(array('template' => $defaults[$template_type]));
}

function ccc_wor_get_default_email_templates() {
    return array(
        'confirmation' => "Dear {user_name},\n\nYour reservation for {site_display_name} has been confirmed for Week of Rivers {year}.\n\nEvent Dates: {event_start_date} to {event_end_date}\nAdditional Guests ({guest_count}): {guest_list}\n\nIf you have any questions, please visit: https://smokymtnmeadows.com/\n\nSee you at Week of Rivers!",
        'incomplete' => "Dear {user_name},\n\nYour reservation for {site_display_name} was not completed within the time limit and has been cancelled.\n\nTo make a new reservation, please visit: {reservation_url}",
        'annual_removed' => "Dear {user_name},\n\nYour annual status for {site_display_name} has been removed due to {removal_reason}.\n\nMembers may still reserve available sites during the general reservation period starting {general_availability_date}.\n\nMembers can view available sites at: {reservation_url}",
        'reminder' => "Dear {user_name},\n\nYou have annual status for {site_display_name} but have not yet made your reservation for this year.\n\nPlease log in to reserve your site before the general availability date on {general_availability_date}.\n\nYou can make your reservation at: {reservation_url}\n\nThank you!"    );
}

add_action('wp_ajax_ccc_wor_update_annual_year', 'ccc_wor_update_annual_year');
function ccc_wor_update_annual_year() {
    check_ajax_referer('ccc_wor_annual', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $annual_id = intval($_POST['annual_id']);
    $last_reserved_year = intval($_POST['last_reserved_year']);
    
    global $wpdb;
    
    $result = $wpdb->update(
        $wpdb->prefix . 'ccc_wor_annual_status',
        array(
            'last_reserved_year' => $last_reserved_year,
            'modified_date' => current_time('mysql')
        ),
        array('annual_id' => $annual_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        ccc_wor_log_transaction('annual_year_updated', 0, '', "Updated annual member year to $last_reserved_year");
        wp_send_json_success();
    } else {
        wp_send_json_error('Database update failed');
    }
}