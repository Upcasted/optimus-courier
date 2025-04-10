(function( $ ) {
	'use strict';

    const BUTTON_TEXT_GENERATING = 'Se generează...';
    const BUTTON_TEXT_DEFAULT = 'Generează AWB';
    const NOTICE_SUCCESS = 'notice-success';
    const NOTICE_WARNING = 'notice-warning';
    const DEFAULT_TRACKING_URL = 'https://optimuscourier.ro/search/';

    // Get tracking URL from settings or use default
    const trackingPageUrl = optimus_courier.tracking_page_url || DEFAULT_TRACKING_URL;

    $(document).ready(function() {
        handleAWBButtonClick();
        handleBulkActionFeedback();
        handleMetaBoxFormSubmission(); // New function for meta box form
    });

    // Make copyShortcode available globally
    window.copyShortcode = function(element) {
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(() => {
            const feedback = element.parentNode.querySelector('.copy-feedback');
            feedback.style.display = 'inline';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        });
    };

    function handleAWBButtonClick() {
        $('.generate-awb, .regenerate-awb').on('click', function(e) {
            e.preventDefault();
            const button = $(this);
            const orderId = button.data('order-id');

            button.prop('disabled', true).text(BUTTON_TEXT_GENERATING);

            $.ajax({
                url: optimus_courier.ajaxurl,
                type: 'POST',
                data: {
                    action: 'optimus_generate_awb',
                    order_id: orderId,
                    nonce: optimus_courier.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const awbNumbers = response.data.awb_number.split(',');
                        let awbContainer = button.closest('td').find('.awb-numbers');
                        if (!awbContainer.length) {
                            awbContainer = $('.inside .awb-numbers');
                        }
                        awbContainer.empty();

                        // First add all AWB numbers with tracking links
                        awbNumbers.forEach(awbNumber => {
                            const trackingLink = `<a href="${trackingPageUrl}?awb=${encodeURIComponent(awbNumber.trim())}" target="_blank">${awbNumber.trim()}</a><br>`;
                            awbContainer.append(trackingLink);
                        });

                        // Then add the action buttons container
                        const actionsHtml = `
                            <div style="display: flex; align-items: center; gap: 5px; margin-top: 5px;">
                                <a href="${optimus_courier.ajaxurl}?action=optimus_download_awb&awb=${encodeURIComponent(awbNumbers[0].trim())}&_wpnonce=${encodeURIComponent(optimus_courier.nonce)}" 
                                   class="button button-small" 
                                   target="_blank">Descarcă AWB</a>
                                <button class="regenerate-awb" 
                                        data-order-id="${orderId}" 
                                        title="Regenerează AWB">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                            </div>`;
                        awbContainer.append(actionsHtml);

                        // Remove the original generate button
                        button.remove();
                    } else {
                        alert('Error: ' + response.data.message);
                        button.prop('disabled', false).text(BUTTON_TEXT_DEFAULT);
                    }
                },
                error: function() {
                    alert('Network error occurred');
                    button.prop('disabled', false).text(BUTTON_TEXT_DEFAULT);
                }
            });
        });
    }

    function handleBulkActionFeedback() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('bulk_action_done') === 'generate_awb') {
            const processedCount = parseInt(urlParams.get('processed_count')) || 0;
            const failedCount = parseInt(urlParams.get('failed_count')) || 0;
            const skippedCount = parseInt(urlParams.get('skipped_count')) || 0;
            const failedOrders = urlParams.get('failed_orders');
            let errorMessages = {};

            try {
                const encodedErrors = urlParams.get('error_messages');
                if (encodedErrors) {
                    errorMessages = JSON.parse(atob(encodedErrors));
                }
            } catch (e) {
                console.error('Error parsing error messages:', e);
            }

            let message = '';

            if (processedCount > 0) {
                message += `${processedCount} AWB-uri generate cu succes. `;
            }

            if (skippedCount > 0) {
                message += `${skippedCount} comenzi sărite (AWB deja existent). `;
            }

            if (failedCount > 0) {
                message += `${failedCount} comenzi eșuate. `;
                if (failedOrders) {
                    message += '<br>Comenzi eșuate:<br>';
                    failedOrders.split(',').forEach(orderId => {
                        message += `#${orderId}: ${errorMessages[orderId] || 'Unknown error'}<br>`;
                    });
                }
            }

            const noticeClass = failedCount > 0 ? NOTICE_WARNING : NOTICE_SUCCESS;
            const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
            $('.wrap h1').after(notice);

            // Clean up URL after displaying message
            var newURL = new URL(window.location.href);
            const newURLParams = new URLSearchParams(newURL.search);

            newURLParams.delete("processed_count");
            newURLParams.delete("failed_count");
            newURLParams.delete("skipped_count");
            newURLParams.delete("failed_orders");
            newURLParams.delete("error_messages");
            newURLParams.delete("bulk_action_done");
    
            window.history.replaceState({}, document.title, newURL);
        }
    }

    function handleMetaBoxFormSubmission() {
        $('#generate-awb-button').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const button = $(this);
            const container = $('#optimus-courier-awb-container');
            const orderId = container.data('order-id');

            // Collect input values from the container
            const data = {
                action: 'generate_awb_from_meta_box',
                order_id: orderId,
                nonce: optimus_courier.nonce, // Changed from 'security' to 'nonce' to match PHP
                destinatar_nume: container.find('input[name="destinatar_nume"]').val(),
                destinatar_adresa: container.find('input[name="destinatar_adresa"]').val(),
                destinatar_localitate: container.find('input[name="destinatar_localitate"]').val(),
                destinatar_judet: container.find('input[name="destinatar_judet"]').val(),
                destinatar_cod_postal: container.find('input[name="destinatar_cod_postal"]').val(),
                destinatar_telefon: container.find('input[name="destinatar_telefon"]').val(),
                destinatar_email: container.find('input[name="destinatar_email"]').val(),
                colet_greutate: container.find('input[name="colet_greutate"]').val(),
                colet_buc: container.find('input[name="colet_buc"]').val(),
            };

            console.log('Nonce value:', optimus_courier.nonce); // Debug log
            console.log('Full data being sent:', data); // Debug log

            button.prop('disabled', true).text(BUTTON_TEXT_GENERATING);

            $.ajax({
                url: optimus_courier.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        alert('AWB generat cu succes: ' + response.data.awb_number);
                        location.reload();
                    } else {
                        console.log(response);
                        alert('Eroare: ' + response.data);
                        button.prop('disabled', false).text(BUTTON_TEXT_DEFAULT);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    alert('A apărut o eroare de rețea');
                    button.prop('disabled', false).text(BUTTON_TEXT_DEFAULT);
                }
            });
        });
    }

    // Add delete AWB functionality
    $(document).on('click', '.delete-awb', function(e) {
        e.preventDefault();
        
        if (!confirm('Ești sigur că vrei să ștergi acest AWB?')) {
            return;
        }

        const button = $(this);
        const orderId = button.data('order-id');
        const awb = button.data('awb');
        const container = button.closest('.awb-numbers');

        $.ajax({
            url: optimus_courier.ajaxurl,
            type: 'POST',
            data: {
                action: 'optimus_delete_awb',
                nonce: optimus_courier.nonce,
                order_id: orderId,
                awb: awb
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the page to show updated meta box
                    location.reload();
                } else {
                    alert(response.data || 'A apărut o eroare la ștergerea AWB-ului.');
                }
            },
            error: function() {
                alert('A apărut o eroare la ștergerea AWB-ului.');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
})( jQuery );
