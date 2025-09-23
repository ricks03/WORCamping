<!-- admin/pages/settings.php -->
<div class="wrap">
    <h1>Settings</h1>
    
    <?php
    if (isset($_GET['message']) && $_GET['message'] === 'saved') {
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_settings_nonce'); ?>
        
        <h2>General Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="event_start_date">Event Start Date</label></th>
                <td><input type="date" name="event_start_date" id="event_start_date" 
                    value="<?php echo esc_attr(get_option('ccc_wor_event_start_date')); ?>"></td>
            </tr>
            <tr>
                <th><label for="event_end_date">Event End Date</label></th>
                <td><input type="date" name="event_end_date" id="event_end_date" 
                    value="<?php echo esc_attr(get_option('ccc_wor_event_end_date')); ?>"></td>
            </tr>
            <tr>
                <th><label for="annual_start_date">Annual Member Start Date</label></th>
                <td><input type="text" name="annual_start_date" id="annual_start_date" 
                    value="<?php echo esc_attr(get_option('ccc_wor_annual_start_date', 'May 1')); ?>" 
                    placeholder="May 1"></td>
            </tr>
            <tr>
                <th><label for="general_availability_date">General Availability Date</label></th>
                <td><input type="text" name="general_availability_date" id="general_availability_date" 
                    value="<?php echo esc_attr(get_option('ccc_wor_general_availability_date', 'June 1')); ?>" 
                    placeholder="June 1"></td>
            </tr>
            <tr>
                <th><label for="transaction_timeout">Transaction Timeout (minutes)</label></th>
                <td><input type="number" name="transaction_timeout" id="transaction_timeout" 
                    value="<?php echo esc_attr(get_option('ccc_wor_transaction_timeout', 30)); ?>"></td>
            </tr>
            <tr>
                <th><label for="campground_image_url">Campground Map Image URL</label></th>
                <td><input type="url" name="campground_image_url" id="campground_image_url" 
                    value="<?php echo esc_attr(get_option('ccc_wor_campground_image_url')); ?>" 
                    class="regular-text"></td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Save Changes">
        </p>
    </form>

</div>
