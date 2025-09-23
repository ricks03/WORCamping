<?php
/**
 * Database Class for CCC WOR Camping Plugin
 * Handles all database table creation and schema management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CCC_WOR_Database {
    
    /**
     * Create all required database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = "DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Sites table
        $sites_table = $wpdb->prefix . 'ccc_wor_sites';
        $sites_sql = "CREATE TABLE IF NOT EXISTS $sites_table (
            site_id VARCHAR(50) PRIMARY KEY,
            site_type VARCHAR(20) NOT NULL,
            site_number VARCHAR(10),
            display_name VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        dbDelta($sites_sql);
        
        // Reservations table
        $reservations_table = $wpdb->prefix . 'ccc_wor_reservations';
        $reservations_sql = "CREATE TABLE IF NOT EXISTS $reservations_table (
            reservation_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            site_id VARCHAR(50) NOT NULL,
            reservation_year YEAR NOT NULL,
            guest_count INT DEFAULT 0,
            order_id BIGINT NULL,
            status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_completed_date DATETIME NULL,
            INDEX idx_user_year (user_id, reservation_year),
            INDEX idx_site_year (site_id, reservation_year)
        ) $charset_collate;";
        
        dbDelta($reservations_sql);
        
        // Annual Status table
        $annual_table = $wpdb->prefix . 'ccc_wor_annual_status';
        $annual_sql = "CREATE TABLE IF NOT EXISTS $annual_table (
            annual_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            site_id VARCHAR(50) NOT NULL,
            last_reserved_year YEAR NOT NULL,
            status ENUM('active', 'under_review', 'removed') DEFAULT 'active',
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_user_site (user_id, site_id),
            INDEX idx_site (site_id)
        ) $charset_collate;";
        
        dbDelta($annual_sql);
        
        // Guest Names table
        $guests_table = $wpdb->prefix . 'ccc_wor_guest_names';
        $guests_sql = "CREATE TABLE IF NOT EXISTS $guests_table (
            guest_id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            guest_name VARCHAR(100) NOT NULL,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservation (reservation_id)
        ) $charset_collate;";
        
        dbDelta($guests_sql);
        
        // Transaction Log table
        $log_table = $wpdb->prefix . 'ccc_wor_transaction_log';
        $log_sql = "CREATE TABLE IF NOT EXISTS $log_table (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            action VARCHAR(100) NOT NULL,
            target_user_id BIGINT NULL,
            site_id VARCHAR(50) NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (created_date),
            INDEX idx_user (user_id),
            INDEX idx_action (action)
        ) $charset_collate;";
        
        dbDelta($log_sql);
        
        // Insert default sites if none exist
        $this->insert_default_sites();
    }
    
    /**
     * Insert default site data
     */
    private function insert_default_sites() {
        global $wpdb;
        
        $sites_table = $wpdb->prefix . 'ccc_wor_sites';
        
        // Check if sites already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $sites_table");
        if ($count > 0) {
            return;
        }
        
        // Cabins
        $cabins = array('Sycamore', 'Blackberry', 'Dogwood', 'Maple');
        foreach ($cabins as $cabin) {
            $wpdb->insert(
                $sites_table,
                array(
                    'site_id' => 'cabin_' . strtolower($cabin),
                    'site_type' => 'Cabin',
                    'site_number' => $cabin,
                    'display_name' => 'Cabin ' . $cabin,
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
        }
        
        // RV Sites
        for ($i = 1; $i <= 33; $i++) {
            if ($i == 9) {
                $wpdb->insert($sites_table, array(
                    'site_id' => 'rv_09a',
                    'site_type' => 'RV Site',
                    'site_number' => '9a',
                    'display_name' => 'RV Site 9a',
                    'is_active' => 1
                ), array('%s', '%s', '%s', '%s', '%d'));
                
                $wpdb->insert($sites_table, array(
                    'site_id' => 'rv_09b',
                    'site_type' => 'RV Site',
                    'site_number' => '9b',
                    'display_name' => 'RV Site 9b',
                    'is_active' => 1
                ), array('%s', '%s', '%s', '%s', '%d'));
            } elseif ($i == 14) {
                $wpdb->insert($sites_table, array(
                    'site_id' => 'rv_14a',
                    'site_type' => 'RV Site',
                    'site_number' => '14a',
                    'display_name' => 'RV Site 14a',
                    'is_active' => 1
                ), array('%s', '%s', '%s', '%s', '%d'));
                
                $wpdb->insert($sites_table, array(
                    'site_id' => 'rv_14b',
                    'site_type' => 'RV Site',
                    'site_number' => '14b',
                    'display_name' => 'RV Site 14b',
                    'is_active' => 1
                ), array('%s', '%s', '%s', '%s', '%d'));
            } else {
                $wpdb->insert($sites_table, array(
                    'site_id' => 'rv_' . sprintf('%02d', $i),
                    'site_type' => 'RV Site',
                    'site_number' => (string)$i,
                    'display_name' => 'RV Site ' . $i,
                    'is_active' => 1
                ), array('%s', '%s', '%s', '%s', '%d'));
            }
        }
        
        // Electric Sites
        $electric_sites = array(28,40,43,44,45,46,47,48,49,50,51,52,53,60,61);
        foreach ($electric_sites as $site_num) {
            $wpdb->insert($sites_table, array(
                'site_id' => 'electric_' . $site_num,
                'site_type' => 'Electric Site',
                'site_number' => (string)$site_num,
                'display_name' => 'Electric Site ' . $site_num,
                'is_active' => 1
            ), array('%s', '%s', '%s', '%s', '%d'));
        }
        
        // Campsites
        $campsites = array(25,26,27,29,30,34,35,36,37,38,39,41,42,54,55,56,57,58,59,62,63,64,65);
        foreach ($campsites as $site_num) {
            $wpdb->insert($sites_table, array(
                'site_id' => 'campsite_' . $site_num,
                'site_type' => 'Campsite',
                'site_number' => (string)$site_num,
                'display_name' => 'Campsite ' . $site_num,
                'is_active' => 1
            ), array('%s', '%s', '%s', '%s', '%d'));
        }
    }
    
    /**
     * Drop all plugin tables (used on uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ccc_wor_guest_names',
            $wpdb->prefix . 'ccc_wor_reservations',
            $wpdb->prefix . 'ccc_wor_annual_status',
            $wpdb->prefix . 'ccc_wor_sites',
            $wpdb->prefix . 'ccc_wor_transaction_log'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Get database version for migrations
     */
    public function get_db_version() {
        return get_option('ccc_wor_db_version', '1.0.0');
    }
    
    /**
     * Update database version
     */
    public function update_db_version($version) {
        update_option('ccc_wor_db_version', $version);
    }
}