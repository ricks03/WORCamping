<!-- admin/pages/reservations.php -->
<div class="wrap">


    <?php
    // Handle individual reservation view
    if (isset($_GET['view'])) {
        $reservation_id = intval($_GET['view']);
        global $wpdb;
        
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.display_name, s.site_type, s.site_number, u.display_name as user_name, u.user_email
             FROM {$wpdb->prefix}ccc_wor_reservations r
             JOIN {$wpdb->prefix}ccc_wor_sites s ON r.site_id = s.site_id
             JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
             WHERE r.reservation_id = %d",
            $reservation_id
        ));
        
        if (!$reservation) {
            echo '<div class="notice notice-error"><p>Reservation not found.</p></div>';
            echo '<a href="' . admin_url('admin.php?page=ccc-wor-reservations') . '" class="button">? Back to Reservations</a>';
            echo '</div>';
            return;
        }
        
        // Get guest names
        $guests = $wpdb->get_results($wpdb->prepare(
            "SELECT guest_name FROM {$wpdb->prefix}ccc_wor_guest_names WHERE reservation_id = %d ORDER BY guest_id",
            $reservation_id
        ));
        
        // Get payment info
        $order_total = null;
        $order_status = null;
        if ($reservation->order_id) {
            $order = wc_get_order($reservation->order_id);
            if ($order) {
                $order_total = $order->get_total();
                $order_status = $order->get_status();
            }
        }
    ?>

    <h1>Reservation Details - <?php echo esc_html($reservation->display_name); ?></h1>
    
    <a href="<?php echo admin_url('admin.php?page=ccc-wor-reservations&year=' . $reservation->reservation_year); ?>" class="button">Back to Reservations</a>
    <table style="margin-top: 20px; width: 100%; border-collapse: collapse;">
    <style>
    table th { 
        text-align: left; 
        padding: 6px 10px; 
        background: #f1f1f1; 
        border-bottom: 1px solid #ddd;
        width: 200px;
    }
    table td { 
        padding: 6px 10px; 
        border-bottom: 1px solid #ddd;
    }
    </style>
    <tr><th>Site:</th><td><?php echo esc_html($reservation->display_name); ?> (<?php echo esc_html($reservation->site_type); ?> #<?php echo esc_html($reservation->site_number); ?>)</td></tr>
    <tr><th>Year:</th><td><?php echo esc_html($reservation->reservation_year); ?></td></tr>
    <tr><th>Status:</th><td><strong><?php echo esc_html($reservation->status); ?></strong></td></tr>
    <tr><th>Reserved On:</th><td><?php echo esc_html($reservation->created_date); ?></td></tr>
    <?php if ($reservation->payment_completed_date): ?>
    <tr><th>Payment Completed:</th><td><?php echo esc_html($reservation->payment_completed_date); ?></td></tr>
    <?php endif; ?>
    
    <tr><th>User:</th><td><?php echo esc_html($reservation->user_name); ?> (<a href="mailto:<?php echo esc_attr($reservation->user_email); ?>"><?php echo esc_html($reservation->user_email); ?></a>)</td></tr>
    
    <tr><th>Additional Guests:</th><td><?php echo esc_html($reservation->guest_count); ?></td></tr>
    <?php if (!empty($guests)): ?>
    <tr><th>Guest Names:</th><td><?php 
        $guest_names = array_map(function($g) { return esc_html($g->guest_name); }, $guests);
        echo implode(', ', $guest_names);
    ?></td></tr>
    <?php endif; ?>
    
    <?php if ($reservation->order_id): ?>
    <tr><th>WooCommerce Order:</th><td><a href="<?php echo admin_url('post.php?post=' . $reservation->order_id . '&action=edit'); ?>" target="_blank">#<?php echo esc_html($reservation->order_id); ?></a> (<?php echo esc_html($order_status ?: 'Unknown'); ?>)</td></tr>
    <tr><th>Total Amount:</th><td><strong>$<?php echo esc_html(number_format($order_total ?: 0, 2)); ?></strong></td></tr>
    <?php else: ?>
    <tr><th>Payment:</th><td><em>No payment information</em></td></tr>
    <?php endif; ?>
</table>
    <?php
        echo '</div>'; // Close wrap div
        return; // Don't show the main reservations list
    }
    ?>

    <h1>Reservations</h1>
    
    <?php
    $year = isset($_GET['year']) ? intval($_GET['year']) : ccc_wor_get_working_year();
    $reservations = ccc_wor_get_reservations($year);
    ?>
    
    <div class="tablenav top">
    
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="ccc-wor-reservations">
            <label for="year">Year:</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <?php 
                $working_year = ccc_wor_get_working_year();
                // Show from working year - 2 to working year + 1 (more future-focused)
                for ($y = $working_year - 2; $y <= $working_year + 1; $y++): 
                ?>
                <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        
        <button type="button" class="button" onclick="exportTransactions(<?php echo $year; ?>)">Export Transactions</button>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Site Type</th>
                <th>User Name</th>
                <th>User Email</th>
                <th>Guests</th>
                <th>Total Amount</th>
                <th>Reservation Status</th>
                <th>Annual Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            global $wpdb;
            foreach ($reservations as $reservation): 
                $annual = $wpdb->get_row($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}ccc_wor_annual_status 
                     WHERE user_id = %d AND site_id = %d",
                    $reservation->user_id, $reservation->site_id
                ));
                $annual_status = $annual ? $annual->status : 'none';
            ?>
            <tr>
                <td><?php echo esc_html($reservation->site_type); ?></td>
                <td><?php echo esc_html($reservation->user_name); ?></td>
                <td><?php echo esc_html($reservation->user_email); ?></td>
                <td><?php echo esc_html($reservation->guest_count); ?></td>
                <td><?php echo $reservation->total_amount ? '$' . number_format($reservation->total_amount, 2) : 'N/A'; ?></td>
               <td><?php echo esc_html($reservation->status); ?></td>
                <td><?php echo esc_html($annual_status); ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=ccc-wor-reservations&view=' . $reservation->reservation_id); ?>">View Details</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
