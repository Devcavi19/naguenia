/**
 * The admin-facing JavaScript file for the plugin.
 *
 * This file is used to handle the admin-facing JavaScript functionality.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 * @subpackage Wpragbot/admin/js
 */

(function( $ ) {
    'use strict';

    $(document).ready(function() {
        // Handle form submission
        $('#wpragbot-settings-form').on('submit', function(e) {
            // Form validation can be added here
            console.log('Settings form submitted');
        });

        // Handle document upload
        $('#wpragbot_upload_submit').on('click', function(e) {
            e.preventDefault();

            // Get form data from the upload form
            var formData = new FormData($('#wpragbot-upload-form')[0]);

            // Add action and nonce
            formData.append('action', 'wpragbot_upload_document');
            formData.append('nonce', wpragbot_admin.nonce);

            // Handle upload via AJAX
            $.ajax({
                url: wpragbot_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    // Show loading indicator
                    $('#wpragbot_upload_submit').prop('disabled', true).text('Uploading...');
                },
                success: function(response) {
                    // Handle success
                    if (response.success) {
                        alert('Document uploaded successfully! Processed ' + response.data.chunks + ' chunks.');
                        $('#wpragbot-upload-form')[0].reset();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                    $('#wpragbot_upload_submit').prop('disabled', false).text('Upload Document');
                },
                error: function(xhr, status, error) {
                    // Handle error
                    alert('Error uploading document: ' + error);
                    $('#wpragbot_upload_submit').prop('disabled', false).text('Upload Document');
                }
            });
        });

        // Initialize any admin-specific functionality here
        console.log('WPRAGBot admin JavaScript loaded');
    });

})( jQuery );