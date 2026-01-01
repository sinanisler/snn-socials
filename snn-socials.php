<?php
/**
 * Plugin Name: SNN Socials
 * Plugin URI: https://sinanisler.com
 * Description: Publish images and videos to X (Twitter), LinkedIn, Instagram, and YouTube from your WordPress dashboard
 * Version: 1.0.0
 * Author: Sinan Isler
 * Author URI: https://sinanisler.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include GitHub updater
require_once plugin_dir_path(__FILE__) . 'github-update.php';

class SNN_Socials {
    
    private $option_name = 'snn_socials_settings';
    
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_snn_publish_post', array($this, 'ajax_publish_post'));
        add_action('wp_ajax_snn_test_connection', array($this, 'ajax_test_connection'));
        
        // OAuth callbacks
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'SNN Socials',
            'SNN Socials',
            'manage_options',
            'snn-socials',
            array($this, 'render_publish_page'),
            'dashicons-share',
            30
        );
        
        add_submenu_page(
            'snn-socials',
            'Publish',
            'Publish',
            'manage_options',
            'snn-socials',
            array($this, 'render_publish_page')
        );
        
        add_submenu_page(
            'snn-socials',
            'Settings',
            'Settings',
            'manage_options',
            'snn-socials-settings',
            array($this, 'render_settings_page')
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
        if (strpos($hook, 'snn-socials') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('snn-socials-admin', false);
        wp_add_inline_style('snn-socials-admin', $this->get_admin_css());
        
        wp_enqueue_script('snn-socials-admin', false, array('jquery'), '1.0', true);
        wp_add_inline_script('snn-socials-admin', $this->get_admin_js());
        
        wp_localize_script('snn-socials-admin', 'snnSocials', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('snn_socials_nonce')
        ));
    }
    
    /**
     * Render publish page
     */
    public function render_publish_page() {
        ?>
        <div class="wrap snn-socials-wrap">
            <h1>🚀 SNN Socials - Publish to Social Media</h1>
            
            <div class="snn-publish-container">
                <div class="snn-publish-form">
                    <h2>Create Post</h2>
                    
                    <div class="snn-form-group">
                        <label>Post Text / Caption</label>
                        <textarea id="snn-post-text" rows="6" placeholder="Write your post text here..."></textarea>
                    </div>
                    
                    <div class="snn-form-group">
                        <label>Media (Image or Video)</label>
                        <button type="button" class="button" id="snn-select-media">Select Media</button>
                        <div id="snn-media-preview"></div>
                        <input type="hidden" id="snn-media-id" value="">
                        <input type="hidden" id="snn-media-url" value="">
                        <input type="hidden" id="snn-media-type" value="">
                    </div>
                    
                    <div class="snn-form-group">
                        <label>Publish To:</label>
                        <div class="snn-platforms">
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms[]" value="x" id="platform-x">
                                <span class="platform-label">
                                    <span class="platform-icon">𝕏</span>
                                    X (Twitter)
                                </span>
                            </label>
                            
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms[]" value="linkedin" id="platform-linkedin">
                                <span class="platform-label">
                                    <span class="platform-icon">in</span>
                                    LinkedIn
                                </span>
                            </label>
                            
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms[]" value="instagram" id="platform-instagram">
                                <span class="platform-label">
                                    <span class="platform-icon">📷</span>
                                    Instagram
                                </span>
                            </label>
                            
                            <label class="snn-platform-option">
                                <input type="checkbox" name="platforms[]" value="youtube" id="platform-youtube">
                                <span class="platform-label">
                                    <span class="platform-icon">▶</span>
                                    YouTube
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="snn-form-group">
                        <button type="button" class="button button-primary button-large" id="snn-publish-btn">
                            Publish Now
                        </button>
                    </div>
                    
                    <div id="snn-publish-status"></div>
                </div>
                
                <div class="snn-sidebar">
                    <div class="snn-info-box">
                        <h3>📊 Quick Stats</h3>
                        <p><strong>Monthly Limit:</strong> ~15 posts</p>
                        <p><strong>Rate Limits:</strong></p>
                        <ul>
                            <li>X: 500 posts/month (Free)</li>
                            <li>LinkedIn: No strict limit</li>
                            <li>Instagram: 25 posts/day</li>
                            <li>YouTube: 10,000 quota units/day</li>
                        </ul>
                    </div>
                    
                    <div class="snn-info-box">
                        <h3>💡 Tips</h3>
                        <ul>
                            <li>Images: JPEG/PNG format</li>
                            <li>Videos: MP4 recommended</li>
                            <li>Instagram: Square ratio best</li>
                            <li>LinkedIn: Professional content</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
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
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $settings = get_option($this->option_name, array());
        ?>
        <div class="wrap snn-socials-wrap">
            <h1>⚙️ SNN Socials Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('snn_socials_settings_nonce'); ?>
                
                <div class="snn-settings-container">
                    
                    <!-- X (Twitter) Settings -->
                    <div class="snn-settings-section">
                        <h2>𝕏 X (Twitter) API Settings</h2>
                        <p class="description">
                            Get your API credentials from <a href="https://developer.x.com/en/portal/dashboard" target="_blank">X Developer Portal</a>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label>API Key (Consumer Key)</label></th>
                                <td><input type="text" name="x_api_key" value="<?php echo esc_attr($settings['x_api_key'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>API Secret (Consumer Secret)</label></th>
                                <td><input type="password" name="x_api_secret" value="<?php echo esc_attr($settings['x_api_secret'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Access Token</label></th>
                                <td><input type="text" name="x_access_token" value="<?php echo esc_attr($settings['x_access_token'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Access Token Secret</label></th>
                                <td><input type="password" name="x_access_secret" value="<?php echo esc_attr($settings['x_access_secret'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- LinkedIn Settings -->
                    <div class="snn-settings-section">
                        <h2>🔗 LinkedIn API Settings</h2>
                        <p class="description">
                            Create an app at <a href="https://www.linkedin.com/developers/" target="_blank">LinkedIn Developers</a>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label>Client ID</label></th>
                                <td><input type="text" name="linkedin_client_id" value="<?php echo esc_attr($settings['linkedin_client_id'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Client Secret</label></th>
                                <td><input type="password" name="linkedin_client_secret" value="<?php echo esc_attr($settings['linkedin_client_secret'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Access Token</label></th>
                                <td><input type="text" name="linkedin_access_token" value="<?php echo esc_attr($settings['linkedin_access_token'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Organization ID (for company page)</label></th>
                                <td>
                                    <input type="text" name="linkedin_org_id" value="<?php echo esc_attr($settings['linkedin_org_id'] ?? ''); ?>" class="regular-text">
                                    <p class="description">Leave empty to post to personal profile</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Instagram Settings -->
                    <div class="snn-settings-section">
                        <h2>📷 Instagram API Settings</h2>
                        <p class="description">
                            Get token from <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> (Instagram Graph API)
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label>Access Token</label></th>
                                <td><input type="text" name="instagram_access_token" value="<?php echo esc_attr($settings['instagram_access_token'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Instagram Business Account ID</label></th>
                                <td><input type="text" name="instagram_business_account_id" value="<?php echo esc_attr($settings['instagram_business_account_id'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- YouTube Settings -->
                    <div class="snn-settings-section">
                        <h2>▶ YouTube API Settings</h2>
                        <p class="description">
                            Create OAuth credentials at <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label>Client ID</label></th>
                                <td><input type="text" name="youtube_client_id" value="<?php echo esc_attr($settings['youtube_client_id'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Client Secret</label></th>
                                <td><input type="password" name="youtube_client_secret" value="<?php echo esc_attr($settings['youtube_client_secret'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Refresh Token</label></th>
                                <td><input type="text" name="youtube_refresh_token" value="<?php echo esc_attr($settings['youtube_refresh_token'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                    
                </div>
                
                <p class="submit">
                    <input type="submit" name="snn_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <div class="snn-help-section">
                <h2>📖 Setup Instructions</h2>
                <details>
                    <summary><strong>X (Twitter) Setup</strong></summary>
                    <ol>
                        <li>Go to <a href="https://developer.x.com/en/portal/dashboard" target="_blank">X Developer Portal</a></li>
                        <li>Create a new app or select existing</li>
                        <li>Set app permissions to "Read and Write"</li>
                        <li>Generate API Keys and Access Tokens</li>
                        <li>Copy all credentials to the fields above</li>
                    </ol>
                </details>
                
                <details>
                    <summary><strong>LinkedIn Setup</strong></summary>
                    <ol>
                        <li>Go to <a href="https://www.linkedin.com/developers/" target="_blank">LinkedIn Developers</a></li>
                        <li>Create a new app</li>
                        <li>Add products: "Share on LinkedIn" and "Sign In with LinkedIn"</li>
                        <li>Under Auth tab, get your Client ID and Secret</li>
                        <li>Use OAuth 2.0 flow to get access token (can use Postman or similar)</li>
                        <li>For company page: Find your organization ID from company page URL</li>
                    </ol>
                </details>
                
                <details>
                    <summary><strong>Instagram Setup</strong></summary>
                    <ol>
                        <li>Convert your Instagram to a Business account</li>
                        <li>Connect it to a Facebook Page</li>
                        <li>Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
                        <li>Create an app and add Instagram Graph API product</li>
                        <li>Get a long-lived access token</li>
                        <li>Get your Instagram Business Account ID</li>
                    </ol>
                </details>
                
                <details>
                    <summary><strong>YouTube Setup</strong></summary>
                    <ol>
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project</li>
                        <li>Enable YouTube Data API v3</li>
                        <li>Create OAuth 2.0 credentials</li>
                        <li>Use OAuth playground to get refresh token</li>
                        <li>Scope needed: https://www.googleapis.com/auth/youtube.upload</li>
                    </ol>
                </details>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        // Placeholder for OAuth callback handling
        // Can be extended based on specific OAuth flows
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('snn_socials_nonce', 'nonce');
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $result = array('success' => false, 'message' => 'Platform not supported');
        
        switch ($platform) {
            case 'x':
                $result = $this->test_x_connection();
                break;
            case 'linkedin':
                $result = $this->test_linkedin_connection();
                break;
            case 'instagram':
                $result = $this->test_instagram_connection();
                break;
            case 'youtube':
                $result = $this->test_youtube_connection();
                break;
        }
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Publish post
     */
    public function ajax_publish_post() {
        check_ajax_referer('snn_socials_nonce', 'nonce');
        
        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $media_url = esc_url_raw($_POST['media_url'] ?? '');
        $media_type = sanitize_text_field($_POST['media_type'] ?? '');
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());
        
        $results = array();
        
        foreach ($platforms as $platform) {
            switch ($platform) {
                case 'x':
                    $results['x'] = $this->publish_to_x($text, $media_url, $media_type);
                    break;
                case 'linkedin':
                    $results['linkedin'] = $this->publish_to_linkedin($text, $media_url, $media_type);
                    break;
                case 'instagram':
                    $results['instagram'] = $this->publish_to_instagram($text, $media_url, $media_type);
                    break;
                case 'youtube':
                    $results['youtube'] = $this->publish_to_youtube($text, $media_url, $media_type);
                    break;
            }
        }
        
        wp_send_json($results);
    }
    
    /**
     * Publish to X (Twitter)
     */
    private function publish_to_x($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings['x_api_key']) || empty($settings['x_access_token'])) {
            return array('success' => false, 'message' => 'X API credentials not configured');
        }
        
        try {
            $media_id = null;
            
            // Upload media if provided
            if (!empty($media_url)) {
                $media_id = $this->upload_media_to_x($media_url, $settings);
                if (!$media_id) {
                    return array('success' => false, 'message' => 'Failed to upload media to X');
                }
            }
            
            // Create tweet
            $tweet_data = array('text' => $text);
            if ($media_id) {
                $tweet_data['media'] = array('media_ids' => array($media_id));
            }
            
            $response = $this->make_x_request(
                'POST',
                'https://api.x.com/2/tweets',
                $tweet_data,
                $settings
            );
            
            if (isset($response['data']['id'])) {
                return array(
                    'success' => true,
                    'message' => 'Published to X successfully',
                    'id' => $response['data']['id']
                );
            }
            
            return array(
                'success' => false,
                'message' => 'Failed to publish to X: ' . json_encode($response)
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'X Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload media to X
     */
    private function upload_media_to_x($media_url, $settings) {
        // Download media file
        $media_file = download_url($media_url);
        if (is_wp_error($media_file)) {
            return false;
        }
        
        // Upload to X (using v1.1 endpoint)
        $boundary = wp_generate_password(24, false);
        $body = '';
        
        $file_contents = file_get_contents($media_file);
        $file_name = basename($media_url);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media\"; filename=\"{$file_name}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $oauth_params = $this->generate_x_oauth_params('POST', 'https://upload.x.com/1.1/media/upload.json', array(), $settings);
        
        $response = wp_remote_post('https://upload.x.com/1.1/media/upload.json', array(
            'headers' => array(
                'Authorization' => 'OAuth ' . $this->build_oauth_header($oauth_params),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary
            ),
            'body' => $body,
            'timeout' => 60
        ));
        
        @unlink($media_file);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['media_id_string'] ?? false;
    }
    
    /**
     * Make X API request
     */
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
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Generate OAuth parameters for X
     */
    private function generate_x_oauth_params($method, $url, $params, $settings) {
        $oauth_params = array(
            'oauth_consumer_key' => $settings['x_api_key'],
            'oauth_nonce' => md5(microtime() . mt_rand()),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $settings['x_access_token'],
            'oauth_version' => '1.0'
        );
        
        $oauth_params['oauth_signature'] = $this->generate_x_signature(
            $method,
            $url,
            array_merge($oauth_params, $params),
            $settings['x_api_secret'],
            $settings['x_access_secret']
        );
        
        return $oauth_params;
    }
    
    /**
     * Generate OAuth signature for X
     */
    private function generate_x_signature($method, $url, $params, $consumer_secret, $token_secret) {
        ksort($params);
        
        $param_string = '';
        foreach ($params as $key => $value) {
            $param_string .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        }
        $param_string = rtrim($param_string, '&');
        
        $base_string = $method . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);
        $signing_key = rawurlencode($consumer_secret) . '&' . rawurlencode($token_secret);
        
        return base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));
    }
    
    /**
     * Build OAuth header
     */
    private function build_oauth_header($oauth_params) {
        $header_parts = array();
        foreach ($oauth_params as $key => $value) {
            $header_parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        return implode(', ', $header_parts);
    }
    
    /**
     * Publish to LinkedIn
     */
    private function publish_to_linkedin($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings['linkedin_access_token'])) {
            return array('success' => false, 'message' => 'LinkedIn access token not configured');
        }
        
        try {
            // Determine author URN (organization or person)
            $author = !empty($settings['linkedin_org_id']) 
                ? 'urn:li:organization:' . $settings['linkedin_org_id']
                : $this->get_linkedin_person_urn($settings['linkedin_access_token']);
            
            if (!$author) {
                return array('success' => false, 'message' => 'Could not determine LinkedIn author');
            }
            
            $post_data = array(
                'author' => $author,
                'commentary' => $text,
                'visibility' => 'PUBLIC',
                'distribution' => array(
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => array(),
                    'thirdPartyDistributionChannels' => array()
                ),
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false
            );
            
            // Upload and attach media if provided
            if (!empty($media_url)) {
                $media_urn = $this->upload_media_to_linkedin($media_url, $media_type, $author, $settings);
                if ($media_urn) {
                    $post_data['content'] = array(
                        'media' => array(
                            'title' => 'Media',
                            'id' => $media_urn
                        )
                    );
                }
            }
            
            $response = wp_remote_post('https://api.linkedin.com/rest/posts', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['linkedin_access_token'],
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'LinkedIn-Version' => date('Ym')
                ),
                'body' => json_encode($post_data),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 201) {
                return array('success' => true, 'message' => 'Published to LinkedIn successfully');
            }
            
            return array(
                'success' => false,
                'message' => 'LinkedIn error: ' . wp_remote_retrieve_body($response)
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'LinkedIn Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get LinkedIn person URN
     */
    private function get_linkedin_person_urn($access_token) {
        $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return isset($data['sub']) ? 'urn:li:person:' . $data['sub'] : false;
    }
    
    /**
     * Upload media to LinkedIn
     */
    private function upload_media_to_linkedin($media_url, $media_type, $author, $settings) {
        // Determine recipe based on media type
        $recipe = strpos($media_type, 'video') !== false 
            ? 'urn:li:digitalmediaRecipe:feedshare-video'
            : 'urn:li:digitalmediaRecipe:feedshare-image';
        
        // Register upload
        $register_data = array(
            'registerUploadRequest' => array(
                'owner' => $author,
                'recipes' => array($recipe),
                'serviceRelationships' => array(
                    array(
                        'identifier' => 'urn:li:userGeneratedContent',
                        'relationshipType' => 'OWNER'
                    )
                ),
                'supportedUploadMechanism' => array('SYNCHRONOUS_UPLOAD')
            )
        );
        
        $response = wp_remote_post('https://api.linkedin.com/rest/assets?action=registerUpload', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['linkedin_access_token'],
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => date('Ym')
            ),
            'body' => json_encode($register_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $register_result = json_decode(wp_remote_retrieve_body($response), true);
        $upload_url = $register_result['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $asset_urn = $register_result['value']['asset'] ?? null;
        
        if (!$upload_url || !$asset_urn) {
            return false;
        }
        
        // Download media
        $media_file = download_url($media_url);
        if (is_wp_error($media_file)) {
            return false;
        }
        
        // Upload media
        $upload_response = wp_remote_post($upload_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['linkedin_access_token']
            ),
            'body' => file_get_contents($media_file),
            'timeout' => 60
        ));
        
        @unlink($media_file);
        
        if (is_wp_error($upload_response)) {
            return false;
        }
        
        return $asset_urn;
    }
    
    /**
     * Publish to Instagram
     */
    private function publish_to_instagram($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings['instagram_access_token']) || empty($settings['instagram_business_account_id'])) {
            return array('success' => false, 'message' => 'Instagram credentials not configured');
        }
        
        try {
            $ig_user_id = $settings['instagram_business_account_id'];
            $access_token = $settings['instagram_access_token'];
            
            // Step 1: Create media container
            $container_data = array(
                'caption' => $text,
                'access_token' => $access_token
            );
            
            if (strpos($media_type, 'video') !== false) {
                $container_data['media_type'] = 'VIDEO';
                $container_data['video_url'] = $media_url;
            } else {
                $container_data['image_url'] = $media_url;
            }
            
            $create_url = "https://graph.facebook.com/v18.0/{$ig_user_id}/media?" . http_build_query($container_data);
            $create_response = wp_remote_post($create_url, array('timeout' => 30));
            
            if (is_wp_error($create_response)) {
                return array('success' => false, 'message' => $create_response->get_error_message());
            }
            
            $create_result = json_decode(wp_remote_retrieve_body($create_response), true);
            $container_id = $create_result['id'] ?? null;
            
            if (!$container_id) {
                return array('success' => false, 'message' => 'Failed to create Instagram media container');
            }
            
            // Wait for processing (especially for videos)
            sleep(3);
            
            // Step 2: Publish the container
            $publish_data = array(
                'creation_id' => $container_id,
                'access_token' => $access_token
            );
            
            $publish_url = "https://graph.facebook.com/v18.0/{$ig_user_id}/media_publish?" . http_build_query($publish_data);
            $publish_response = wp_remote_post($publish_url, array('timeout' => 30));
            
            if (is_wp_error($publish_response)) {
                return array('success' => false, 'message' => $publish_response->get_error_message());
            }
            
            $publish_result = json_decode(wp_remote_retrieve_body($publish_response), true);
            
            if (isset($publish_result['id'])) {
                return array(
                    'success' => true,
                    'message' => 'Published to Instagram successfully',
                    'id' => $publish_result['id']
                );
            }
            
            return array(
                'success' => false,
                'message' => 'Instagram error: ' . json_encode($publish_result)
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Instagram Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Publish to YouTube
     */
    private function publish_to_youtube($text, $media_url, $media_type) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings['youtube_client_id']) || empty($settings['youtube_refresh_token'])) {
            return array('success' => false, 'message' => 'YouTube credentials not configured');
        }
        
        // Only videos can be uploaded to YouTube
        if (strpos($media_type, 'video') === false) {
            return array('success' => false, 'message' => 'YouTube only accepts videos');
        }
        
        try {
            // Get access token from refresh token
            $access_token = $this->get_youtube_access_token($settings);
            if (!$access_token) {
                return array('success' => false, 'message' => 'Failed to get YouTube access token');
            }
            
            // Download video
            $video_file = download_url($media_url);
            if (is_wp_error($video_file)) {
                return array('success' => false, 'message' => 'Failed to download video');
            }
            
            // Prepare metadata
            $metadata = array(
                'snippet' => array(
                    'title' => substr($text, 0, 100) ?: 'Video from SNN Socials',
                    'description' => $text,
                    'categoryId' => '22' // People & Blogs
                ),
                'status' => array(
                    'privacyStatus' => 'public'
                )
            );
            
            // Upload video using resumable upload
            $boundary = wp_generate_password(24, false);
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
            $body .= json_encode($metadata) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: video/mp4\r\n\r\n";
            $body .= file_get_contents($video_file) . "\r\n";
            $body .= "--{$boundary}--\r\n";
            
            $response = wp_remote_post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'multipart/related; boundary=' . $boundary
                ),
                'body' => $body,
                'timeout' => 120
            ));
            
            @unlink($video_file);
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $result = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($result['id'])) {
                return array(
                    'success' => true,
                    'message' => 'Published to YouTube successfully',
                    'id' => $result['id']
                );
            }
            
            return array(
                'success' => false,
                'message' => 'YouTube error: ' . json_encode($result)
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'YouTube Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get YouTube access token from refresh token
     */
    private function get_youtube_access_token($settings) {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $settings['youtube_client_id'],
                'client_secret' => $settings['youtube_client_secret'],
                'refresh_token' => $settings['youtube_refresh_token'],
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['access_token'] ?? false;
    }
    
    /**
     * Test X connection
     */
    private function test_x_connection() {
        $settings = get_option($this->option_name, array());
        
        try {
            $response = $this->make_x_request('GET', 'https://api.x.com/2/users/me', array(), $settings);
            return array('success' => true, 'message' => 'X connection successful');
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Test LinkedIn connection
     */
    private function test_linkedin_connection() {
        $settings = get_option($this->option_name, array());
        
        $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['linkedin_access_token']
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $status = wp_remote_retrieve_response_code($response);
        if ($status === 200) {
            return array('success' => true, 'message' => 'LinkedIn connection successful');
        }
        
        return array('success' => false, 'message' => 'LinkedIn connection failed');
    }
    
    /**
     * Test Instagram connection
     */
    private function test_instagram_connection() {
        $settings = get_option($this->option_name, array());
        
        $url = 'https://graph.facebook.com/v18.0/me?access_token=' . $settings['instagram_access_token'];
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['id'])) {
            return array('success' => true, 'message' => 'Instagram connection successful');
        }
        
        return array('success' => false, 'message' => 'Instagram connection failed');
    }
    
    /**
     * Test YouTube connection
     */
    private function test_youtube_connection() {
        $settings = get_option($this->option_name, array());
        
        $access_token = $this->get_youtube_access_token($settings);
        if (!$access_token) {
            return array('success' => false, 'message' => 'Failed to get access token');
        }
        
        return array('success' => true, 'message' => 'YouTube connection successful');
    }
    
    /**
     * Get admin CSS
     */
    private function get_admin_css() {
        return '
        .snn-socials-wrap {
            max-width: 1400px;
        }
        
        .snn-publish-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .snn-publish-form {
            flex: 1;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .snn-sidebar {
            width: 300px;
        }
        
        .snn-info-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .snn-info-box h3 {
            margin-top: 0;
            font-size: 16px;
        }
        
        .snn-info-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .snn-form-group {
            margin-bottom: 25px;
        }
        
        .snn-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .snn-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
        }
        
        .snn-platforms {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .snn-platform-option {
            display: block;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .snn-platform-option:hover {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        
        .snn-platform-option input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .snn-platform-option input[type="checkbox"]:checked ~ .platform-label {
            color: #2271b1;
            font-weight: 600;
        }
        
        .platform-icon {
            display: inline-block;
            width: 24px;
            text-align: center;
            font-weight: bold;
        }
        
        #snn-media-preview {
            margin-top: 15px;
        }
        
        #snn-media-preview img {
            max-width: 300px;
            height: auto;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        #snn-publish-status {
            margin-top: 20px;
        }
        
        .snn-status-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 4px solid #ddd;
        }
        
        .snn-status-item.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .snn-status-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .snn-status-item.loading {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        
        .snn-settings-container {
            max-width: 900px;
        }
        
        .snn-settings-section {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .snn-settings-section h2 {
            margin-top: 0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .snn-help-section {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        .snn-help-section details {
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .snn-help-section summary {
            cursor: pointer;
            font-weight: 600;
            color: #2271b1;
        }
        
        .snn-help-section ol {
            margin-top: 10px;
        }
        ';
    }
    
    /**
     * Get admin JavaScript
     */
    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            var mediaId = "";
            var mediaUrl = "";
            var mediaType = "";
            
            // Media uploader
            $("#snn-select-media").on("click", function(e) {
                e.preventDefault();
                
                var frame = wp.media({
                    title: "Select Media",
                    button: { text: "Use this media" },
                    multiple: false
                });
                
                frame.on("select", function() {
                    var attachment = frame.state().get("selection").first().toJSON();
                    mediaId = attachment.id;
                    mediaUrl = attachment.url;
                    mediaType = attachment.type;
                    
                    $("#snn-media-id").val(mediaId);
                    $("#snn-media-url").val(mediaUrl);
                    $("#snn-media-type").val(mediaType);
                    
                    var preview = "";
                    if (attachment.type === "image") {
                        preview = "<img src=\"" + attachment.url + "\" alt=\"Preview\">";
                    } else if (attachment.type === "video") {
                        preview = "<video width=\"300\" controls><source src=\"" + attachment.url + "\"></video>";
                    }
                    preview += "<p><strong>File:</strong> " + attachment.filename + "</p>";
                    
                    $("#snn-media-preview").html(preview);
                });
                
                frame.open();
            });
            
            // Publish button
            $("#snn-publish-btn").on("click", function() {
                var text = $("#snn-post-text").val();
                var platforms = [];
                
                $("input[name=\"platforms[]\"]:checked").each(function() {
                    platforms.push($(this).val());
                });
                
                if (!text && !mediaUrl) {
                    alert("Please add some text or media before publishing.");
                    return;
                }
                
                if (platforms.length === 0) {
                    alert("Please select at least one platform.");
                    return;
                }
                
                $("#snn-publish-status").html("<div class=\"snn-status-item loading\">⏳ Publishing...</div>");
                $("#snn-publish-btn").prop("disabled", true);
                
                $.ajax({
                    url: snnSocials.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "snn_publish_post",
                        nonce: snnSocials.nonce,
                        text: text,
                        media_url: mediaUrl,
                        media_type: mediaType,
                        platforms: platforms
                    },
                    success: function(response) {
                        var html = "";
                        
                        $.each(response, function(platform, result) {
                            var status = result.success ? "success" : "error";
                            var icon = result.success ? "✅" : "❌";
                            html += "<div class=\"snn-status-item " + status + "\">";
                            html += icon + " <strong>" + platform.toUpperCase() + ":</strong> " + result.message;
                            html += "</div>";
                        });
                        
                        $("#snn-publish-status").html(html);
                        $("#snn-publish-btn").prop("disabled", false);
                        
                        // Clear form if all successful
                        var allSuccess = true;
                        $.each(response, function(platform, result) {
                            if (!result.success) allSuccess = false;
                        });
                        
                        if (allSuccess) {
                            setTimeout(function() {
                                $("#snn-post-text").val("");
                                $("#snn-media-preview").html("");
                                $("#snn-media-id, #snn-media-url, #snn-media-type").val("");
                                mediaUrl = "";
                                mediaType = "";
                            }, 3000);
                        }
                    },
                    error: function() {
                        $("#snn-publish-status").html("<div class=\"snn-status-item error\">❌ An error occurred. Please try again.</div>");
                        $("#snn-publish-btn").prop("disabled", false);
                    }
                });
            });
        });
        ';
    }
}

// Initialize plugin
new SNN_Socials();