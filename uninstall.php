<?php
/**
 * Uninstall CCC WOR Camping Plugin
 * This file is called when the plugin is deleted
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load database class
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Drop all tables
CCC_WOR_Database::drop_tables();

// Remove all options
$options = array(
    'ccc_wor_annual_start_date',
    'ccc_wor_general_availability_date', 
    'ccc_wor_event_start_date',
    'ccc_wor_event_end_date',
    'ccc_wor_transaction_timeout',
    'ccc_wor_campground_image_url',
    'ccc_wor_db_version'
);

foreach ($options as $option) {
    delete_option($option);
}

// Remove email templates
$site_types = array('campsite', 'premium_site', 'rv_site', 'cabin');
foreach ($site_types as $type) {
    delete_option('ccc_wor_email_template_' . $type);
}
delete_option('ccc_wor_email_template_incomplete');
delete_option('ccc_wor_email_template_annual_removed');