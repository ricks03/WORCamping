<!-- admin/pages/sites.php -->
<div class="wrap">
    <h1>Site Management</h1>
    
    <?php
    $sites = ccc_wor_get_sites();
    $missing_products = ccc_wor_check_woocommerce_products();
    
    if (!empty($missing_products)):
    ?>
    <div class="notice notice-warning">
        <p><strong>Missing WooCommerce products:</strong> <?php echo implode(', ', $missing_products); ?>.</p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'site_exists'): ?>
    <div class="notice notice-error">
        <p>A site with that ID already exists. Please use a different site ID.</p>
    </div>
    <?php endif; ?> 
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'name_exists'): ?>
    <div class="notice notice-error">
        <p>A site with that display name already exists. Please use a different name.</p>
    </div>
    <?php endif; ?> 
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'number_exists'): ?>
    <div class="notice notice-error">
        <p>A site with that number already exists. Please use a different site number.</p>
    </div>
    <?php endif; ?>
     
    <?php if (isset($_GET['message']) && $_GET['message'] === 'sites_imported'): ?>
    <div class="notice notice-success">
        <p>Import completed: <?php echo intval($_GET['count']); ?> new sites imported
        <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
            , <?php echo intval($_GET['skipped']); ?> existing sites skipped
        <?php endif; ?>
        .</p>
        
        <?php 
        $skipped_sites = get_transient('ccc_wor_skipped_sites');
        if ($skipped_sites): 
        ?>
        <p><strong>Skipped sites (already exist):</strong> <?php echo implode(', ', $skipped_sites); ?></p>
        <?php 
            delete_transient('ccc_wor_skipped_sites');
        endif; 
        ?>
    </div>
    <?php endif; ?>  
    
    <?php
    // Show detailed import results
    $import_results = get_transient('ccc_wor_import_results');
    if ($import_results && isset($_GET['message']) && $_GET['message'] === 'sites_imported'): 
    ?>
    <div class="notice notice-info">
        <h3>Import Details:</h3>
        <div style="max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; margin: 10px 0;">
            <?php foreach ($import_results as $result): ?>
            <div style="margin-bottom: 5px; padding: 5px; 
                background: <?php echo $result['status'] === 'imported' ? '#d4edda' : ($result['status'] === 'error' ? '#f8d7da' : '#fff3cd'); ?>;">
                <strong>Line <?php echo $result['line']; ?>:</strong> 
                <?php echo esc_html($result['message']); ?>
                <br><small>Data: <?php echo esc_html($result['data']); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php 
        delete_transient('ccc_wor_import_results');
    endif; 
    ?>    
        
    <?php
    // Check if editing a site
    $editing_site = null;
    if (isset($_GET['edit'])) {
        $edit_site_id = sanitize_text_field($_GET['edit']);
        global $wpdb;
        $editing_site = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ccc_wor_sites WHERE site_id = %d",
            $edit_site_id
        ));
    }  
    
    if ($editing_site):
    ?>
    <h2>Edit Site: <?php echo esc_html($editing_site->display_name); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_sites_nonce'); ?>
        
        <input type="hidden" name="site_id" value="<?php echo esc_attr($editing_site->site_id); ?>">
        <input type="hidden" name="action" value="edit_site">
        
        <table class="form-table">
            <tr>
                <th><label for="edit_site_type">Site Type</label></th>
                <td>
                    <select name="site_type" id="edit_site_type" required>
                        <option value="Campsite" <?php selected($editing_site->site_type, 'Campsite'); ?>>Campsite</option>
                        <option value="Premium Campsite" <?php selected($editing_site->site_type, 'Premium Campsite'); ?>>Premium Campsite</option>
                        <option value="RV Site" <?php selected($editing_site->site_type, 'RV Site'); ?>>RV Site</option>
                        <option value="Cabin" <?php selected($editing_site->site_type, 'Cabin'); ?>>Cabin</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="edit_site_number">Site Number</label></th>
                <td><input type="text" name="site_number" id="edit_site_number" required value="<?php echo esc_attr($editing_site->site_number); ?>"></td>
            </tr>
            <tr>
                <th><label for="edit_display_name">Display Name</label></th>
                <td><input type="text" name="display_name" id="edit_display_name" required value="<?php echo esc_attr($editing_site->display_name); ?>"></td>
            </tr>
            <tr>
                <th><label for="edit_is_active">Active</label></th>
                <td><input type="checkbox" name="is_active" id="edit_is_active" <?php checked($editing_site->is_active, 1); ?>></td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Update Site">
            <a href="<?php echo admin_url('admin.php?page=ccc-wor-sites'); ?>" class="button">Cancel</a>
        </p>
    </form>
    <?php else: ?>      
    
    <h2>Add New Site</h2>
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_sites_nonce'); ?>
        <input type="hidden" name="action" value="add_site">
        
        <table class="form-table">
            <tr>
                <th><label for="site_type">Site Type</label></th>
                <td>
                    <select name="site_type" id="site_type" required>
                        <option value="">Select Type</option>
                        <option value="Campsite">Campsite</option>
                        <option value="Premium Campsite">Premium Campsite</option>
                        <option value="RV Site">RV Site</option>
                        <option value="Cabin">Cabin</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="site_number">Site Number</label></th>
                <td><input type="text" name="site_number" id="site_number" required></td>
            </tr>
            <tr>
                <th><label for="display_name">Display Name</label></th>
                <td><input type="text" name="display_name" id="display_name" required></td>
            </tr>
            <tr>
                <th><label for="is_active">Active</label></th>
                <td><input type="checkbox" name="is_active" id="is_active" checked></td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Add Site">
        </p>
    </form>
    
    <?php endif; ?>
    
    <h2>Import/Export Sites</h2>

    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <div style="flex: 1;">
            <h3>Export Sites</h3>
            <p>Download all site information as CSV</p>
            <button type="button" class="button" onclick="exportSites()">Export Sites to CSV</button>
        </div>
        
        <div style="flex: 1;">
            <h3>Import Sites</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ccc_wor_sites_nonce'); ?>
                <input type="hidden" name="action" value="import_sites">
                <p>Upload CSV with columns: site_type, site_number, display_name, is_active</p>
                <p class="description">
                    <strong>CSV Format:</strong> site_type, site_number, display_name, is_active<br>
                    <strong>Valid Site Types:</strong> Campsite, Premium Campsite, RV Site, Cabin, Field Camping<br>
                    <strong>Example:</strong> Campsite,25,Campsite 25,1
                </p>
                <input type="file" name="sites_csv" accept=".csv" required>
                <p class="submit">
                    <input type="submit" name="submit" class="button" value="Import Sites">
                </p>
            </form>
        </div>
    </div>    
    
    <h2>Existing Sites</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Type</th>
                <th>Site Number</th>
                <th>Display Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): ?>
            <tr>
                <td><?php echo esc_html($site->site_type); ?></td>
                <td><?php echo esc_html($site->site_number); ?></td>
                <td><?php echo esc_html($site->display_name); ?></td>
                <td><?php echo $site->is_active ? 'Active' : 'Inactive'; ?></td>
                <td>
                  <a href="<?php echo admin_url('admin.php?page=ccc-wor-sites&edit=' . $site->site_id); ?>">Edit</a> | 
                  <form method="post" style="display:inline;">
                      <?php wp_nonce_field('ccc_wor_sites_nonce'); ?>
                      <input type="hidden" name="action" value="delete_site">
                      <input type="hidden" name="site_id" value="<?php echo esc_attr($site->site_id); ?>">
                      <button type="submit" name="submit" class="button-link-delete" onclick="return confirm('Are you sure you want to delete this site?');" style="color:#b32d2e;text-decoration:none;background:none;border:none;padding:0;cursor:pointer;">Delete</button>
                  </form>
                </td>           
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-populate display name when adding a site
    function updateDisplayName() {
        var siteType = $('#site_type').val();
        var siteNumber = $('#site_number').val();
        
        if (siteType && siteNumber) {
            var displayName = siteType + ' ' + siteNumber;
            $('#display_name').val(displayName);
        }
    }
    
    // Update display name when either field changes
    $('#site_type, #site_number').on('change keyup', function() {
        // Only auto-populate if display name is empty or matches the pattern
        var currentDisplayName = $('#display_name').val();
        var siteType = $('#site_type').val();
        var siteNumber = $('#site_number').val();
        
        // Auto-populate if empty or if it looks like an auto-generated name
        if (!currentDisplayName || 
            (siteType && currentDisplayName.indexOf(siteType) === 0)) {
            updateDisplayName();
        }
    });
    
    // Clear display name when site type is cleared
    $('#site_type').on('change', function() {
        if (!$(this).val()) {
            $('#display_name').val('');
        }
    });
});
</script>
