<?php    
/**
 * Plugin Name: SNN Socials
 * Plugin URI: https://sinanisler.com
 * Description: Publish images and videos to X (Twitter), LinkedIn, Instagram, and YouTube sequentially with a smart queue manager.
 * Version: 2.0.0
 * Author: sinanisler
 * Author URI: https://sinanisler.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include GitHub updater (Optional check to prevent errors if file missing)
if (file_exists(plugin_dir_path(__FILE__) . 'github-update.php')) {
    require_once plugin_dir_path(__FILE__) . 'github-update.php';
}

class SNN_Socials {
    
    private $option_name = 'snn_socials_settings';
    
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_snn_publish_single_step', array($this, 'ajax_publish_single_step'));
        add_action('wp_ajax_snn_test_connection', array($this, 'ajax_test_connection'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'SNN Socials',
            'SNN Socials',
            'manage_options',
            'snn-socials',
            array($this, 'render_publish_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('snn_socials_settings', $this->option_name);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_snn-socials') {
            return;
        }

        wp_enqueue_media();
        wp_register_style('snn-socials-admin', false);
        wp_enqueue_style('snn-socials-admin');
        wp_add_inline_style('snn-socials-admin', $this->get_admin_css());

        wp_register_script('snn-socials-admin', false, array('jquery', 'media-upload', 'media-views'), '2.0.0', true);
        wp_enqueue_script('snn-socials-admin');

        wp_localize_script('snn-socials-admin', 'snnSocials', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('snn_socials_nonce')
        ));

        wp_add_inline_script('snn-socials-admin', $this->get_admin_js());
    }
    
    /**
     * Render publish page
     */
    public function render_publish_page() {
        // Handle settings save
        if (isset($_POST['snn_save_settings'])) {
            check_admin_referer('snn_socials_settings_nonce');

            $settings = array(
                // X (Twitter) Settings
                'x_api_key' => sanitize_text_field($_POST['x_api_key'] ?? ''),
                'x_api_secret' => sanitize_text_field($_POST['x_api_secret'] ?? ''),
                'x_access_token' => sanitize_text_field($_POST['x_access_token'] ?? ''),
                'x_access_secret' => sanitize_text_field($_POST['x_access_secret'] ?? ''),

                // LinkedIn Settings
                'linkedin_client_id' => sanitize_text_field($_POST['linkedin_client_id'] ?? ''),
                'linkedin_client_secret' => sanitize_text_field($_POST['linkedin_client_secret'] ?? ''),
                'linkedin_access_token' => sanitize_text_field($_POST['linkedin_access_token'] ?? ''),
                'linkedin_org_id' => sanitize_text_field($_POST['linkedin_org_id'] ?? ''),

                // Instagram Settings
                'instagram_access_token' => sanitize_text_field($_POST['instagram_access_token'] ?? ''),
                'instagram_business_account_id' => sanitize_text_field($_POST['instagram_business_account_id'] ?? ''),

                // YouTube Settings
                'youtube_client_id' => sanitize_text_field($_POST['youtube_client_id'] ?? ''),
                'youtube_client_secret' => sanitize_text_field($_POST['youtube_client_secret'] ?? ''),
                'youtube_refresh_token' => sanitize_text_field($_POST['youtube_refresh_token'] ?? ''),
            );

            update_option($this->option_name, $settings);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }

        $settings = get_option($this->option_name, array());
        ?>
        <div class="wrap snn-socials-wrap">
            <h1>🚀 SNN Socials - Smart Publisher</h1>

            <div class="snn-publish-container">
                <div class="snn-publish-form">
                    <h2>Create Post</h2>

                    <div class="snn-form-group">
                        <label for="snn-post-text">Post Text / Caption <span class="char-count"></span></label><br>
                        <textarea id="snn-post-text" rows="8" maxlength="5000" placeholder="Write your post text here..."></textarea>
                    </div>

                    <div class="snn-form-group">
                        <label>Media (Image or Video)</label>
                        <div class="media-upload-area">
                            <button type="button" class="button button-secondary" id="snn-select-media">Select Media</button>
                            <button type="button" class="button button-link-delete" id="snn-remove-media" style="display:none;">
                                <span class="dashicons dashicons-no"></span> Remove Media
                            </button>
                        </div>
                        <div id="snn-media-preview"></div>
                        <input type="hidden" id="snn-media-id" value="">
                        <input type="hidden" id="snn-media-url" value="">
                        <input type="hidden" id="snn-media-type" value="">
                    </div>

                    <div class="snn-form-group">
                        <label>Publish To:</label>
                        <div class="snn-platforms">
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms" value="x" data-name="X (Twitter)" data-icon="𝕏">
                                <span class="platform-label"><span class="platform-icon">𝕏</span> X (Twitter)</span>
                            </label>
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms" value="linkedin" data-name="LinkedIn" data-icon="in">
                                <span class="platform-label"><span class="platform-icon">in</span> LinkedIn</span>
                            </label>
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms" value="instagram" data-name="Instagram" data-icon="📷">
                                <span class="platform-label"><span class="platform-icon">📷</span> Instagram</span>
                            </label>
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms" value="youtube" data-name="YouTube" data-icon="▶">
                                <span class="platform-label"><span class="platform-icon">▶</span> YouTube</span>
                            </label>
                        </div>
                    </div>

                    <div class="snn-form-group">
                        <button type="button" class="button button-primary button-large" id="snn-publish-btn">Publish Now</button>
                    </div>
                </div>

                <div class="snn-sidebar">
                    <div id="snn-queue-container" class="snn-info-box" style="display:none;">
                        <h3>📋 Publishing Queue</h3>
                        <div id="snn-eta-display" class="eta-badge">Ready to start</div>
                        <div id="snn-process-queue" class="snn-process-list">
                            </div>
                        <div id="snn-final-summary" style="display:none; margin-top:15px; border-top:1px solid #eee; padding-top:10px;"></div>
                    </div>

                    <div class="snn-info-box">
                        <h3>💡 Tips</h3>
                        <ul>
                            <li>Images: JPEG/PNG format</li>
                            <li>Videos: MP4 recommended</li>
                            <li>Instagram: Square ratio best</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="snn-api-settings-wrapper">
                <details class="snn-settings-accordion">
                    <summary>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <strong>API Settings & Credentials</strong>
                    </summary>
                    <div class="snn-settings-content">
                        <form method="post" action="">
                            <?php wp_nonce_field('snn_socials_settings_nonce'); ?>
                            
                            <div class="snn-settings-section">
                                <h2>𝕏 X (Twitter)</h2>
                                <table class="form-table">
                                    <tr><th>API Key</th><td><input type="text" name="x_api_key" value="<?php echo esc_attr($settings['x_api_key'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>API Secret</th><td><input type="password" name="x_api_secret" value="<?php echo esc_attr($settings['x_api_secret'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Access Token</th><td><input type="text" name="x_access_token" value="<?php echo esc_attr($settings['x_access_token'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Access Secret</th><td><input type="password" name="x_access_secret" value="<?php echo esc_attr($settings['x_access_secret'] ?? ''); ?>" class="regular-text"></td></tr>
                                </table>
                            </div>

                            <div class="snn-settings-section">
                                <h2>🔗 LinkedIn</h2>
                                <table class="form-table">
                                    <tr><th>Client ID</th><td><input type="text" name="linkedin_client_id" value="<?php echo esc_attr($settings['linkedin_client_id'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Client Secret</th><td><input type="password" name="linkedin_client_secret" value="<?php echo esc_attr($settings['linkedin_client_secret'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Access Token</th><td><input type="text" name="linkedin_access_token" value="<?php echo esc_attr($settings['linkedin_access_token'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Organization ID</th><td><input type="text" name="linkedin_org_id" value="<?php echo esc_attr($settings['linkedin_org_id'] ?? ''); ?>" class="regular-text"><p class="description">Optional</p></td></tr>
                                </table>
                            </div>

                            <div class="snn-settings-section">
                                <h2>📷 Instagram</h2>
                                <table class="form-table">
                                    <tr><th>Access Token</th><td><input type="text" name="instagram_access_token" value="<?php echo esc_attr($settings['instagram_access_token'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Business ID</th><td><input type="text" name="instagram_business_account_id" value="<?php echo esc_attr($settings['instagram_business_account_id'] ?? ''); ?>" class="regular-text"></td></tr>
                                </table>
                            </div>

                            <div class="snn-settings-section">
                                <h2>▶ YouTube</h2>
                                <table class="form-table">
                                    <tr><th>Client ID</th><td><input type="text" name="youtube_client_id" value="<?php echo esc_attr($settings['youtube_client_id'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Client Secret</th><td><input type="password" name="youtube_client_secret" value="<?php echo esc_attr($settings['youtube_client_secret'] ?? ''); ?>" class="regular-text"></td></tr>
                                    <tr><th>Refresh Token</th><td><input type="text" name="youtube_refresh_token" value="<?php echo esc_attr($settings['youtube_refresh_token'] ?? ''); ?>" class="regular-text"></td></tr>
                                </table>
                            </div>

                            <p class="submit"><input type="submit" name="snn_save_settings" class="button button-primary" value="Save API Settings"></p>
                        </form>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Publish Single Step (The Abstraction Layer)
     * Handles one platform at a time based on the method name
     */
    public function ajax_publish_single_step() {
        check_ajax_referer('snn_socials_nonce', 'nonce');
        
        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $media_url = esc_url_raw($_POST['media_url'] ?? '');
        $media_type = sanitize_text_field($_POST['media_type'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        // Dynamic Method Construction
        $method_name = 'publish_to_' . $platform;

        // Abstraction Logic: Check if method exists, otherwise fail gracefully
        if (method_exists($this, $method_name)) {
            try {
                // Dispatch to specific platform method
                $result = $this->$method_name($text, $media_url, $media_type);
            } catch (Exception $e) {
                $result = array('success' => false, 'message' => 'Exception: ' . $e->getMessage());
            }
        } else {
            $result = array('success' => false, 'message' => "Platform handler '{$platform}' not found.");
        }
        
        wp_send_json($result);
    }
    
    // ----------------------------------------------------------------
    // Platform Specific Methods (Standardized return format)
    // ----------------------------------------------------------------

    private function publish_to_x($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        if (empty($settings['x_api_key'])) return array('success' => false, 'message' => 'Credentials missing');
        
        try {
            $media_id = null;
            if (!empty($media_url)) {
                $media_id = $this->upload_media_to_x($media_url, $settings);
                if (!$media_id) return array('success' => false, 'message' => 'Media upload failed');
            }
            
            $tweet_data = array('text' => $text);
            if ($media_id) $tweet_data['media'] = array('media_ids' => array($media_id));
            
            $response = $this->make_x_request('POST', 'https://api.x.com/2/tweets', $tweet_data, $settings);
            
            if (isset($response['data']['id'])) {
                return array('success' => true, 'message' => 'Published successfully');
            }
            return array('success' => false, 'message' => 'API Error: ' . json_encode($response));
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    // Reuse existing helper methods for X
    private function upload_media_to_x($media_url, $settings) {
        $media_file = download_url($media_url);
        if (is_wp_error($media_file)) return false;
        
        $boundary = wp_generate_password(24, false);
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media\"; filename=\"" . basename($media_url) . "\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= file_get_contents($media_file) . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $oauth_params = $this->generate_x_oauth_params('POST', 'https://upload.x.com/1.1/media/upload.json', array(), $settings);
        
        $response = wp_remote_post('https://upload.x.com/1.1/media/upload.json', array(
            'headers' => array(
                'Authorization' => 'OAuth ' . $this->build_oauth_header($oauth_params),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary
            ),
            'body' => $body, 'timeout' => 60
        ));
        
        @unlink($media_file);
        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['media_id_string'] ?? false;
    }

    private function make_x_request($method, $url, $data, $settings) {
        $oauth_params = $this->generate_x_oauth_params($method, $url, array(), $settings);
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'OAuth ' . $this->build_oauth_header($oauth_params),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        if ($method === 'POST' && !empty($data)) $args['body'] = json_encode($data);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function generate_x_oauth_params($method, $url, $params, $settings) {
        $oauth = array(
            'oauth_consumer_key' => $settings['x_api_key'],
            'oauth_nonce' => md5(microtime() . mt_rand()),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $settings['x_access_token'],
            'oauth_version' => '1.0'
        );
        $oauth['oauth_signature'] = $this->generate_x_signature($method, $url, array_merge($oauth, $params), $settings['x_api_secret'], $settings['x_access_secret']);
        return $oauth;
    }

    private function generate_x_signature($method, $url, $params, $consumer_secret, $token_secret) {
        ksort($params);
        $param_string = '';
        foreach ($params as $key => $value) $param_string .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        $param_string = rtrim($param_string, '&');
        $base = $method . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);
        $key = rawurlencode($consumer_secret) . '&' . rawurlencode($token_secret);
        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }

    private function build_oauth_header($oauth_params) {
        $parts = array();
        foreach ($oauth_params as $key => $value) $parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        return implode(', ', $parts);
    }

    private function publish_to_linkedin($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        if (empty($settings['linkedin_access_token'])) return array('success' => false, 'message' => 'Token missing');
        
        try {
            $author = !empty($settings['linkedin_org_id']) ? 'urn:li:organization:' . $settings['linkedin_org_id'] : $this->get_linkedin_person_urn($settings['linkedin_access_token']);
            if (!$author) return array('success' => false, 'message' => 'Author URN not found');
            
            $post_data = array(
                'author' => $author,
                'commentary' => $text,
                'visibility' => 'PUBLIC',
                'distribution' => array('feedDistribution' => 'MAIN_FEED', 'targetEntities' => array(), 'thirdPartyDistributionChannels' => array()),
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false
            );
            
            if (!empty($media_url)) {
                $media_urn = $this->upload_media_to_linkedin($media_url, $media_type, $author, $settings);
                if ($media_urn) $post_data['content'] = array('media' => array('title' => 'Media', 'id' => $media_urn));
            }
            
            $response = wp_remote_post('https://api.linkedin.com/rest/posts', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['linkedin_access_token'],
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'LinkedIn-Version' => date('Ym')
                ),
                'body' => json_encode($post_data), 'timeout' => 30
            ));
            
            if (is_wp_error($response)) return array('success' => false, 'message' => $response->get_error_message());
            if (wp_remote_retrieve_response_code($response) === 201) return array('success' => true, 'message' => 'Published successfully');
            return array('success' => false, 'message' => 'API Error: ' . wp_remote_retrieve_body($response));
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    private function get_linkedin_person_urn($access_token) {
        $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', array('headers' => array('Authorization' => 'Bearer ' . $access_token)));
        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return isset($data['sub']) ? 'urn:li:person:' . $data['sub'] : false;
    }

    private function upload_media_to_linkedin($media_url, $media_type, $author, $settings) {
        $recipe = strpos($media_type, 'video') !== false ? 'urn:li:digitalmediaRecipe:feedshare-video' : 'urn:li:digitalmediaRecipe:feedshare-image';
        $register = array('registerUploadRequest' => array('owner' => $author, 'recipes' => array($recipe), 'serviceRelationships' => array(array('identifier' => 'urn:li:userGeneratedContent', 'relationshipType' => 'OWNER')), 'supportedUploadMechanism' => array('SYNCHRONOUS_UPLOAD')));
        
        $response = wp_remote_post('https://api.linkedin.com/rest/assets?action=registerUpload', array(
            'headers' => array('Authorization' => 'Bearer ' . $settings['linkedin_access_token'], 'Content-Type' => 'application/json', 'X-Restli-Protocol-Version' => '2.0.0', 'LinkedIn-Version' => date('Ym')),
            'body' => json_encode($register), 'timeout' => 30
        ));
        
        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $upload_url = $data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $asset = $data['value']['asset'] ?? null;
        
        if (!$upload_url || !$asset) return false;
        
        $media_file = download_url($media_url);
        if (is_wp_error($media_file)) return false;
        
        $up_res = wp_remote_post($upload_url, array('headers' => array('Authorization' => 'Bearer ' . $settings['linkedin_access_token']), 'body' => file_get_contents($media_file), 'timeout' => 60));
        @unlink($media_file);
        
        return is_wp_error($up_res) ? false : $asset;
    }

    private function publish_to_instagram($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        if (empty($settings['instagram_access_token'])) return array('success' => false, 'message' => 'Credentials missing');
        
        try {
            $container_data = array('caption' => $text, 'access_token' => $settings['instagram_access_token']);
            if (strpos($media_type, 'video') !== false) {
                $container_data['media_type'] = 'VIDEO';
                $container_data['video_url'] = $media_url;
            } else {
                $container_data['image_url'] = $media_url;
            }
            
            $create_res = wp_remote_post("https://graph.facebook.com/v18.0/{$settings['instagram_business_account_id']}/media?" . http_build_query($container_data));
            if (is_wp_error($create_res)) return array('success' => false, 'message' => $create_res->get_error_message());
            
            $create_data = json_decode(wp_remote_retrieve_body($create_res), true);
            $container_id = $create_data['id'] ?? null;
            if (!$container_id) return array('success' => false, 'message' => 'Container creation failed: ' . json_encode($create_data));
            
            sleep(3); // Wait for processing
            
            $pub_res = wp_remote_post("https://graph.facebook.com/v18.0/{$settings['instagram_business_account_id']}/media_publish?" . http_build_query(array('creation_id' => $container_id, 'access_token' => $settings['instagram_access_token'])));
            $pub_data = json_decode(wp_remote_retrieve_body($pub_res), true);
            
            if (isset($pub_data['id'])) return array('success' => true, 'message' => 'Published successfully');
            return array('success' => false, 'message' => 'Publish failed: ' . json_encode($pub_data));
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    private function publish_to_youtube($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        if (strpos($media_type, 'video') === false) return array('success' => false, 'message' => 'Only video supported');
        
        $access_token = $this->get_youtube_access_token($settings);
        if (!$access_token) return array('success' => false, 'message' => 'Auth failed');
        
        $video_file = download_url($media_url);
        if (is_wp_error($video_file)) return array('success' => false, 'message' => 'Download failed');
        
        $metadata = array('snippet' => array('title' => substr($text, 0, 100) ?: 'Video', 'description' => $text, 'categoryId' => '22'), 'status' => array('privacyStatus' => 'public'));
        
        $boundary = wp_generate_password(24, false);
        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\nContent-Type: video/mp4\r\n\r\n" . file_get_contents($video_file) . "\r\n--{$boundary}--\r\n";
        
        $response = wp_remote_post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'multipart/related; boundary=' . $boundary),
            'body' => $body, 'timeout' => 120
        ));
        
        @unlink($video_file);
        if (is_wp_error($response)) return array('success' => false, 'message' => $response->get_error_message());
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['id'])) return array('success' => true, 'message' => 'Published successfully');
        return array('success' => false, 'message' => 'API Error: ' . json_encode($data));
    }
    
    private function get_youtube_access_token($settings) {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array('body' => array('client_id' => $settings['youtube_client_id'], 'client_secret' => $settings['youtube_client_secret'], 'refresh_token' => $settings['youtube_refresh_token'], 'grant_type' => 'refresh_token')));
        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['access_token'] ?? false;
    }
    
    public function ajax_test_connection() {
        // Placeholder for existing test functionality logic if needed
        wp_send_json(array('success' => false, 'message' => 'Not implemented in this version'));
    }

    /**
     * Get Admin CSS - Updated for Queue UI
     */
    private function get_admin_css() {
        return '
        .snn-socials-wrap { max-width: 1200px; }
        .snn-publish-container { display: flex; gap: 30px; margin-top: 20px; }
        .snn-publish-form { flex: 1; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .snn-sidebar { width: 350px; }
        .snn-info-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid #e5e7eb; }
        .snn-form-group { margin-bottom: 25px; }
        .snn-form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        textarea { width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
        
        /* Checkbox Buttons */
        .snn-platforms { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .snn-platform-option { display: block; border: 1px solid #ddd; padding: 15px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
        .snn-platform-option:hover { background: #f0f6fc; border-color: #2271b1; }
        .snn-platform-option:has(input:checked) { background: #f0f6fc; border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .platform-icon { display: inline-block; width: 24px; text-align: center; margin-right: 5px; font-weight: bold; }
        
        /* Media Preview */
        #snn-media-preview img, #snn-media-preview video { max-width: 100%; height: auto; margin-top: 10px; border-radius: 4px; }
        
        /* Queue System UI */
        .snn-process-list { margin-top: 15px; display: flex; flex-direction: column; gap: 8px; }
        .snn-queue-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease; }
        
        .snn-queue-item.pending { border-left: 4px solid #cbd5e1; color: #64748b; }
        
        .snn-queue-item.publishing { background: #eff6ff; border-color: #bfdbfe; border-left: 4px solid #3b82f6; color: #1e40af; }
        .snn-queue-item.publishing .status-icon::after { content: ""; display: inline-block; width: 12px; height: 12px; border: 2px solid #3b82f6; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite; }
        
        .snn-queue-item.success { background: #f0fdf4; border-color: #bbf7d0; border-left: 4px solid #22c55e; color: #166534; }
        
        .snn-queue-item.error { background: #fef2f2; border-color: #fecaca; border-left: 4px solid #ef4444; color: #991b1b; }
        
        .item-info { display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .item-status { font-size: 12px; opacity: 0.8; }
        
        .eta-badge { background: #e0f2fe; color: #0369a1; padding: 8px; border-radius: 6px; text-align: center; font-weight: bold; font-size: 13px; margin-bottom: 10px; border: 1px solid #bae6fd; }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Settings */
        .snn-api-settings-wrapper { margin-top: 30px; }
        .snn-settings-accordion summary { padding: 15px; background: #fff; cursor: pointer; border: 1px solid #ddd; border-radius: 6px; display: flex; align-items: center; gap: 8px; }
        .snn-settings-content { padding: 20px; background: #fff; border: 1px solid #ddd; border-top: none; }
        .snn-settings-section { margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #ddd; }
        ';
    }

    /**
     * JavaScript Queue Manager & Logic
     */
    private function get_admin_js() {
        return "
        jQuery(document).ready(function($) {
            
            // --- 1. MEDIA HANDLING ---
            var mediaFrame;
            $('#snn-select-media').on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title: 'Select Media',
                    button: { text: 'Use this media' },
                    multiple: false
                });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#snn-media-id').val(attachment.id);
                    $('#snn-media-url').val(attachment.url);
                    $('#snn-media-type').val(attachment.type);
                    var html = (attachment.type === 'video') ? '<video controls src=\"'+attachment.url+'\"></video>' : '<img src=\"'+attachment.url+'\">';
                    $('#snn-media-preview').html(html);
                    $('#snn-remove-media').show();
                });
                mediaFrame.open();
            });

            $('#snn-remove-media').on('click', function() {
                $('#snn-media-id, #snn-media-url, #snn-media-type').val('');
                $('#snn-media-preview').html('');
                $(this).hide();
            });

            $('#snn-post-text').on('input', function() {
                $('.char-count').text($(this).val().length + ' chars');
            });

            // --- 2. QUEUE MANAGER CLASS ---
            class PublishQueue {
                constructor(text, mediaUrl, mediaType, platforms) {
                    this.queue = platforms; // Array of objects {id, name, icon}
                    this.currentIndex = 0;
                    this.text = text;
                    this.mediaUrl = mediaUrl;
                    this.mediaType = mediaType;
                    this.startTime = Date.now();
                    this.successCount = 0;
                    this.failCount = 0;
                }

                initUI() {
                    $('#snn-process-queue').empty();
                    $('#snn-final-summary').hide();
                    
                    this.queue.forEach(p => {
                        var html = `
                            <div id='queue-item-${p.id}' class='snn-queue-item pending'>
                                <div class='item-info'>
                                    <span class='platform-icon'>${p.icon}</span> ${p.name}
                                </div>
                                <div class='item-status status-icon'>Pending</div>
                            </div>`;
                        $('#snn-process-queue').append(html);
                    });
                    
                    $('#snn-queue-container').fadeIn();
                    this.lockInterface(true);
                }

                lockInterface(locked) {
                    $('#snn-publish-btn').prop('disabled', locked).text(locked ? 'Publishing...' : 'Publish Again');
                    $('#snn-post-text, input[type=checkbox]').prop('disabled', locked);
                    if(locked) {
                        window.onbeforeunload = function() { return 'Publishing in progress. Are you sure you want to leave?'; };
                    } else {
                        window.onbeforeunload = null;
                    }
                }

                updateStatus(id, state, message) {
                    var $el = $('#queue-item-' + id);
                    $el.removeClass('pending publishing success error').addClass(state);
                    
                    var icon = '';
                    if(state === 'publishing') icon = 'Processing...';
                    if(state === 'success') icon = '✅ Published';
                    if(state === 'error') icon = '❌ Failed';
                    
                    $el.find('.item-status').text(message || icon);
                }

                updateETA() {
                    if (this.currentIndex === 0) return;
                    
                    var elapsed = (Date.now() - this.startTime) / 1000;
                    var avgTime = elapsed / this.currentIndex;
                    var remainingItems = this.queue.length - this.currentIndex;
                    var eta = Math.ceil(avgTime * remainingItems);
                    
                    $('#snn-eta-display').text(remainingItems > 0 ? `Est. time remaining: ${eta}s` : 'Finishing up...');
                }

                start() {
                    this.initUI();
                    this.processNext();
                }

                processNext() {
                    if (this.currentIndex >= this.queue.length) {
                        this.finish();
                        return;
                    }

                    var item = this.queue[this.currentIndex];
                    this.updateStatus(item.id, 'publishing');
                    
                    // Recursive AJAX Call
                    $.ajax({
                        url: snnSocials.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'snn_publish_single_step', // New abstracted endpoint
                            nonce: snnSocials.nonce,
                            platform: item.id,
                            text: this.text,
                            media_url: this.mediaUrl,
                            media_type: this.mediaType
                        },
                        success: (res) => {
                            if (res.success) {
                                this.updateStatus(item.id, 'success', 'Success');
                                this.successCount++;
                            } else {
                                this.updateStatus(item.id, 'error', res.message.substring(0, 30) + '...');
                                console.error(item.name + ' Error:', res);
                                this.failCount++;
                            }
                        },
                        error: (xhr) => {
                            this.updateStatus(item.id, 'error', 'Server Error');
                            this.failCount++;
                        },
                        complete: () => {
                            this.currentIndex++;
                            this.updateETA();
                            this.processNext(); // Continue regardless of success/fail
                        }
                    });
                }

                finish() {
                    this.lockInterface(false);
                    $('#snn-eta-display').text('Batch Complete');
                    
                    var summaryClass = (this.failCount === 0) ? 'notice-success' : 'notice-warning';
                    var summaryHtml = `
                        <div class='notice ${summaryClass} inline' style='padding:10px'>
                            <p><strong>Done!</strong> ${this.successCount} successful, ${this.failCount} failed.</p>
                        </div>`;
                        
                    $('#snn-final-summary').html(summaryHtml).fadeIn();
                }
            }

            // --- 3. EVENT HANDLER ---
            $('#snn-publish-btn').on('click', function() {
                var text = $('#snn-post-text').val().trim();
                var mediaUrl = $('#snn-media-url').val();
                var mediaType = $('#snn-media-type').val();
                
                // Collect platforms dynamically
                var platforms = [];
                $('input[name=\"platforms\"]:checked').each(function() {
                    platforms.push({
                        id: $(this).val(),
                        name: $(this).data('name'),
                        icon: $(this).data('icon')
                    });
                });

                if (platforms.length === 0) {
                    alert('Please select at least one platform.');
                    return;
                }

                if (!text && !mediaUrl) {
                    alert('Please provide text or media.');
                    return;
                }

                // Initialize the Queue Manager
                var manager = new PublishQueue(text, mediaUrl, mediaType, platforms);
                manager.start();
            });

        });
        ";
    }
}

new SNN_Socials();
?>