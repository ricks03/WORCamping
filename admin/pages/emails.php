<!-- admin/pages/emails.php -->
<div class="wrap">
    <h1>Email Template Editor</h1>
    
    <?php
    if (isset($_GET['message']) && $_GET['message'] === 'saved') {
        echo '<div class="notice notice-success"><p>Email templates saved.</p></div>';
    }
    
    $site_types = array('Campsite', 'Electric Site', 'RV Site', 'Cabin');
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_email_nonce'); ?>
        
        <?php foreach ($site_types as $type): 
            $key = strtolower(str_replace(' ', '_', $type));
            $template = get_option('ccc_wor_email_template_' . $key, '');
            if (empty($template)) {
                $template = "Dear {user_name},\n\nYour reservation for {site_display_name} has been confirmed for Week of Rivers {year}.\n\nEvent Dates: {event_start_date} to {event_end_date}\nAdditional Guests ({guest_count}): {guest_list}\n\nIf you have any questions, please visit: https://smokymtnmeadows.com/\n\nSee you at Week of Rivers!";
            }
        ?>
        <h2><?php echo esc_html($type); ?> Confirmation Email</h2>
        <table class="form-table">
            <tr>
                <th><label for="template_<?php echo $key; ?>">Email Template</label></th>
                <td>
                    <textarea name="template_<?php echo $key; ?>" id="template_<?php echo $key; ?>" 
                              rows="10" class="large-text code"><?php echo esc_textarea($template); ?></textarea>
                    <p class="description">
                        Available variables: {user_name}, {user_email}, {site_id}, {site_display_name}, {site_type}, 
                        {event_start_date}, {event_end_date}, {guest_count}, {guest_list}, {reservation_date}, {year}
                    </p>
                </td>
            </tr>
        </table>
        <?php endforeach; ?>
        
        <h2>Incomplete Transaction Email</h2>
        <?php
        $incomplete_template = get_option('ccc_wor_email_template_incomplete', '');
        if (empty($incomplete_template)) {
            $incomplete_template = "Dear {user_name},\n\nYour reservation for {site_display_name} was not completed within the time limit and has been cancelled.\n\nTo make a new reservation, please visit the reservation page.";
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="template_incomplete">Email Template</label></th>
                <td>
                    <textarea name="template_incomplete" id="template_incomplete" 
                              rows="8" class="large-text code"><?php echo esc_textarea($incomplete_template); ?></textarea>
                    <p class="description">
                        Available variables: {user_name}, {site_display_name}
                    </p>
                </td>
            </tr>
        </table>
        
        <h2>Annual Status Removed Email</h2>
        <?php
        $removed_template = get_option('ccc_wor_email_template_annual_removed', '');
        if (empty($removed_template)) {
            $removed_template = "Dear {user_name},\n\nYour annual status for {site_display_name} has been removed due to [membership change/missed reservation periods].\n\nYou may still reserve available sites during the general reservation period starting {general_availability_date}.";
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
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Save Changes">
        </p>
    </form>
</div>
