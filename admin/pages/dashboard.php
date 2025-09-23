<!-- admin/pages/dashboard.php -->
<div class="wrap">
    <h1>Campground Administration</h1>
    
    <?php
    $current_year = date('Y');
    $reservations = ccc_wor_get_reservations($current_year);
    $total_reservations = count($reservations);
    
    global $wpdb;
    $total_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_sites WHERE is_active = 1");
    $available_sites = $total_sites - $total_reservations;
    ?>
    
    <div class="ccc-wor-dashboard-stats">
        <div class="stat-box">
            <h3>Total Sites</h3>
            <p class="stat-number"><?php echo $total_sites; ?></p>
        </div>
        <div class="stat-box">
            <h3>Reservations (<?php echo $current_year; ?>)</h3>
            <p class="stat-number"><?php echo $total_reservations; ?></p>
        </div>
        <div class="stat-box">
            <h3>Available Sites</h3>
            <p class="stat-number"><?php echo $available_sites; ?></p>
        </div>
    </div>
    
    <h2>Recent Reservations</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Site</th>
                <th>User</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($reservations, 0, 10) as $reservation): ?>
            <tr>
                <td><?php echo esc_html($reservation->display_name); ?></td>
                <td><?php echo esc_html($reservation->user_name); ?></td>
                <td><?php echo esc_html($reservation->status); ?></td>
                <td><?php echo esc_html($reservation->created_date); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
