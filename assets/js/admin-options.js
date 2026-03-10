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

    function getConfig() {
        if (window.folioAdmin && window.folioAdmin.config) {
            return window.folioAdmin.config;
        }

        return {};
    }

    function getNonce(key) {
        var config = getConfig();

        if (config.nonces && config.nonces[key]) {
            return config.nonces[key];
        }

        return '';
    }

    function getAjaxUrl() {
        if (window.folioAdmin && window.folioAdmin.ajax_url) {
            return window.folioAdmin.ajax_url;
        }

        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }

        return '';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatBytes(bytes) {
        var size = Number(bytes || 0);

        if (size <= 0) {
            return '0 B';
        }

        var base = 1024;
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var index = Math.min(Math.floor(Math.log(size) / Math.log(base)), units.length - 1);

        return parseFloat((size / Math.pow(base, index)).toFixed(2)) + ' ' + units[index];
    }

    function buildDashiconLabel(iconClass, label, extraClass) {
        var classes = ['dashicons', iconClass, 'folio-button-icon'];

        if (extraClass) {
            classes.push(extraClass);
        }

        return '<span class="' + classes.join(' ') + '"></span> ' + escapeHtml(label);
    }

    function renderInlineNotice(type, message) {
        return '<div class="notice notice-' + type + ' inline"><p>' + message + '</p></div>';
    }

    function getAiTestState(isSuccess) {
        return {
            color: isSuccess ? '#28a745' : '#dc3545',
            icon: isSuccess ? '[OK]' : '[FAIL]',
            background: isSuccess ? '#d4edda' : '#f8d7da'
        };
    }

    function renderAiTestSummary(response) {
        var isSuccess = !!response.success;
        var message = response.data && response.data.message ? response.data.message : t('unknown_error', 'Unknown error');
        var state = getAiTestState(isSuccess);

        return '<div style="color: ' + state.color + '; margin-bottom: 15px; font-size: 14px; padding: 10px; background: ' + state.background + '; border-left: 4px solid ' + state.color + '; border-radius: 3px;">' +
            '<strong>' + state.icon + ' ' + escapeHtml(message) + '</strong>' +
        '</div>';
    }

    function renderAiTestMeta(result) {
        return '<div style="color: #666; font-size: 12px; margin-bottom: 8px;">' +
            '<div><strong>Endpoint:</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + escapeHtml(result.endpoint || t('not_set', 'Not set')) + '</code></div>' +
            '<div style="margin-top: 4px;"><strong>' + escapeHtml(t('model', 'Model')) + ':</strong> <code style="background: #e9ecef; padding: 2px 4px; border-radius: 2px; font-size: 11px;">' + escapeHtml(result.model || t('not_set', 'Not set')) + '</code></div>' +
        '</div>';
    }

    function renderAiTestContent(result, isSuccess) {
        if (isSuccess) {
            if (result.response_preview) {
                return '<div><strong>' + escapeHtml(t('ai_response', 'AI Response')) + ':</strong><div style="color: #333; font-family: monospace; background: #f0f0f0; padding: 8px; border-radius: 3px; margin-top: 4px; word-break: break-all; font-size: 11px; line-height: 1.5;">' + escapeHtml(result.response_preview) + '</div></div>';
            }

            return '<div><strong>' + escapeHtml(t('ai_response', 'AI Response')) + ':</strong> <span style="color: #999; font-style: italic;">' + escapeHtml(t('no_response_content', 'No response content')) + '</span></div>';
        }

        var errorMsg = result.error || result.message || t('unknown_error', 'Unknown error');

        return '<div><strong>' + escapeHtml(t('error_message', 'Error')) + ':</strong><div style="color: #dc3545; background: #fff5f5; padding: 8px; border-radius: 3px; margin-top: 4px; font-size: 11px; line-height: 1.5; word-break: break-all;">' + escapeHtml(errorMsg) + '</div></div>';
    }

    function renderAiTestResultCard(index, result) {
        var isSuccess = !!result.success;
        var state = getAiTestState(isSuccess);
        var apiName = result.name || (t('api_config_prefix', 'API Config #') + (parseInt(index, 10) + 1));

        return '<div style="margin-bottom: 15px; padding: 12px; background: #fff; border-left: 4px solid ' + state.color + '; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">' +
            '<div style="font-weight: 600; margin-bottom: 8px;">' +
                '<span style="color: ' + state.color + '; font-size: 14px; margin-right: 6px;">' + state.icon + '</span>' +
                '<span style="color: #333; font-size: 14px;">' + escapeHtml(apiName) + '</span>' +
            '</div>' +
            renderAiTestMeta(result) +
            '<div style="margin-bottom: 6px;"><strong>' + escapeHtml(t('status', 'Status')) + ':</strong> <span style="color: ' + state.color + '; font-weight: 600;">' + escapeHtml(isSuccess ? t('connected', 'Connected') : t('connection_failed', 'Connection failed')) + '</span></div>' +
            renderAiTestContent(result, isSuccess) +
        '</div>';
    }

    function renderAiTestResults(results) {
        var resultKeys = Object.keys(results || {});

        if (!resultKeys.length) {
            return '';
        }

        return '<div style="margin-top: 15px; border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: #f9f9f9;">' +
            '<strong style="display: block; margin-bottom: 12px; font-size: 13px; color: #333;">' + escapeHtml(t('api_test_details', 'API test details:')) + '</strong>' +
            resultKeys.map(function(index) {
                return renderAiTestResultCard(index, results[index]);
            }).join('') +
        '</div>';
    }

    function renderAiTestResponse(response) {
        var html = renderAiTestSummary(response);

        if (response.data && response.data.results) {
            html += renderAiTestResults(response.data.results);
        }

        return html;
    }

    function buildSpinnerMarkup(message) {
        return '<span class="spinner is-active" style="float: none; margin: 0;"></span>' +
            (message ? ' ' + escapeHtml(message) : '');
    }

    function setButtonState($button, state) {
        if (!$button || !$button.length || !state) {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(state, 'disabled')) {
            $button.prop('disabled', !!state.disabled);
        }

        if (Object.prototype.hasOwnProperty.call(state, 'visible')) {
            if (state.visible) {
                $button.show();
            } else {
                $button.hide();
            }
        }

        if (!state.type) {
            return;
        }

        if (state.type === 'dashicon') {
            $button.html(buildDashiconLabel(state.iconClass, state.label, state.extraClass));
            return;
        }

        if (state.type === 'text') {
            $button.text(state.label);
            return;
        }

        if (state.type === 'html') {
            $button.html(state.html);
            return;
        }

        if (state.type === 'restore') {
            restoreButtonHtml($button, state.fallbackHtml);
        }
    }

    function runPostAction(config) {
        var $button = config.button;
        var $result = config.result;
        var fallbackErrorButton = config.errorButton || config.completedButton;

        if (config.confirmMessage && !window.confirm(config.confirmMessage)) {
            return Promise.resolve(null);
        }

        if (config.rememberButton) {
            rememberButtonHtml($button);
        }

        setButtonState($button, config.loadingButton);

        if ($result && $result.length && Object.prototype.hasOwnProperty.call(config, 'loadingMessage')) {
            $result.html(buildSpinnerMarkup(config.loadingMessage));
        }

        return postAction(config.action, config.nonceKey, config.nonceField, config.extraData)
            .then(function(data) {
                setButtonState($button, config.completedButton);

                if (config.onSuccess) {
                    config.onSuccess(data, $result, $button);
                }

                return data;
            })
            .catch(function(error) {
                setButtonState($button, fallbackErrorButton);

                if (config.onError) {
                    config.onError(error, $result, $button);
                }

                return null;
            });
    }

    function renderAiApiConfig(optionName, index) {
        var inputPrefix = optionName + '[ai_apis][' + index + ']';

        return '' +
            '<div class="folio-ai-api-item" data-index="' + index + '">' +
                '<div class="folio-ai-api-header">' +
                    '<h3>' + escapeHtml(t('api_config_prefix', 'API Config #') + (index + 1)) + '</h3>' +
                    '<button type="button" class="button button-small folio-remove-api folio-button-danger">' + escapeHtml(t('delete_label', 'Delete')) + '</button>' +
                '</div>' +
                '<table class="form-table">' +
                    '<tr>' +
                        '<th scope="row">' + escapeHtml(t('api_config_name', 'Config Name')) + '</th>' +
                        '<td>' +
                            '<input type="text" name="' + escapeHtml(inputPrefix + '[name]') + '" value="" class="regular-text" placeholder="' + escapeHtml(t('example_openai_primary', 'Example: OpenAI Primary')) + '">' +
                            '<p class="description">' + escapeHtml(t('identify_api_config', 'Used to identify this API config.')) + '</p>' +
                        '</td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row">' + escapeHtml(t('enabled_label', 'Enabled')) + '</th>' +
                        '<td>' +
                            '<label>' +
                                '<input type="checkbox" name="' + escapeHtml(inputPrefix + '[enabled]') + '" value="1" checked>' +
                                escapeHtml(t('enable_this_api_config', 'Enable this API config')) +
                            '</label>' +
                        '</td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row">' + escapeHtml(t('api_endpoint', 'API Endpoint')) + '</th>' +
                        '<td>' +
                            '<input type="url" name="' + escapeHtml(inputPrefix + '[endpoint]') + '" value="" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">' +
                            '<p class="description">' + escapeHtml(t('api_endpoint_url', 'API endpoint URL')) + '</p>' +
                        '</td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row">' + escapeHtml(t('api_key', 'API Key')) + '</th>' +
                        '<td>' +
                            '<input type="password" name="' + escapeHtml(inputPrefix + '[key]') + '" value="" class="large-text" placeholder="sk-...">' +
                            '<p class="description">' + escapeHtml(t('your_api_key', 'Your API key')) + '</p>' +
                        '</td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row">' + escapeHtml(t('model_name', 'Model Name')) + '</th>' +
                        '<td>' +
                            '<input type="text" name="' + escapeHtml(inputPrefix + '[model]') + '" value="gpt-3.5-turbo" class="regular-text" placeholder="gpt-3.5-turbo">' +
                            '<p class="description">' + escapeHtml(t('ai_model_to_use', 'AI model to use')) + '</p>' +
                        '</td>' +
                    '</tr>' +
                '</table>' +
            '</div>';
    }

    function postAction(action, nonceKey, nonceField, extraData) {
        var data = new URLSearchParams();
        var payload = extraData || {};
        var nonce = getNonce(nonceKey);

        data.set('action', action);

        if (nonce) {
            data.set(nonceField || 'nonce', nonce);
        }

        Object.keys(payload).forEach(function(key) {
            data.set(key, payload[key]);
        });

        return fetch(getAjaxUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: data.toString()
        }).then(function(response) {
            return response.json();
        });
    }

    function rememberButtonHtml($button) {
        if (!$button.data('original-html')) {
            $button.data('original-html', $button.html());
        }
    }

    function restoreButtonHtml($button, fallbackHtml) {
        var originalHtml = $button.data('original-html');

        if (originalHtml) {
            $button.html(originalHtml);
            return;
        }

        if (fallbackHtml) {
            $button.html(fallbackHtml);
        }
    }

    function toggleAutoThemeSettings() {
        var isAuto = $('#theme-mode-select').val() === 'auto';
        $('.auto-sunset-settings').toggle(isAuto);
    }

    function updateRangeOutput(input) {
        var outputSelector = input.getAttribute('data-output-selector');
        var output = outputSelector ? document.querySelector(outputSelector) : input.nextElementSibling;

        if (!output) {
            return;
        }

        output.textContent = input.value + '%';
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
            var notice = $('<div class="notice notice-success is-dismissible"><p>' + escapeHtml(t('settings_saved', 'Settings saved!')) + '</p></div>');
            $('.folio-options-wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

        $(document)
            .off('change.folioThemeMode', '#theme-mode-select')
            .on('change.folioThemeMode', '#theme-mode-select', toggleAutoThemeSettings);

        toggleAutoThemeSettings();

        $(document)
            .off('input.folioRangeOutput', '.folio-range-input')
            .on('input.folioRangeOutput', '.folio-range-input', function() {
                updateRangeOutput(this);
            });

        $('.folio-range-input').each(function() {
            updateRangeOutput(this);
        });

        var $aiApiContainer = $('#folio-ai-apis-container');
        if ($aiApiContainer.length) {
            $(document)
                .off('click.folioAiAdd', '#folio-add-api')
                .on('click.folioAiAdd', '#folio-add-api', function(e) {
                    e.preventDefault();

                    var optionName = $aiApiContainer.data('option-name') || 'folio_theme_options';
                    var apiIndex = parseInt($aiApiContainer.data('next-index'), 10);

                    if (isNaN(apiIndex)) {
                        apiIndex = $aiApiContainer.find('.folio-ai-api-item').length;
                    }

                    $aiApiContainer.append(renderAiApiConfig(optionName, apiIndex));
                    $aiApiContainer.data('next-index', apiIndex + 1);
                });

            $(document)
                .off('click.folioAiRemove', '.folio-remove-api')
                .on('click.folioAiRemove', '.folio-remove-api', function(e) {
                    e.preventDefault();

                    if (!window.confirm(t('api_delete_confirm', 'Are you sure you want to delete this API config?'))) {
                        return;
                    }

                    $(this).closest('.folio-ai-api-item').remove();
                });
        }

        $(document)
            .off('click.folioAiTest', '#folio-test-ai-connection')
            .on('click.folioAiTest', '#folio-test-ai-connection', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#folio-ai-test-result');

                $result.css('color', '#646970');

                runPostAction({
                    action: 'folio_test_ai_connection',
                    button: $btn,
                    result: $result,
                    extraData: {
                        nonce: window.folioAdmin ? window.folioAdmin.nonce : ''
                    },
                    loadingButton: {
                        disabled: true,
                        type: 'text',
                        label: t('testing', 'Testing...')
                    },
                    loadingMessage: t('testing_all_api_connections', 'Testing all API connections...'),
                    completedButton: {
                        disabled: false,
                        type: 'text',
                        label: t('test_connection', 'Test Connection')
                    },
                    onSuccess: function(response, $resultEl) {
                        $resultEl.css('color', '').html(renderAiTestResponse(response));
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html(escapeHtml(t('request_failed_retry', 'Request failed, please try again'))).css('color', '#dc3545');
                    }
                });
            });

        var mediaUploader;

        $(document)
            .off('click.folioUploadImage', '.folio-upload-image-button')
            .on('click.folioUploadImage', '.folio-upload-image-button', function(e) {
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

                        var imageClass = targetId === 'site_favicon'
                            ? 'folio-image-preview-image folio-image-preview-image--icon'
                            : 'folio-image-preview-image folio-image-preview-image--logo';
                        var imgHtml = '<img src="' + attachment.url + '" class="' + imageClass + '" alt="' + escapeHtml(t('preview', 'Preview')) + '">';
                        $preview.html(imgHtml);

                        $button.text(t('replace_prefix', 'Replace ') + (targetId === 'site_favicon' ? t('icon', 'Icon') : 'LOGO'));

                        var $removeBtn = $wrapper.find('.folio-remove-image-button');
                        if ($removeBtn.length === 0) {
                            var removeBtnHtml = '<button type="button" class="button folio-remove-image-button folio-button-spacing" data-target="' + targetId + '">' + escapeHtml(t('remove_prefix', 'Remove ')) + (targetId === 'site_favicon' ? escapeHtml(t('icon', 'Icon')) : 'LOGO') + '</button>';
                            $button.after(removeBtnHtml);
                        }
                    });

                    mediaUploader.open();
                } catch (error) {
                    console.error('Error creating media uploader:', error);
                    alert(t('cannot_open_media_uploader', 'Unable to open media uploader. Please refresh and try again.'));
                }
            });

        $(document)
            .off('click.folioRemoveImage', '.folio-remove-image-button')
            .on('click.folioRemoveImage', '.folio-remove-image-button', function(e) {
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

        $(document)
            .off('click.folioClearMaintenanceLogs', '#folio-clear-maintenance-logs')
            .on('click.folioClearMaintenanceLogs', '#folio-clear-maintenance-logs', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var clearUrl = $btn.data('clear-url');
                var confirmMessage = $btn.data('confirm') || '';

                if (!clearUrl) {
                    return;
                }

                if (confirmMessage && !window.confirm(confirmMessage)) {
                    return;
                }

                window.location.href = clearUrl;
            });

        $(document)
            .off('click.folioOptimizeAssets', '#folio-optimize-assets')
            .on('click.folioOptimizeAssets', '#folio-optimize-assets', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#folio-optimization-result');

                runPostAction({
                    action: 'folio_optimize_assets',
                    nonceKey: 'optimize_assets',
                    nonceField: 'nonce',
                    button: $btn,
                    result: $result,
                    rememberButton: true,
                    loadingButton: {
                        disabled: true,
                        type: 'dashicon',
                        iconClass: 'dashicons-update',
                        label: t('optimizing', 'Optimizing...'),
                        extraClass: 'folio-icon-spin'
                    },
                    loadingMessage: t('optimizing_assets', 'Optimizing assets...'),
                    completedButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-performance',
                        label: t('re_optimize_assets', 'Re-optimize Assets')
                    },
                    onSuccess: function(data, $resultEl) {
                        if (data.success) {
                            $resultEl.html(renderInlineNotice('success', '✓ ' + escapeHtml(t('asset_optimization_completed', 'Asset optimization completed!'))));
                            window.setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                            return;
                        }

                        var message = data && data.data ? data.data : t('unknown_error', 'Unknown error');
                        $resultEl.html(renderInlineNotice('error', '✗ ' + escapeHtml(t('optimization_failed_prefix', 'Optimization failed:')) + ' ' + escapeHtml(message)));
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html(renderInlineNotice('error', '✗ ' + escapeHtml(t('request_failed', 'Request failed'))));
                    }
                });
            });

        $(document)
            .off('click.folioClearOptimizedCache', '#folio-clear-optimized-cache')
            .on('click.folioClearOptimizedCache', '#folio-clear-optimized-cache', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#folio-optimization-result');


                runPostAction({
                    action: 'folio_clear_optimized_cache',
                    nonceKey: 'clear_optimized_cache',
                    nonceField: 'nonce',
                    button: $btn,
                    result: $result,
                    rememberButton: true,
                    confirmMessage: t('clear_cache_confirm', 'Are you sure you want to clear optimized cache?'),
                    loadingButton: {
                        disabled: true,
                        type: 'dashicon',
                        iconClass: 'dashicons-update',
                        label: t('clearing', 'Clearing...'),
                        extraClass: 'folio-icon-spin'
                    },
                    loadingMessage: t('clearing_cache', 'Clearing cache...'),
                    completedButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-trash',
                        label: t('clear_optimized_cache', 'Clear Optimized Cache')
                    },
                    onSuccess: function(data, $resultEl) {
                        if (data.success) {
                            $resultEl.html(renderInlineNotice('success', '✓ ' + escapeHtml(t('cache_cleared', 'Cache cleared!'))));
                            window.setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                            return;
                        }

                        var message = data && data.data ? data.data : t('unknown_error', 'Unknown error');
                        $resultEl.html(renderInlineNotice('error', '✗ ' + escapeHtml(t('clear_failed_prefix', 'Clear failed:')) + ' ' + escapeHtml(message)));
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html(renderInlineNotice('error', '✗ ' + escapeHtml(t('request_failed', 'Request failed'))));
                    }
                });
            });

        $(document)
            .off('click.folioFlushRewrite', '#flush-rewrite-rules')
            .on('click.folioFlushRewrite', '#flush-rewrite-rules', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#flush-result');

                runPostAction({
                    action: 'folio_flush_rewrite_rules',
                    nonceKey: 'flush_rewrite',
                    nonceField: 'nonce',
                    button: $btn,
                    result: $result,
                    loadingButton: {
                        disabled: true
                    },
                    loadingMessage: '',
                    completedButton: {
                        disabled: false
                    },
                    onSuccess: function(data, $resultEl) {
                        if (data.success) {
                            $resultEl.html('<span style="color: #28a745;">✓ ' + escapeHtml(data.data.message) + '</span>');
                        } else {
                            $resultEl.html('<span style="color: #dc3545;">✗ ' + escapeHtml(data.data.message) + '</span>');
                        }

                        window.setTimeout(function() {
                            $resultEl.html('');
                        }, 3000);
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html('<span style="color: #dc3545;">✗ ' + escapeHtml(t('request_failed', 'Request failed')) + '</span>');
                    }
                });
            });

        $(document)
            .off('click.folioBatchAlt', '#folio-batch-update-alt')
            .on('click.folioBatchAlt', '#folio-batch-update-alt', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#folio-alt-update-result');

                runPostAction({
                    action: 'folio_batch_update_alt',
                    nonceKey: 'batch_update_alt',
                    nonceField: '_ajax_nonce',
                    button: $btn,
                    result: $result,
                    loadingButton: {
                        disabled: true
                    },
                    loadingMessage: t('updating', 'Updating...'),
                    completedButton: {
                        disabled: false
                    },
                    onSuccess: function(data, $resultEl) {
                        if (data.success) {
                            $resultEl.html('<span style="color: #28a745;">✓ ' + escapeHtml(data.data.message) + '</span>');
                        } else {
                            $resultEl.html('<span style="color: #dc3545;">✗ ' + escapeHtml(t('update_failed', 'Update failed')) + '</span>');
                        }

                        window.setTimeout(function() {
                            $resultEl.html('');
                        }, 5000);
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html('<span style="color: #dc3545;">✗ ' + escapeHtml(t('request_failed', 'Request failed')) + '</span>');
                    }
                });
            });

        $(document)
            .off('click.folioBatchOptimizeImages', '#folio-batch-optimize-images')
            .on('click.folioBatchOptimizeImages', '#folio-batch-optimize-images', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $result = $('#folio-image-optimization-result');

                runPostAction({
                    action: 'folio_batch_optimize_images',
                    nonceKey: 'batch_optimize_images',
                    nonceField: '_ajax_nonce',
                    button: $btn,
                    result: $result,
                    rememberButton: true,
                    loadingButton: {
                        disabled: true,
                        type: 'text',
                        label: t('optimizing', 'Optimizing...')
                    },
                    loadingMessage: t('batch_optimizing_images', 'Batch optimizing images...'),
                    completedButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-format-image',
                        label: t('continue_optimization', 'Continue Optimization'),
                        visible: true
                    },
                    errorButton: {
                        disabled: false,
                        type: 'restore',
                        fallbackHtml: buildDashiconLabel('dashicons-format-image', t('batch_optimize_images', 'Batch Optimize Images')),
                        visible: true
                    },
                    onSuccess: function(data, $resultEl, $buttonEl) {
                        if (data.success) {
                            $resultEl.html('<div style="color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px;">✓ ' + escapeHtml(data.data.message) + '</div>');

                            if (data.data.remaining > 0) {
                                $resultEl.append('<p style="margin-top: 10px;">' + escapeHtml(t('there_are_still', 'There are still')) + ' ' + escapeHtml(data.data.remaining) + ' ' + escapeHtml(t('images_left_to_optimize', 'images left to optimize. You can continue by clicking the optimize button.')) + '</p>');
                            } else {
                                $resultEl.append('<p style="margin-top: 10px; color: #28a745;">' + escapeHtml(t('all_images_optimized', 'All images have been optimized!')) + '</p>');
                                $buttonEl.hide();
                            }
                            return;
                        }

                        $resultEl.html('<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ ' + escapeHtml(t('optimization_failed_prefix', 'Optimization failed:')) + '</div>');
                    },
                    onError: function(_error, $resultEl) {
                        $resultEl.html('<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ ' + escapeHtml(t('request_failed', 'Request failed')) + '</div>');
                    }
                });
            });

        $(document)
            .off('click.folioImageStats', '#folio-get-image-stats')
            .on('click.folioImageStats', '#folio-get-image-stats', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $dashboard = $('#folio-image-stats-dashboard');
                var $summary = $('#folio-image-stats-summary');
                var $recent = $('#folio-recent-optimizations');

                runPostAction({
                    action: 'folio_get_optimization_stats',
                    nonceKey: 'get_optimization_stats',
                    nonceField: '_ajax_nonce',
                    button: $btn,
                    rememberButton: true,
                    loadingButton: {
                        disabled: true,
                        type: 'text',
                        label: t('loading', 'Loading...')
                    },
                    completedButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-chart-pie',
                        label: t('refresh_stats', 'Refresh Stats')
                    },
                    errorButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-chart-pie',
                        label: t('load_failed', 'Load Failed')
                    },
                    onSuccess: function(data) {
                        if (!data.success) {
                            return;
                        }

                        $dashboard.show();
                        $summary.html(
                            '<div>' + escapeHtml(t('optimized_images', 'Optimized Images:')) + ' ' + escapeHtml(data.data.total_images) + '</div>' +
                            '<div>' + escapeHtml(t('space_saved', 'Space Saved:')) + ' ' + escapeHtml(formatBytes(data.data.total_savings)) + '</div>' +
                            '<div>' + escapeHtml(t('pending_optimization', 'Pending Optimization:')) + ' ' + escapeHtml(data.data.unoptimized_count) + '</div>'
                        );

                        var recentOptimizations = Array.isArray(data.data.recent_optimizations) ? data.data.recent_optimizations.slice().reverse() : [];
                        $recent.html(recentOptimizations.map(function(item) {
                            return '' +
                                '<div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px;">' +
                                    '<div><strong>' + escapeHtml(item.filename || '') + '</strong></div>' +
                                    '<div>' + escapeHtml(t('saved_label', 'Saved:')) + ' ' + escapeHtml(formatBytes(item.savings)) + ' (' + escapeHtml(item.percentage) + '%)</div>' +
                                '</div>';
                        }).join(''));
                    }
                });
            });

        $(document)
            .off('click.folioLoadPerformanceData', '#folio-load-performance-data')
            .on('click.folioLoadPerformanceData', '#folio-load-performance-data', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $dashboard = $('#folio-performance-dashboard');
                var $stats = $('#folio-performance-stats');
                var $requests = $('#folio-recent-requests');
                var $slowQueries = $('#folio-slow-queries');

                runPostAction({
                    action: 'folio_get_performance_data',
                    nonceKey: 'get_performance_data',
                    nonceField: '_ajax_nonce',
                    button: $btn,
                    rememberButton: true,
                    loadingButton: {
                        disabled: true,
                        type: 'text',
                        label: t('loading', 'Loading...')
                    },
                    completedButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-chart-line',
                        label: t('refresh_data', 'Refresh Data')
                    },
                    errorButton: {
                        disabled: false,
                        type: 'dashicon',
                        iconClass: 'dashicons-chart-line',
                        label: t('load_failed', 'Load Failed')
                    },
                    onSuccess: function(data) {
                        if (!data.success) {
                            return;
                        }

                        var stats = data.data.stats || {};
                        var requestLogs = Array.isArray(data.data.logs) ? data.data.logs.slice(-10).reverse() : [];
                        var slowQueries = Array.isArray(data.data.slow_queries) ? data.data.slow_queries : [];

                        $dashboard.show();
                        $stats.html(
                            '<div style="margin-bottom: 8px;"><strong>' + escapeHtml(t('frontend_page_performance', 'Frontend Page Performance')) + '</strong></div>' +
                            '<div style="margin-bottom: 8px;"><strong>' + escapeHtml(t('performance_score', 'Performance Score:')) + ' </strong><span style="color: ' + (stats.performance_score >= 80 ? '#28a745' : (stats.performance_score >= 60 ? '#ffc107' : '#dc3545')) + '; font-size: 16px;">' + escapeHtml(stats.performance_score || 0) + '/100</span></div>' +
                            '<div>' + escapeHtml(t('average_load_time', 'Average Load Time:')) + ' <span style="color: ' + ((stats.avg_load_time || 0) > 2 ? '#dc3545' : '#28a745') + '">' + escapeHtml(stats.avg_load_time ? Number(stats.avg_load_time).toFixed(3) : 0) + 's</span></div>' +
                            '<div>' + escapeHtml(t('max_load_time', 'Max Load Time:')) + ' <span style="color: ' + ((stats.max_load_time || 0) > 3 ? '#dc3545' : '#ffc107') + '">' + escapeHtml(stats.max_load_time ? Number(stats.max_load_time).toFixed(3) : 0) + 's</span></div>' +
                            '<div>' + escapeHtml(t('average_memory_usage', 'Average Memory Usage:')) + ' ' + escapeHtml(formatBytes(stats.avg_memory || 0)) + '</div>' +
                            '<div>' + escapeHtml(t('average_query_count', 'Average Query Count:')) + ' <span style="color: ' + ((stats.avg_queries || 0) > 30 ? '#dc3545' : '#28a745') + '">' + escapeHtml(Math.round(stats.avg_queries || 0)) + '</span></div>' +
                            '<div>' + escapeHtml(t('mobile_visits', 'Mobile Visits:')) + ' ' + escapeHtml(stats.mobile_percentage || 0) + '%</div>' +
                            '<div>' + escapeHtml(t('optimization_issues', 'Optimization Issues:')) + ' <span style="color: ' + ((stats.optimization_issues || 0) > 0 ? '#dc3545' : '#28a745') + '">' + escapeHtml(stats.optimization_issues || 0) + '</span></div>' +
                            '<div>' + escapeHtml(t('total_page_visits', 'Total Page Visits:')) + ' ' + escapeHtml(stats.total_requests || 0) + '</div>' +
                            '<div style="margin-top: 8px; font-size: 11px; color: #666;">' + escapeHtml(t('frontend_page_data_only', 'Frontend page data only')) + '</div>'
                        );

                        $requests.html(requestLogs.map(function(log) {
                            var loadTime = Number(log.load_time || 0);
                            var pageType = log.page_type || t('unknown_label', 'unknown');
                            var suggestions = Array.isArray(log.optimization_suggestions) ? log.optimization_suggestions.length : 0;

                            return '' +
                                '<div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px; border-left: 3px solid ' + (loadTime > 3 ? '#dc3545' : (loadTime > 2 ? '#ffc107' : '#28a745')) + ';">' +
                                    '<div><strong>' + escapeHtml(log.url || '') + '</strong> <span style="background: #e9ecef; padding: 2px 6px; border-radius: 10px; font-size: 10px;">' + escapeHtml(pageType) + '</span></div>' +
                                    '<div>⏱️ ' + escapeHtml(loadTime.toFixed(3)) + 's | 💾 ' + escapeHtml(formatBytes(log.memory_usage || 0)) + ' | 🗄️ ' + escapeHtml(log.db_queries || 0) + ' ' + escapeHtml(t('queries_label', 'queries')) + '</div>' +
                                    (log.is_mobile ? '<div style="color: #6c757d; font-size: 10px;">[' + escapeHtml(t('mobile_device', 'Mobile Device')) + ']</div>' : '') +
                                    (suggestions > 0 ? '<div style="color: #dc3545; font-size: 10px;">[' + escapeHtml(suggestions) + ' ' + escapeHtml(t('optimization_suggestions', 'Optimization Suggestions')) + ']</div>' : '') +
                                '</div>';
                        }).join(''));

                        if (slowQueries.length > 0) {
                            $slowQueries.html(slowQueries.map(function(item) {
                                var queryTime = Number(item.time || 0);
                                var querySql = item.sql ? String(item.sql).substring(0, 100) : '';

                                return '' +
                                    '<div style="margin-bottom: 8px; padding: 8px; background: rgba(255, 193, 7, 0.1); border-radius: 3px; font-size: 12px;">' +
                                        '<div style="color: #856404;"><strong>' + escapeHtml(queryTime.toFixed(3)) + 's</strong> - ' + escapeHtml(item.url || '') + '</div>' +
                                        '<div style="font-family: monospace; color: #6c757d;">' + escapeHtml(querySql) + '...</div>' +
                                    '</div>';
                            }).join(''));
                        } else {
                            $slowQueries.html('<div style="color: #28a745;">' + escapeHtml(t('no_slow_query_records', 'No slow query records')) + '</div>');
                        }
                    }
                });
            });

        $(document)
            .off('click.folioClearPerformanceLogs', '#folio-clear-performance-logs')
            .on('click.folioClearPerformanceLogs', '#folio-clear-performance-logs', function(e) {
                e.preventDefault();

                var $btn = $(this);

                runPostAction({
                    action: 'folio_clear_performance_logs',
                    nonceKey: 'clear_performance_logs',
                    nonceField: '_ajax_nonce',
                    button: $btn,
                    confirmMessage: t('clear_performance_logs_confirm', 'Are you sure you want to clear all performance logs?'),
                    loadingButton: {
                        disabled: true
                    },
                    completedButton: {
                        disabled: false
                    },
                    onSuccess: function(data) {
                        if (data.success) {
                            $('#folio-performance-dashboard').hide();
                            window.alert(t('performance_logs_cleared', 'Performance logs have been cleared'));
                        }
                    }
                });
            });

        $(document)
            .off('click.folioResetSettings', '#folio-reset-settings')
            .on('click.folioResetSettings', '#folio-reset-settings', function(e) {
                e.preventDefault();

                if (!window.confirm(t('reset_settings_confirm', 'Are you sure you want to reset all settings? This cannot be undone!'))) {
                    return;
                }

                if (!window.confirm(t('reset_settings_final_confirm', 'Final confirmation: really reset?'))) {
                    return;
                }

                var config = getConfig();

                if (config.reset_settings_url) {
                    window.location.href = config.reset_settings_url;
                }
            });
    });
})(jQuery);
