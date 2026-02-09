<?php
/**
 * AI API Manager
 * 
 * 统一的AI API调用管理器，支持多个API配置和轮询机制
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Folio_AI_API_Manager {
    
    private static $instance = null;
    private $apis = array();
    private $current_index = 0;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->load_apis();
    }
    
    /**
     * 加载API配置
     */
    private function load_apis() {
        $options = get_option('folio_theme_options', array());
        
        // 优先使用新的多API配置
        if (isset($options['ai_apis']) && is_array($options['ai_apis']) && !empty($options['ai_apis'])) {
            foreach ($options['ai_apis'] as $api) {
                if (isset($api['enabled']) && $api['enabled'] == 1 && !empty($api['endpoint']) && !empty($api['key'])) {
                    $this->apis[] = array(
                        'name' => isset($api['name']) ? $api['name'] : '',
                        'endpoint' => $api['endpoint'],
                        'key' => $api['key'],
                        'model' => isset($api['model']) && !empty($api['model']) ? $api['model'] : 'gpt-3.5-turbo',
                    );
                }
            }
        }
        
        // 兼容旧版本：如果新配置为空，使用旧的单个API配置
        if (empty($this->apis) && !empty($options['ai_api_endpoint']) && !empty($options['ai_api_key'])) {
            $this->apis[] = array(
                'name' => '默认API',
                'endpoint' => $options['ai_api_endpoint'],
                'key' => $options['ai_api_key'],
                'model' => isset($options['ai_model']) && !empty($options['ai_model']) ? $options['ai_model'] : 'gpt-3.5-turbo',
            );
        }
    }
    
    /**
     * 调用AI API（支持轮询）
     * 
     * @param string $prompt 提示词
     * @param array $options 额外选项（如temperature, max_tokens等）
     * @return string|false 返回AI响应内容，失败返回false
     */
    public function call_api($prompt, $options = array()) {
        if (empty($this->apis)) {
            error_log('Folio AI API Manager: 没有可用的API配置');
            return false;
        }
        
        // 从上次成功的位置开始，如果上次失败则从下一个开始
        $start_index = $this->current_index;
        $attempted = 0;
        $max_attempts = count($this->apis);
        
        while ($attempted < $max_attempts) {
            $api = $this->apis[$this->current_index];
            $attempted++;
            
            // 尝试调用API
            $result = $this->try_call_api($api, $prompt, $options);
            
            if (is_array($result) && isset($result['success']) && $result['success']) {
                // 成功，更新当前索引为下一个（用于下次轮询）
                $this->current_index = ($this->current_index + 1) % count($this->apis);
                return $result['response'];
            }
            
            // 失败，尝试下一个API
            $this->current_index = ($this->current_index + 1) % count($this->apis);
            
            // 如果已经尝试了所有API，跳出循环
            if ($this->current_index === $start_index) {
                break;
            }
        }
        
        error_log('Folio AI API Manager: 所有API调用都失败了');
        return false;
    }
    
    /**
     * 尝试调用单个API
     * 
     * @param array $api API配置
     * @param string $prompt 提示词
     * @param array $options 额外选项
     * @return array|false 返回数组包含success和详细信息，失败返回false
     */
    private function try_call_api($api, $prompt, $options = array()) {
        $endpoint = $api['endpoint'];
        $key = $api['key'];
        $model = $api['model'];
        
        // 默认选项
        $default_options = array(
            'temperature' => 0.7,
            'max_tokens' => 1024,
        );
        $options = wp_parse_args($options, $default_options);
        
        // 检测API类型（通过endpoint或model名称）
        $is_deepseek = false;
        if (stripos($endpoint, 'deepseek') !== false || stripos($model, 'deepseek') !== false) {
            $is_deepseek = true;
        }
        
        // 构建请求体
        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
        );
        
        // DeepSeek API 要求：非流式调用时必须设置 enable_thinking 为 false
        if ($is_deepseek) {
            $body['enable_thinking'] = false;
        }
        
        // 确保不包含 stream 参数（非流式调用）
        // 如果 options 中有 stream，但值为 false 或未设置，则不添加
        if (isset($options['stream']) && $options['stream'] === true) {
            $body['stream'] = true;
        }
        
        // 发送请求，明确 UTF-8 编码以避免日韩文被替换为问号
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept-Charset' => 'utf-8',
                'Authorization' => 'Bearer ' . $key,
            ),
            'body' => wp_json_encode($body),
            'timeout' => isset($options['timeout']) ? $options['timeout'] : 30,
        ));
        
        // 检查错误
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Folio AI API Manager Error (' . $api['name'] . '): ' . $error_message);
            return array(
                'success' => false,
                'error' => '网络错误: ' . $error_message,
                'response' => null,
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_info = 'HTTP ' . $response_code;
            $error_data = json_decode($response_body, true);
            if (isset($error_data['error']['message'])) {
                $error_info .= ': ' . $error_data['error']['message'];
            } elseif (isset($error_data['error'])) {
                $error_info .= ': ' . (is_string($error_data['error']) ? $error_data['error'] : json_encode($error_data['error']));
            }
            error_log('Folio AI API Manager Error (' . $api['name'] . '): ' . $error_info);
            return array(
                'success' => false,
                'error' => $error_info,
                'response' => null,
            );
        }
        
        $data = json_decode($response_body, true);
        
        // 解析响应，支持多种 API 返回格式
        $content = null;
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
        } elseif (isset($data['data']['choices'][0]['message']['content'])) {
            $content = $data['data']['choices'][0]['message']['content'];
        } elseif (!empty($data['result'])) {
            $content = is_string($data['result']) ? $data['result'] : '';
        } elseif (!empty($data['output'])) {
            $content = is_string($data['output']) ? $data['output'] : (isset($data['output']['text']) ? $data['output']['text'] : '');
        } elseif (!empty($data['text'])) {
            $content = $data['text'];
        }
        
        if (is_string($content) && trim($content) !== '') {
            return array(
                'success' => true,
                'error' => null,
                'response' => trim($content),
            );
        }
        
        // 记录错误
        $error_message = '无法解析响应';
        if (isset($data['error'])) {
            $error_message = 'API错误: ' . (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']));
            error_log('Folio AI API Manager Error (' . $api['name'] . '): ' . $error_message);
        } else {
            error_log('Folio AI API Manager Error (' . $api['name'] . '): ' . $error_message . ' - ' . substr($response_body, 0, 200));
        }
        
        return array(
            'success' => false,
            'error' => $error_message,
            'response' => null,
        );
    }
    
    /**
     * 获取所有可用的API配置
     * 
     * @return array
     */
    public function get_apis() {
        return $this->apis;
    }
    
    /**
     * 检查是否有可用的API
     * 
     * @return bool
     */
    public function has_apis() {
        return !empty($this->apis);
    }
    
    /**
     * 测试API连接
     * 
     * @param int $api_index API索引（可选，不指定则测试所有）
     * @return array 测试结果
     */
    public function test_connection($api_index = null) {
        $results = array();
        
        if ($api_index !== null) {
            // 测试指定API
            if (isset($this->apis[$api_index])) {
                $api = $this->apis[$api_index];
                $test_prompt = "请回复：连接成功";
                $result = $this->try_call_api($api, $test_prompt);
                $results[$api_index] = array(
                    'name' => $api['name'] ?: 'API配置 #' . ($api_index + 1),
                    'endpoint' => $api['endpoint'],
                    'model' => $api['model'],
                    'success' => is_array($result) && isset($result['success']) ? $result['success'] : false,
                    'message' => is_array($result) && isset($result['success']) && $result['success'] ? '连接成功' : (is_array($result) && isset($result['error']) ? $result['error'] : '连接失败'),
                    'response' => is_array($result) && isset($result['response']) ? $result['response'] : null,
                    'error' => is_array($result) && isset($result['error']) ? $result['error'] : null,
                );
            }
        } else {
            // 测试所有API
            foreach ($this->apis as $index => $api) {
                $test_prompt = "请回复：连接成功";
                $result = $this->try_call_api($api, $test_prompt);
                $results[$index] = array(
                    'name' => $api['name'] ?: 'API配置 #' . ($index + 1),
                    'endpoint' => $api['endpoint'],
                    'model' => $api['model'],
                    'success' => is_array($result) && isset($result['success']) ? $result['success'] : false,
                    'message' => is_array($result) && isset($result['success']) && $result['success'] ? '连接成功' : (is_array($result) && isset($result['error']) ? $result['error'] : '连接失败'),
                    'response' => is_array($result) && isset($result['response']) ? $result['response'] : null,
                    'error' => is_array($result) && isset($result['error']) ? $result['error'] : null,
                );
            }
        }
        
        return $results;
    }
}
