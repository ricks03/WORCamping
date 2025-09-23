/* assets/admin.js */
jQuery(document).ready(function($) {
    
    // Export transactions
    window.exportTransactions = function(year) {
        $.ajax({
            url: ccc_wor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ccc_wor_admin_action',
                ccc_action: 'export_transactions',
                nonce: ccc_wor_ajax.nonce,
                year: year
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function(blob) {
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'week_of_rivers_transactions_' + year + '.csv';
                link.click();
            }
        });
    };
    
    // Export sites
    window.exportSites = function() {
        window.location.href = ccc_wor_ajax.ajax_url + '?action=ccc_wor_export_sites&nonce=' + ccc_wor_ajax.nonce;
    };
    
    // Send reminder emails
    window.sendReminderEmails = function() {
        if (!confirm('Send reminder emails to all annual members who have not yet registered?')) {
            return;
        }
        
        $.ajax({
            url: ccc_wor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ccc_wor_admin_action',
                ccc_action: 'send_reminder_emails',
                nonce: ccc_wor_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Reminder emails sent successfully.');
                } else {
                    alert('Error sending reminder emails.');
                }
            }
        });
    };
    
    // Cleanup annual status
    window.cleanupAnnualStatus = function() {
        if (!confirm('Review and remove users who should lose annual status?')) {
            return;
        }
        
        $.ajax({
            url: ccc_wor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ccc_wor_admin_action',
                ccc_action: 'cleanup_annual_status',
                nonce: ccc_wor_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error during cleanup.');
                }
            }
        });
    };
});