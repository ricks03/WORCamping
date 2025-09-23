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
        )
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
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, s.display_name, s.site_type, u.display_name as user_name, u.user_email
         FROM {$wpdb->prefix}ccc_wor_reservations r
         JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
         JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
         WHERE r.reservation_year = %d
         ORDER BY r.created_date DESC",
        $year
    ));
}

/**
 * Check if all required WooCommerce products exist
 */
function ccc_wor_check_woocommerce_products() {
    $required_products = array('Campsite', 'Electric Site', 'RV Site', 'Cabin', 'Guest');
    $missing = array();
    
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
        }
    }
    
    return $missing;
}
/**
 * Get current availability period
 */
function ccc_wor_get_current_period() {
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
 * Get status message based on current period
 */
function ccc_wor_get_status_message($period) {
    $annual_start = get_option('ccc_wor_annual_start_date', 'May 1');
    $general_start = get_option('ccc_wor_general_availability_date', 'June 1');
    $event_start = get_option('ccc_wor_event_start_date');
    $event_end = get_option('ccc_wor_event_end_date');
    
    switch ($period) {
        case 'closed':
            if (current_time('timestamp') < strtotime($annual_start . ' ' . date('Y'))) {
                return "Reservations are not yet open. Annual member reservations begin on $annual_start.<br>Next: Annual member reservations open $annual_start";
            } else {
                return "Reservations are closed for this year.";
            }
        case 'annual':
            return "Annual member reservation period is active until $general_start. General reservations begin on $general_start.<br>Next: General reservations open $general_start";
        case 'general':
            return "General reservation period is active. All available sites can be reserved.<br>Event dates: " . date('F j, Y', strtotime($event_start)) . " to " . date('F j, Y', strtotime($event_end));
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
        $site_types = array('campsite', 'electric_site', 'rv_site', 'cabin');
        
        foreach ($site_types as $type) {
            if (isset($_POST['template_' . $type])) {
                update_option('ccc_wor_email_template_' . $type, wp_kses_post($_POST['template_' . $type]));
            }
        }
        
        update_option('ccc_wor_email_template_incomplete', wp_kses_post($_POST['template_incomplete']));
        update_option('ccc_wor_email_template_annual_removed', wp_kses_post($_POST['template_annual_removed']));
        
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
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ccc_wor_annual_status',
                array(
                    'user_id' => intval($_POST['user_id']),
                    'site_id' => sanitize_text_field($_POST['site_id']),
                    'last_reserved_year' => intval($_POST['last_reserved_year']),
                    'status' => 'active'
                )
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
            
            while (($data = fgetcsv($handle)) !== false) {
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
                if (!in_array('ccc_member', $user->roles)) {
                    $errors[] = "User $email does not have ccc_member role";
                    continue;
                }
                
                // Find site by number (try different prefixes)
                $site_id = null;
                $prefixes = array('campsite_', 'electric_', 'rv_', 'cabin_');
                foreach ($prefixes as $prefix) {
                    $test_id = $prefix . strtolower($site_number);
                    $site = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %s OR site_number = %s",
                        $test_id, $site_number
                    ));
                    if ($site) {
                        $site_id = $site->site_id;
                        break;
                    }
                }
                
                if (!$site_id) {
                    $errors[] = "Site not found: $site_number";
                    continue;
                }
                
                // Insert or update annual status
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ccc_wor_annual_status WHERE user_id = %d AND site_id = %s",
                    $user->ID, $site_id
                ));
                
                if ($existing) {
                    if ($year > $existing->last_reserved_year) {
                        $wpdb->update(
                            $wpdb->prefix . 'ccc_wor_annual_status',
                            array('last_reserved_year' => $year),
                            array('annual_id' => $existing->annual_id)
                        );
                    }
                } else {
                    $wpdb->insert(
                        $wpdb->prefix . 'ccc_wor_annual_status',
                        array(
                            'user_id' => $user->ID,
                            'site_id' => $site_id,
                            'last_reserved_year' => $year,
                            'status' => 'active'
                        )
                    );
                }
                
                $imported++;
            }
            
            fclose($handle);
            
            ccc_wor_log_transaction('csv_imported', 0, '', "Imported $imported records");
            
            if (!empty($errors)) {
                set_transient('ccc_wor_import_errors', $errors, 60);
            }
            
            wp_redirect(admin_url('admin.php?page=ccc-wor-settings&message=imported&count=' . $imported));
            exit;
        }
    }
}

/**
 * AJAX handler for removing annual status
 */
add_action('wp_ajax_ccc_wor_remove_annual_status', 'ccc_wor_ajax_remove_annual_status');
function ccc_wor_ajax_remove_annual_status() {
    check_ajax_referer('ccc_wor_annual', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    
    $annual_id = intval($_POST['annual_id']);
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] == 1;
    
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
        $email->send_annual_status_removed_email($annual->user_id, $annual->site_id);
    }
    
    // Remove annual status
    $wpdb->update(
        $wpdb->prefix . 'ccc_wor_annual_status',
        array('status' => 'removed'),
        array('annual_id' => $annual_id)
    );
    
    ccc_wor_log_transaction('annual_status_removed', $annual->user_id, $annual->site_id, 'Annual status removed');
    
    wp_send_json_success();
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
    
    $annual = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ccc_wor_annual_status WHERE annual_id = %d",
        $annual_id
    ));
    
    if (!$annual) {
        wp_send_json_error('Annual status not found');
    }
    
    $wpdb->delete(
        $wpdb->prefix . 'ccc_wor_annual_status',
        array('annual_id' => $annual_id)
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
    }
}

function ccc_wor_send_reminder_emails() {
    global $wpdb;
    
    $current_year = date('Y');
    
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
        
        $subject = 'Week of Rivers - Reserve Your Annual Site';
        $message = "Dear {$user->display_name},\n\n";
        $message .= "You have annual status for {$member->display_name} but have not yet made your reservation for this year.\n\n";
        $message .= "Please log in to reserve your site before the general availability date.\n\n";
        $message .= "Thank you!";
        
        if (wp_mail($user->user_email, $subject, $message)) {
            $count++;
        }
    }
    
    ccc_wor_log_transaction('reminder_emails_sent', 0, '', "Sent $count reminder emails");
    
    wp_send_json_success(array('message' => "Sent $count reminder emails"));
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
    fputcsv($output, array('site_id', 'site_type', 'site_number', 'display_name', 'is_active'));
    
    foreach ($sites as $site) {
        fputcsv($output, array(
            $site->site_id,
            $site->site_type,
            $site->site_number,
            $site->display_name,
            $site->is_active
        ));
    }
    
    fclose($output);
    exit;
}