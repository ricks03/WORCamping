<!-- admin/pages/reservations.php -->
<div class="wrap">
    <h1>Reservations</h1>
    
    <?php
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $reservations = ccc_wor_get_reservations($year);
    ?>
    
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="ccc-wor-reservations">
            <label for="year">Year:</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        
        <button type="button" class="button" onclick="exportTransactions(<?php echo $year; ?>)">Export Transactions</button>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Site ID</th>
                <th>Site Type</th>
                <th>User Name</th>
                <th>User Email</th>
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
                     WHERE user_id = %d AND site_id = %s",
                    $reservation->user_id, $reservation->site_id
                ));
                $annual_status = $annual ? $annual->status : 'none';
            ?>
            <tr>
                <td><?php echo esc_html($reservation->site_id); ?></td>
                <td><?php echo esc_html($reservation->site_type); ?></td>
                <td><?php echo esc_html($reservation->user_name); ?></td>
                <td><?php echo esc_html($reservation->user_email); ?></td>
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
