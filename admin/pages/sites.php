<!-- admin/pages/sites.php -->
<div class="wrap">
    <h1>Site Management</h1>
    
    <?php
    $sites = ccc_wor_get_sites();
    $missing_products = ccc_wor_check_woocommerce_products();
    
    if (!empty($missing_products)):
    ?>
    <div class="notice notice-warning">
        <p><strong>Missing WooCommerce products:</strong> <?php echo implode(', ', $missing_products); ?>. Please create these products.</p>
    </div>
    <?php endif; ?>
    
    <h2>Add New Site</h2>
    <form method="post" action="">
        <?php wp_nonce_field('ccc_wor_sites_nonce'); ?>
        <input type="hidden" name="action" value="add_site">
        
        <table class="form-table">
            <tr>
                <th><label for="site_id">Site ID</label></th>
                <td><input type="text" name="site_id" id="site_id" required placeholder="campsite_25"></td>
            </tr>
            <tr>
                <th><label for="site_type">Site Type</label></th>
                <td>
                    <select name="site_type" id="site_type" required>
                        <option value="">Select Type</option>
                        <option value="Campsite">Campsite</option>
                        <option value="Electric Site">Electric Site</option>
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
                <?php wp_nonce_field('ccc_wor_sites_import_nonce'); ?>
                <input type="hidden" name="action" value="import_sites">
                <p>Upload CSV with columns: site_id, site_type, site_number, display_name, is_active</p>
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
                <th>Site ID</th>
                <th>Type</th>
                <th>Number</th>
                <th>Display Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): ?>
            <tr>
                <td><?php echo esc_html($site->site_id); ?></td>
                <td><?php echo esc_html($site->site_type); ?></td>
                <td><?php echo esc_html($site->site_number); ?></td>
                <td><?php echo esc_html($site->display_name); ?></td>
                <td><?php echo $site->is_active ? 'Active' : 'Inactive'; ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=ccc-wor-sites&edit=' . $site->site_id); ?>">Edit</a> | 
                    <a href="<?php echo admin_url('admin.php?page=ccc-wor-sites&delete=' . $site->site_id); ?>" 
                       onclick="return confirm('Are you sure you want to delete this site?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
