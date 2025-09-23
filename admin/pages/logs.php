<!-- admin/pages/logs.php -->
<div class="wrap">
    <h1>Transaction Log</h1>
    
    <?php
    global $wpdb;
    
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, u.display_name as user_name 
         FROM {$wpdb->prefix}ccc_wor_transaction_log l
         LEFT JOIN {$wpdb->prefix}users u ON l.user_id = u.ID
         ORDER BY l.created_date DESC
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ccc_wor_transaction_log");
    $total_pages = ceil($total / $per_page);
    ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Action</th>
                <th>User</th>
                <th>Site ID</th>
                <th>Details</th>
                <th>Admin User</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo esc_html($log->created_date); ?></td>
                <td><?php echo esc_html($log->action); ?></td>
                <td><?php echo esc_html($log->target_user_id ?: 'N/A'); ?></td>
                <td><?php echo esc_html($log->site_id ?: 'N/A'); ?></td>
                <td><?php echo esc_html($log->details); ?></td>
                <td><?php echo esc_html($log->user_name ?: 'System'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>
