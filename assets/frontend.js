/* assets/frontend.js */
jQuery(document).ready(function($) {
    
    // Site selection
    $('.site-box').on('click', function() {
        if ($(this).hasClass('reserved')) {
            alert('This site is already reserved.');
            return;
        }
        
        if ($(this).hasClass('annual') && $('.site-grid').data('period') === 'annual') {
            alert('This site is only available to annual members during the annual period.');
            return;
        }
        
        var siteId = $(this).data('site-id');
        var siteName = $(this).text().trim();
        
        $('.site-box').removeClass('selected');
        $(this).addClass('selected');
        
        $('#site_id').val(siteId);
        $('#selected-site-name').text(siteName);
        $('#reservation-form').show();
        
        $('html, body').animate({
            scrollTop: $('#reservation-form').offset().top - 100
        }, 500);
    });
       
    // Field camping selection
    $('#field-camping-btn').on('click', function() {
        $('#field-camping-form').show();
        $('#site-legend').hide();
        $('#site-grid').hide();
        $('#reservation-form').hide();
        
        $('html, body').animate({
            scrollTop: $('#field-camping-form').offset().top - 100
        }, 500);
    });

    // Field camping form submission
    $('#ccc-wor-field-camping-form').on('submit', function(e) {
        e.preventDefault();
        
        var guestCount = parseInt($('#field_guest_count').val());
        var guestNames = $('#field_guest_names').val().trim().split('\n').filter(function(name) {
            return name.trim() !== '';
        });
        
        if (guestCount !== guestNames.length) {
            alert('Number of people must match the number of names provided.');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            url: ccc_wor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ccc_wor_frontend_action',
                ccc_action: 'make_reservation',
                nonce: ccc_wor_ajax.nonce,
                site_id: 'field_camping',
                guest_count: guestCount - 1, // Subtract 1 since guest_count is additional guests
                guest_names: $('#field_guest_names').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });
    
    // Form submission
    $('#ccc-wor-reservation-form').on('submit', function(e) {
        e.preventDefault();
        
        var siteId = $('#site_id').val();
        if (!siteId) {
            alert('Please select a site before proceeding.');
            return;
        }
        
        var guestCount = parseInt($('#guest_count').val());
        var guestNames = $('#guest_names').val().trim().split('\n').filter(function(name) {
            return name.trim() !== '';
        });
        
        if (guestCount > 0 && guestNames.length === 0) {
            alert('Please provide a name for each guest.');
            return;
        }
        
        if (guestCount > 0 && guestNames.length !== guestCount) {
            alert('Guest count must match the number of names provided.');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            url: ccc_wor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ccc_wor_frontend_action',
                ccc_action: 'make_reservation',
                nonce: ccc_wor_ajax.nonce,
                site_id: siteId,
                guest_count: guestCount,
                guest_names: $('#guest_names').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });
});

function cancelSelection() {
    jQuery('.site-box').removeClass('selected');
    jQuery('#reservation-form').hide();
    jQuery('#ccc-wor-reservation-form')[0].reset();
}

function cancelFieldSelection() {
    jQuery('#field-camping-form').hide();
    jQuery('#site-legend').show();
    jQuery('#site-grid').show();
    jQuery('#ccc-wor-field-camping-form')[0].reset();
}
