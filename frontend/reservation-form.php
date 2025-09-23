<!-- frontend/reservation-form.php -->
<div class="ccc-wor-reservation-container">
    <?php
    global $wpdb;
    
    $event_start = get_option('ccc_wor_event_start_date');
    $event_end = get_option('ccc_wor_event_end_date');
    $current_period = ccc_wor_get_current_period();
    $sites = ccc_wor_get_sites(array('is_active' => 1));
    $year = date('Y');
    $user_id = get_current_user_id();
    
    // Get user's annual sites
    $annual_sites = $wpdb->get_col($wpdb->prepare(
        "SELECT site_id FROM {$wpdb->prefix}ccc_wor_annual_status 
         WHERE user_id = %d AND status = 'active'",
        $user_id
    ));
    
    // Get reserved sites
    $reserved_sites = $wpdb->get_col($wpdb->prepare(
        "SELECT site_id FROM {$wpdb->prefix}ccc_wor_reservations 
         WHERE reservation_year = %d AND status IN ('confirmed', 'pending')",
        $year
    ));
    ?>
    
    <div class="reservation-header">
        <h2>Week of Rivers Reservations</h2>
        <?php if ($event_start && $event_end): ?>
        <p class="event-dates">Event Dates: <?php echo date('F j, Y', strtotime($event_start)); ?> to <?php echo date('F j, Y', strtotime($event_end)); ?></p>
        <?php endif; ?>
        
        <div class="status-message">
            <?php echo ccc_wor_get_status_message($current_period); ?>
        </div>
        
        <p>For questions, visit: <a href="https://smokymtnmeadows.com/" target="_blank">https://smokymtnmeadows.com/</a></p>
        
        <?php
        $campground_image = get_option('ccc_wor_campground_image_url');
        if ($campground_image):
        ?>
        <p><a href="<?php echo esc_url($campground_image); ?>" target="_blank">View Campground Map</a></p>
        <?php endif; ?>
    </div>
    
    <div class="site-legend">
        <h3>Legend</h3>
        <div class="legend-items">
            <span class="legend-item"><span class="color-box annual"></span> Annual</span>
            <span class="legend-item"><span class="color-box open-to-all"></span> Open to All</span>
            <span class="legend-item"><span class="color-box available"></span> Available</span>
            <span class="legend-item"><span class="color-box reserved"></span> Reserved</span>
        </div>
    </div>
    
    <div class="site-grid">
        <?php
        $sites_by_type = array();
        foreach ($sites as $site) {
            $sites_by_type[$site->site_type][] = $site;
        }
        
        // Sort campsites numerically
        if (isset($sites_by_type['Campsite'])) {
            usort($sites_by_type['Campsite'], function($a, $b) {
                return (int)$a->site_number - (int)$b->site_number;
            });
        }
        if (isset($sites_by_type['Electric Site'])) {
            usort($sites_by_type['Electric Site'], function($a, $b) {
                return (int)$a->site_number - (int)$b->site_number;
            });
        }
        
        $display_order = array('Campsite', 'Electric Site', 'RV Site', 'Cabin');
        
        foreach ($display_order as $type):
            if (!isset($sites_by_type[$type])) continue;
        ?>
        <div class="site-type-group">
            <h3><?php echo esc_html($type); ?>s</h3>
            <div class="sites">
                <?php foreach ($sites_by_type[$type] as $site):
                    $is_reserved = in_array($site->site_id, $reserved_sites);
                    $is_annual = in_array($site->site_id, $annual_sites);
                    
                    $class = 'site-box ';
                    if ($is_reserved) {
                        $class .= 'reserved';
                    } elseif ($current_period === 'annual' && !$is_annual) {
                        $class .= 'annual';
                    } elseif ($current_period === 'general') {
                        $class .= 'open-to-all';
                    } elseif ($is_annual) {
                        $class .= 'annual';
                    } else {
                        $class .= 'available';
                    }
                ?>
                <div class="<?php echo $class; ?>" data-site-id="<?php echo esc_attr($site->site_id); ?>">
                    <?php echo esc_html($site->site_number); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div id="reservation-form" style="display:none;">
        <h3>Reserve Site: <span id="selected-site-name"></span></h3>
        <form id="ccc-wor-reservation-form">
            <input type="hidden" name="site_id" id="site_id">
            
            <p>
                <label for="guest_count">Number of additional guests:</label>
                <input type="number" name="guest_count" id="guest_count" min="0" value="0">
            </p>
            
            <p>
                <label for="guest_names">Guest names (one per line):</label>
                <textarea name="guest_names" id="guest_names" rows="5"></textarea>
            </p>
            
            <p>
                <button type="submit" class="button button-primary">Reserve Site & Checkout</button>
                <button type="button" class="button" onclick="cancelSelection()">Cancel Selection</button>
            </p>
        </form>
    </div>
</div>

<?php
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