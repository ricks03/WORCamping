<!-- frontend/reservation-form.php -->
<div class="ccc-wor-reservation-container">
    <?php
    global $wpdb;
    
    $event_start = get_option('ccc_wor_event_start_date');
    $event_end = get_option('ccc_wor_event_end_date');
    $current_period = ccc_wor_get_current_period();
    $sites = ccc_wor_get_sites(array('is_active' => 1));
    $year = ccc_wor_get_working_year(); // CHANGED
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
        <?php if ($event_start && $event_end): ?>
        <p class="event-dates">Week of Rivers Dates: <?php echo date('F j, Y', strtotime($event_start)); ?> to <?php echo date('F j, Y', strtotime($event_end)); ?></p>
        <?php endif; ?>
        
        <div class="status-message">
            <?php echo ccc_wor_get_status_message($current_period); ?>
        </div>
        
        <p>For questions about the campground, visit: <a href="https://smokymtnmeadows.com/" target="_blank">https://smokymtnmeadows.com/</a></p>
        <p>For questions about campground reservations for WOR, contact the <a href="mailto:cruise.chair@carolinacanoeclub.org?subject=Week of Rivers Question">CCC Cruise Chair</a>.
        
        <?php
        $campground_image = get_option('ccc_wor_campground_image_url');
        if ($campground_image):
        ?>
        <p><a href="<?php echo esc_url($campground_image); ?>" target="_blank">View Campground Map</a></p>
        <?php endif; ?>
    </div>

    <?php if ($current_period !== 'closed'): ?>
            
    <div class="site-legend">
        <h3>Legend</h3>
        <div class="legend-items">
            <span class="legend-item"><span class="color-box annual"></span> Annual</span>
            <span class="legend-item"><span class="color-box open-to-all"></span> Open to All</span>
            <span class="legend-item"><span class="color-box available"></span> Available</span>
            <span class="legend-item"><span class="color-box reserved"></span> Reserved</span>
        </div>
    </div>
    
    <div class="field-camping-section" style="background: #e8f5e9; padding: 20px; margin: 20px 0; border-radius: 5px; border: 2px solid #4caf50;">
        <h3 style="margin-top: 0; color: #2e7d32;">Field Camping Available</h3>
        <p><strong>Camp in the open field</strong> - No assigned site, bring your own tent or small RV.</p>
        <button type="button" id="field-camping-btn" class="button button-primary" style="background: #4caf50; border-color: #4caf50;">Reserve Field Camping</button>
    </div>

    <div id="field-camping-form" style="display: none; background: #f1f8e9; padding: 20px; margin: 20px 0; border-radius: 5px; border: 2px solid #4caf50;">
        <h3>Field Camping Reservation</h3>
        <form id="ccc-wor-field-camping-form">
            <input type="hidden" name="site_id" value="field_camping">
            
            <p>
                <label for="field_guest_count">Number of people (including yourself):</label>
                <input type="number" name="guest_count" id="field_guest_count" min="1" value="1" required>
            </p>
            
            <p>
                <label for="field_guest_names">Names of all campers (one per line):</label>
                <textarea name="guest_names" id="field_guest_names" rows="5" required></textarea>
            </p>
            
            <p>
                <button type="submit" class="button button-primary" style="background: #4caf50; border-color: #4caf50;">Reserve Field Camping & Checkout</button>
                <button type="button" class="button" onclick="cancelFieldSelection()">Cancel</button>
            </p>
        </form>
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
        if (isset($sites_by_type['Premium Campsite'])) {
            usort($sites_by_type['Premium Campsite'], function($a, $b) {
                return (int)$a->site_number - (int)$b->site_number;
            });
        }
        
        $display_order = array('Campsite', 'Premium Campsite', 'RV Site', 'Cabin');
        
        foreach ($display_order as $type):
            if (!isset($sites_by_type[$type])) continue;
            
            // Check if this section has any visible sites
            $has_visible_sites = false;
            foreach ($sites_by_type[$type] as $site) {
                $is_annual = in_array($site->site_id, $annual_sites);
                // During annual period, only show user's annual sites
                if ($current_period == 'annual' && !$is_annual) {
                    continue;
                }
                $has_visible_sites = true;
                break; // Found at least one visible site
            }
            
            // Skip this entire section if no visible sites
            if (!$has_visible_sites) continue;
        ?>
        <div class="site-type-group <?php echo $type === 'Cabin' ? 'cabin-sites' : ''; ?>">
            <h3><?php echo esc_html($type); ?>s</h3>
            <div class="sites">
                <?php foreach ($sites_by_type[$type] as $site):
                    $is_reserved = in_array($site->site_id, $reserved_sites);
                    $is_annual = in_array($site->site_id, $annual_sites);
                    
                    // During annual period, only show user's annual sites
                    if ($current_period == 'annual' && !$is_annual) {
                      continue; // Skip this site
                    }
                    
                    $class = 'site-box ';
                    if ($is_reserved) {
                        $class .= 'reserved';
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
    <?php endif; ?>        

</div>
