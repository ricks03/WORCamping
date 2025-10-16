<!-- admin/pages/annual.php -->
<div class="wrap">
    <h1>Annual Member Management</h1>
    
    <?php
    global $wpdb;
    
    // Show success/error messages
    if (isset($_GET['message']) && $_GET['message'] === 'annual_added') {
        echo '<div class="notice notice-success"><p>Annual member added successfully.</p></div>';
    }
    if (isset($_GET['error']) && $_GET['error'] === 'insert_failed') {
        echo '<div class="notice notice-error"><p>Failed to add annual member. Please try again.</p></div>';
    }
    
    if (isset($_GET['error']) && $_GET['error'] === 'insert_failed') {
        echo '<div class="notice notice-error"><p>Failed to add annual member. Please try again.</p></div>';
    }
    if (isset($_GET['error']) && $_GET['error'] === 'already_exists') {
        echo '<div class="notice notice-error"><p>This user already has annual status for this site.</p></div>';
    }
    
    $annual_members = $wpdb->get_results("
        SELECT 
            a.annual_id,
            a.user_id,
            a.site_id,
            a.last_reserved_year,
            a.status,
            a.created_date as annual_created,
            s.display_name as site_display_name,
            u.display_name as user_name,
            u.user_email
        FROM {$wpdb->prefix}ccc_wor_annual_status a
        JOIN {$wpdb->prefix}ccc_wor_sites s ON a.site_id = s.site_id
        JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        ORDER BY a.status, s.site_id
    ");   
     
    // Get members who should lose annual status
    // Simple logic: We're in late 2025, so 2025 event is complete
    // Anyone who last reserved before 2024 missed 2+ events (2024 and 2025)
    $current_calendar_year = date('Y'); // 2025
    $cutoff_year = $current_calendar_year - 1; // 2024

    $members_to_review = $wpdb->get_results($wpdb->prepare("
        SELECT a.*, s.display_name as site_display_name, u.display_name as user_name, u.user_email
        FROM {$wpdb->prefix}ccc_wor_annual_status a
        JOIN {$wpdb->prefix}ccc_wor_sites s ON a.site_id = s.site_id
        JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        WHERE a.last_reserved_year < %d AND a.status = 'active'
        ORDER BY s.site_id
    ", $cutoff_year));    
          
    // Check for members who lost ccc_member role
    $lost_role = array();
    foreach ($annual_members as $member) {
        $user = get_userdata($member->user_id);
        if ($user && !in_array('ccc_member', $user->roles)) {
            $lost_role[] = $member;
        }
    }
    ?>
    
    <?php if (!empty($members_to_review) || !empty($lost_role)): ?>
    <div class="notice notice-warning">
        <h3>Members Requiring Review</h3>
        
        <?php if (!empty($members_to_review)): ?>
        <h4>Members who missed 2+ years:</h4>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Site</th>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Last Reserved Year</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members_to_review as $member): ?>
                <tr>
                    <td><?php echo esc_html($member->site_display_name); ?></td>
                    <td><?php echo esc_html($member->user_name); ?></td>
                    <td><?php echo esc_html($member->user_email); ?></td>
                    <td><?php echo esc_html($member->last_reserved_year); ?></td>
                    <td>
                    <button type="button" class="button-small" onclick="deleteAnnualMember(<?php echo $member->annual_id; ?>, true, 'missed_years')">Delete & Email</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if (!empty($lost_role)): ?>
        <h4>Members with expired membership (missing ccc_member role):</h4>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Site</th>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Last Reserved Year</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lost_role as $member): ?>
                <tr>
                    <td><?php echo esc_html($member->site_display_name); ?></td>
                    <td><?php echo esc_html($member->user_name); ?></td>
                    <td><?php echo esc_html($member->user_email); ?></td>
                    <td><?php echo esc_html($member->last_reserved_year); ?></td>
                    <td>
                    <button type="button" class="button-small" onclick="deleteAnnualMember(<?php echo $member->annual_id; ?>, true, 'lost_membership')">Delete & Email</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <h2>All Annual Members</h2>
    <button type="button" class="button" onclick="sendReminderEmails()">Send Reminder Emails to ALL Annual Members</button>
    <br>Note: Send Reminder Emails should not send reminders to those registered or invalid (by year and membership) above. But you should still address those first. 
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Site</th>
                <th>User Name</th>
                <th>User Email</th>
                <th>Last Reserved Year</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($annual_members as $member): ?>
            <tr>
                <td><?php echo esc_html($member->site_display_name); ?></td>
                <td><?php echo esc_html($member->user_name); ?></td>
                <td><?php echo esc_html($member->user_email); ?></td>
                <td><?php echo esc_html($member->last_reserved_year); ?></td>
                <td><?php echo esc_html($member->status); ?></td>
                <td>
                    <button type="button" class="button-small" onclick="sendIndividualReminder(<?php echo $member->annual_id; ?>)">Send Email</button> | 
<button type="button" class="button-small" onclick="editYear(<?php echo $member->annual_id; ?>, <?php echo $member->last_reserved_year; ?>, '<?php echo esc_js($member->user_name); ?>')">Edit Year</button>                    <button type="button" class="button-small" onclick="deleteAnnualMember(<?php echo $member->annual_id; ?>, false)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>Add Annual Member</h2>
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_annual_nonce'); ?>
        <input type="hidden" name="action" value="add_annual">
        
        <table class="form-table">
            <tr>
                <th><label for="user_id">User</label></th>
                <td>
                    <select name="user_id" id="user_id" required>
                        <option value="">Select User</option>
                        <?php
                        $members = get_users(array('role' => 'ccc_member'));
                        foreach ($members as $member) {
                            echo '<option value="' . $member->ID . '">' . esc_html($member->display_name) . ' (' . esc_html($member->user_email) . ')</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="site_id_annual">Site</label></th>
                <td>
                    <select name="site_id" id="site_id_annual" required>
                        <option value="">Select Site</option>
                        <?php
                        $sites = ccc_wor_get_sites(array('is_active' => 1));
                        foreach ($sites as $site) {
                            echo '<option value="' . esc_attr($site->site_id) . '">' . esc_html($site->display_name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="last_reserved_year">Last Reserved Year</label></th>
                <td><input type="number" name="last_reserved_year" id="last_reserved_year" required value="<?php echo date('Y'); ?>"></td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Add Annual Member">
        </p>
    </form>
    
    <h2>Import Annual Data</h2>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ccc_wor_csv_import_nonce'); ?>
        <input type="hidden" name="action" value="csv_import">
        <p>
            <label for="csv_file">CSV format: member_email, site_number, year</label><br>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        </p>
        <p class="description">Only tracks back 2 years. Example: john@example.com,25,2023</p>
        <p class="submit">
            <input type="submit" name="submit" class="button" value="Import CSV">
        </p>
    </form>
    
    <h2>Export Annual Data</h2>
    <p>Download current annual member data in CSV format (member_email, site_number, year)</p>
    <button type="button" class="button" onclick="exportAnnualData()">Export Annual Data</button>
    
    <?php
    // Show import results
    if (isset($_GET['message']) && $_GET['message'] === 'imported') {
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        echo '<div class="notice notice-success"><p>Successfully imported ' . $count . ' annual member records.</p></div>';
    }
    
    $errors = get_transient('ccc_wor_import_errors');
    if ($errors) {
        echo '<div class="notice notice-error"><p><strong>Import Errors:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
        delete_transient('ccc_wor_import_errors');
    }
    ?>
</div>

<script>
function deleteAnnualMember(annualId, sendEmail = false, reason = 'general') {
    if (!confirm('Are you sure you want to delete this annual member?')) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ccc_wor_delete_annual_member',
            annual_id: annualId,
            send_email: sendEmail ? 1 : 0,
            reason: reason,
            nonce: '<?php echo wp_create_nonce('ccc_wor_annual'); ?>'
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error deleting annual member');
            }
        }
    });
}
function exportAnnualData() {
    window.location.href = ajaxurl + '?action=ccc_wor_export_annual&nonce=<?php echo wp_create_nonce('ccc_wor_annual'); ?>';
}

function sendIndividualReminder(annualId) {
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ccc_wor_send_individual_reminder',
            annual_id: annualId,
            nonce: '<?php echo wp_create_nonce('ccc_wor_annual'); ?>'
        },
        success: function(response) {
            if (response.success) {
                alert('Reminder email sent successfully.');
            } else {
                alert('Error sending reminder email: ' + response.data);
            }
        }
    });
}

function editYear(annualId, currentYear, userName) {
    var newYear = prompt('Edit Last Reserved Year for ' + userName + ':', currentYear);
    if (newYear === null || newYear === currentYear.toString()) {
        return; // User cancelled or no change
    }
    
    if (!/^\d{4}$/.test(newYear) || newYear < 2020 || newYear > 2030) {
        alert('Please enter a valid year between 2020 and 2030');
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ccc_wor_update_annual_year',
            annual_id: annualId,
            last_reserved_year: newYear,
            nonce: '<?php echo wp_create_nonce('ccc_wor_annual'); ?>'
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error updating year: ' + response.data);
            }
        }
    });
}
</script>