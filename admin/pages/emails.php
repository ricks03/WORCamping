<!-- admin/pages/emails.php -->
<div class="wrap">
    <h1>Email Template Editor</h1>
    
    <?php
    if (isset($_GET['message']) && $_GET['message'] === 'saved') {
        echo '<div class="notice notice-success"><p>Email templates saved.</p></div>';
    }
    
    $site_types = array('Campsite', 'Premium Campsite', 'RV Site', 'Cabin');
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_email_nonce'); ?>
        
        
        <h2>Site Confirmation Email</h2>
        <?php
        $confirmation_template = get_option('ccc_wor_email_template_confirmation', '');
        if (empty($confirmation_template)) {
            $defaults = ccc_wor_get_default_email_templates();
            $confirmation_template = $defaults['confirmation'];
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="template_confirmation">Email Template</label></th>
                <td>
                    <textarea name="template_confirmation" id="template_confirmation" 
                              rows="10" class="large-text code"><?php echo esc_textarea($confirmation_template); ?></textarea>
                    <p class="description">
                        Available variables: {user_name}, {user_email}, {site_display_name}, {site_type}, 
                        {event_start_date}, {event_end_date}, {guest_count}, {guest_list}, {reservation_date}, {year}
                    </p>
                    <p><button type="button" class="button button-secondary" onclick="resetTemplate('confirmation')">Reset to Default</button></p>
                </td>
            </tr>
        </table>        
        
        
        <h2>Incomplete Transaction Email</h2>
        <?php
        $incomplete_template = get_option('ccc_wor_email_template_incomplete', '');
        if (empty($incomplete_template)) {
            $defaults = ccc_wor_get_default_email_templates();
            $incomplete_template = $defaults['incomplete'];
        }        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="template_incomplete">Email Template</label></th>
                <td>
                    <textarea name="template_incomplete" id="template_incomplete" 
                              rows="8" class="large-text code"><?php echo esc_textarea($incomplete_template); ?></textarea>
                    <p class="description">
                    Available variables: {user_name}, {site_display_name}, {general_availability_date}, {removal_reason}, {reservation_url}
                    </p>
                    <p><button type="button" class="button button-secondary" onclick="resetTemplate('incomplete')">Reset to Default</button></p>               
                </td>
            </tr>
        </table>
        
        <h2>Annual Status Removed Email</h2>
        <?php
        $removed_template = get_option('ccc_wor_email_template_annual_removed', '');
        if (empty($removed_template)) {
            $defaults = ccc_wor_get_default_email_templates();
            $removed_template = $defaults['annual_removed'];
        }        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="template_annual_removed">Email Template</label></th>
                <td>
                    <textarea name="template_annual_removed" id="template_annual_removed" 
                              rows="8" class="large-text code"><?php echo esc_textarea($removed_template); ?></textarea>
                    <p class="description">
                        Available variables: {user_name}, {site_display_name}, {general_availability_date}
                    </p>
                    <p><button type="button" class="button button-secondary" onclick="resetTemplate('annual_removed')">Reset to Default</button></p>
                </td>
            </tr>
        </table>
        
        <h2>Reminder Email</h2>
        <?php
        $reminder_template = get_option('ccc_wor_email_template_reminder', '');
        if (empty($reminder_template)) {
            $defaults = ccc_wor_get_default_email_templates();
            $reminder_template = $defaults['reminder'];
        }        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="template_reminder">Email Template</label></th>
                <td>
                    <textarea name="template_reminder" id="template_reminder" 
                              rows="8" class="large-text code"><?php echo esc_textarea($reminder_template); ?></textarea>
                    <p class="description">
                        Available variables: {user_name}, {site_display_name}, {general_availability_date}, {reservation_url}
                    </p>
                    <p><button type="button" class="button button-secondary" onclick="resetTemplate('reminder')">Reset to Default</button></p>               
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Save Changes">
        </p>
    </form>
    
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
        <h2>Available Email Variables</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
            <div>
                <h3>General Variables (All Templates)</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Variable</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>{user_name}</code></td><td>User's display name</td></tr>
                        <tr><td><code>{user_email}</code></td><td>User's email address</td></tr>
                        <tr><td><code>{site_display_name}</code></td><td>Friendly site name (e.g., "Cabin Sycamore")</td></tr>
                        <tr><td><code>{site_type}</code></td><td>Type of site (Campsite, RV Site, etc.)</td></tr>
                        <tr><td><code>{general_availability_date}</code></td><td>When general reservations open</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div>
                <h3>Confirmation Email Variables</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Variable</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>{event_start_date}</code></td><td>Event start date</td></tr>
                        <tr><td><code>{event_end_date}</code></td><td>Event end date</td></tr>
                        <tr><td><code>{guest_count}</code></td><td>Number of additional guests</td></tr>
                        <tr><td><code>{guest_list}</code></td><td>Comma-separated list of guest names</td></tr>
                        <tr><td><code>{reservation_date}</code></td><td>When reservation was made</td></tr>
                        <tr><td><code>{year}</code></td><td>Reservation year</td></tr>
                    </tbody>
                </table>
                
                <h3 style="margin-top: 20px;">Annual Status Removed Variables</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Variable</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>{removal_reason}</code></td><td>Context-specific reason for removal</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #cce7ff; border-radius: 3px;">
            <h4 style="margin-top: 0;">Usage Notes:</h4>
            <ul style="margin-bottom: 0;">
                <li>Variables are case-sensitive and must include the curly braces</li>
                <li>Unused variables will appear as blank text in emails</li>
                <li><code>{removal_reason}</code> automatically provides context: "missed reservation periods (2+ years)" or "membership status change"</li>
            </ul>
        </div>
    </div>
    
</div>

<script>
function resetTemplate(templateType) {
    if (!confirm('Reset this email template to default? This will overwrite your current template.')) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'ccc_wor_reset_email_template',
            template_type: templateType,
            nonce: '<?php echo wp_create_nonce('ccc_wor_email_reset'); ?>'
        },
        success: function(response) {
            if (response.success) {
                document.getElementById('template_' + templateType).value = response.data.template;
                alert('Template reset to default.');
            } else {
                alert('Error resetting template.');
            }
        }
    });
}
</script>
