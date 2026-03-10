<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('AI API Configuration', 'folio'); ?></h2>
        <p class="description"><?php esc_html_e('Configure multiple AI services for auto-generating summaries and keywords. Supports OpenAI-compatible APIs (OpenAI, Azure OpenAI, Qwen, ERNIE, etc.). The system automatically rotates available APIs.', 'folio'); ?></p>

        <div id="folio-ai-apis-container"
             data-option-name="<?php echo esc_attr($option_name); ?>"
             data-next-index="<?php echo esc_attr(count($ai_apis)); ?>">
            <?php foreach ($ai_apis as $index => $api) : ?>
                <div class="folio-ai-api-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="folio-ai-api-header">
                        <h3><?php esc_html_e('API Config', 'folio'); ?> #<?php echo esc_html($index + 1); ?></h3>
                        <button type="button" class="button button-small folio-remove-api folio-button-danger"><?php esc_html_e('Delete', 'folio'); ?></button>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Config Name', 'folio'); ?></th>
                            <td>
                                <input type="text" name="<?php echo esc_attr($option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr(isset($api['name']) ? $api['name'] : ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('Example: OpenAI Primary', 'folio'); ?>">
                                <p class="description"><?php esc_html_e('Used to identify this API config.', 'folio'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled', 'folio'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(isset($api['enabled']) ? $api['enabled'] : 1, 1); ?>>
                                    <?php esc_html_e('Enable this API config', 'folio'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('API Endpoint', 'folio'); ?></th>
                            <td>
                                <input type="url" name="<?php echo esc_attr($option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][endpoint]" value="<?php echo esc_attr(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">
                                <p class="description">
                                    <?php esc_html_e('API endpoint URL', 'folio'); ?><br>
                                    <strong>OpenAI:</strong> https://api.openai.com/v1/chat/completions<br>
                                    <strong>通义千问:</strong> https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions<br>
                                    <strong>文心一言:</strong> https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'folio'); ?></th>
                            <td>
                                <input type="password" name="<?php echo esc_attr($option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr(isset($api['key']) ? $api['key'] : ''); ?>" class="large-text" placeholder="sk-...">
                                <p class="description"><?php esc_html_e('Your API key', 'folio'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Model Name', 'folio'); ?></th>
                            <td>
                                <input type="text" name="<?php echo esc_attr($option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][model]" value="<?php echo esc_attr(isset($api['model']) ? $api['model'] : 'gpt-3.5-turbo'); ?>" class="regular-text" placeholder="gpt-3.5-turbo">
                                <p class="description">
                                    <?php esc_html_e('AI model to use', 'folio'); ?><br>
                                    <strong>OpenAI:</strong> gpt-3.5-turbo, gpt-4<br>
                                    <strong>通义千问:</strong> qwen-turbo, qwen-plus<br>
                                    <strong>文心一言:</strong> ernie-bot-turbo, ernie-bot
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button" id="folio-add-api"><?php esc_html_e('+ Add API Config', 'folio'); ?></button>
        </p>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Feature Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable AI Generation', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[ai_enabled]" value="1" <?php checked(isset($options['ai_enabled']) ? $options['ai_enabled'] : 1, 1); ?>>
                        <?php esc_html_e('Show AI generation panel on post editor page', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto-generate by Default', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[ai_auto_generate_default]" value="1" <?php checked(isset($options['ai_auto_generate_default']) ? $options['ai_auto_generate_default'] : 0, 1); ?>>
                        <?php esc_html_e('Enable "auto-generate on save" by default for new posts', 'folio'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Connection Test', 'folio'); ?></h2>
        <p class="description"><?php esc_html_e('After saving settings, test all enabled AI API connections. Each API config will be tested automatically.', 'folio'); ?></p>
        <button type="button" class="button" id="folio-test-ai-connection">
            <?php esc_html_e('Test All APIs', 'folio'); ?>
        </button>
        <div id="folio-ai-test-result" class="folio-result-margin-top"></div>
    </div>
</div>
