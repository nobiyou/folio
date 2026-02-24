/**
 * Folio Theme Options - Admin Scripts
 */
(function($) {
    'use strict';

    function t(key, fallback) {
        if (window.folioAdmin && window.folioAdmin.i18n && window.folioAdmin.i18n[key]) {
            return window.folioAdmin.i18n[key];
        }
        return fallback;
    }

    $(document).ready(function() {
        $('form').on('submit', function(e) {
            var hasError = false;

            $(this).find('[required]').each(function() {
                if ($(this).val() === '') {
                    hasError = true;
                    $(this).css('border-color', '#dc3232');
                } else {
                    $(this).css('border-color', '');
                }
            });

            if (hasError) {
                e.preventDefault();
                alert(t('fill_required_fields', 'Please fill all required fields'));
                return false;
            }
        });

        $('input, textarea, select').on('focus', function() {
            $(this).parent().addClass('focused');
        }).on('blur', function() {
            $(this).parent().removeClass('focused');
        });

        if (window.location.search.indexOf('settings-updated=true') > -1) {
            var notice = $('<div class="notice notice-success is-dismissible"><p>' + t('settings_saved', 'Settings saved!') + '</p></div>');
            $('.folio-options-wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

        $('#folio-test-ai-connection').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $result = $('#folio-ai-test-result');

            $btn.prop('disabled', true).text(t('testing', 'Testing...'));
            $result.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + t('testing_all_api_connections', 'Testing all API connections...')).css('color', '#646970');

            $.ajax({
                url: folioAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'folio_test_ai_connection',
                    nonce: folioAdmin.nonce
                },
                success: function(response) {
                    var html = '';
                    var color = response.success ? '#28a745' : '#dc3545';
                    var icon = response.success ? '[OK]' : '[FAIL]';
                    var message = response.data && response.data.message ? response.data.message : t('unknown_error', 'Unknown error');

                    html += '<div style="color: ' + color + '; margin-bottom: 15px; font-size: 14px; padding: 10px; background: ' + (response.success ? '#d4edda' : '#f8d7da') + '; border-left: 4px solid ' + color + '; border-radius: 3px;">';
                    html += '<strong>' + icon + ' ' + message + '</strong>';
                    html += '</div>';

                    if (response.data && response.data.results && Object.keys(response.data.results).length > 0) {
                        html += '<div style="margin-top: 15px; border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: #f9f9f9;">';
                        html += '<strong style="display: block; margin-bottom: 12px; font-size: 13px; color: #333;">' + t('api_test_details', 'API test details:') + '</strong>';

                        $.each(response.data.results, function(index, result) {
                            var apiName = result.name || (t('api_config_prefix', 'API Config #') + (parseInt(index, 10) + 1));
                            var isSuccess = !!result.success;
                            var resultColor = isSuccess ? '#28a745' : '#dc3545';
                            var resultIcon = isSuccess ? '[OK]' : '[FAIL]';

                            html += '<div style="margin-bottom: 15px; padding: 12px; background: #fff; border-left: 4px solid ' + resultColor + '; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                            html += '<div style="font-weight: 600; margin-bottom: 8px;">';
                            html += '<span style="color: ' + resultColor + '; font-size: 14px; margin-right: 6px;">' + resultIcon + '</span>';
                            html += '<span style="color: #333; font-size: 14px;">' + apiName + '</span>';
                            html += '</div>';

                            html += '<div style="color: #666; font-size: 12px; margin-bottom: 8px;">';
                            html += '<div><strong>Endpoint:</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + (result.endpoint || t('not_set', 'Not set')) + '</code></div>';
                            html += '<div style="margin-top: 4px;"><strong>' + t('model', 'Model') + ':</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + (result.model || t('not_set', 'Not set')) + '</code></div>';
                            html += '</div>';

                            if (isSuccess) {
                                html += '<div style="margin-bottom: 6px;"><strong>' + t('status', 'Status') + ':</strong> <span style="color: #28a745; font-weight: 600;">' + t('connected', 'Connected') + '</span></div>';
                                if (result.response_preview) {
                                    html += '<div><strong>' + t('ai_response', 'AI Response') + ':</strong><div style="color: #333; font-family: monospace; background: #f0f0f0; padding: 8px; border-radius: 3px; margin-top: 4px; word-break: break-all; font-size: 11px; line-height: 1.5;">' + result.response_preview + '</div></div>';
                                } else {
                                    html += '<div><strong>' + t('ai_response', 'AI Response') + ':</strong> <span style="color: #999; font-style: italic;">' + t('no_response_content', 'No response content') + '</span></div>';
                                }
                            } else {
                                var errorMsg = result.error || result.message || t('unknown_error', 'Unknown error');
                                html += '<div style="margin-bottom: 6px;"><strong>' + t('status', 'Status') + ':</strong> <span style="color: #dc3545; font-weight: 600;">' + t('connection_failed', 'Connection failed') + '</span></div>';
                                html += '<div><strong>' + t('error_message', 'Error') + ':</strong><div style="color: #dc3545; background: #fff5f5; padding: 8px; border-radius: 3px; margin-top: 4px; font-size: 11px; line-height: 1.5; word-break: break-all;">' + errorMsg + '</div></div>';
                            }

                            html += '</div>';
                        });

                        html += '</div>';
                    }

                    $result.html(html);
                },
                error: function() {
                    $result.html(t('request_failed_retry', 'Request failed, please try again')).css('color', '#dc3545');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(t('test_connection', 'Test Connection'));
                }
            });
        });

        var mediaUploader;

        $(document).on('click', '.folio-upload-image-button', function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert(t('media_uploader_not_loaded', 'Media uploader is not loaded. Please refresh the page and try again.'));
                console.error('wp.media is undefined');
                return false;
            }

            var $button = $(this);
            var targetId = $button.data('target');

            if (!targetId) {
                console.error('Target ID not found');
                return false;
            }

            var $wrapper = $button.closest('.folio-image-upload-wrapper');
            var $preview = $wrapper.find('.folio-image-preview');

            if (mediaUploader) {
                mediaUploader.close();
            }

            try {
                mediaUploader = wp.media({
                    title: t('select_image', 'Select Image'),
                    button: {
                        text: t('use_image', 'Use this image')
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.url);

                    var maxWidth = targetId === 'site_favicon' ? '32px' : '200px';
                    var imgHtml = '<img src="' + attachment.url + '" style="max-width: ' + maxWidth + '; height: auto; display: block; margin-bottom: 10px;" alt="' + t('preview', 'Preview') + '">';
                    $preview.html(imgHtml);

                    $button.text(t('replace_prefix', 'Replace ') + (targetId === 'site_favicon' ? t('icon', 'Icon') : 'LOGO'));

                    var $removeBtn = $wrapper.find('.folio-remove-image-button');
                    if ($removeBtn.length === 0) {
                        var removeBtnHtml = '<button type="button" class="button folio-remove-image-button" data-target="' + targetId + '" style="margin-left: 10px;">' + t('remove_prefix', 'Remove ') + (targetId === 'site_favicon' ? t('icon', 'Icon') : 'LOGO') + '</button>';
                        $button.after(removeBtnHtml);
                    }
                });

                mediaUploader.open();
            } catch (error) {
                console.error('Error creating media uploader:', error);
                alert(t('cannot_open_media_uploader', 'Unable to open media uploader. Please refresh and try again.'));
            }
        });

        $(document).on('click', '.folio-remove-image-button', function(e) {
            e.preventDefault();

            var $button = $(this);
            var targetId = $button.data('target');
            var $wrapper = $button.closest('.folio-image-upload-wrapper');
            var $preview = $wrapper.find('.folio-image-preview');

            $('#' + targetId).val('');
            $preview.html('');
            $button.remove();

            var $uploadBtn = $wrapper.find('.folio-upload-image-button');
            $uploadBtn.text(t('select_prefix', 'Select ') + (targetId === 'site_favicon' ? t('icon', 'Icon') : 'LOGO'));
        });
    });
})(jQuery);
