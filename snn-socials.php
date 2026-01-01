<?php    
/**
 * Plugin Name: SNN Socials
 * Plugin URI: https://sinanisler.com
 * Description: Publish images and videos to X (Twitter), LinkedIn, Instagram, and YouTube from your WordPress dashboard
 * Version: 1.1.3
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
        // Add to Settings menu
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

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Register and enqueue styles
        wp_register_style('snn-socials-admin', false);
        wp_enqueue_style('snn-socials-admin');
        wp_add_inline_style('snn-socials-admin', $this->get_admin_css());

        // Register and enqueue scripts with proper dependencies
        wp_register_script('snn-socials-admin', false, array('jquery', 'media-upload', 'media-views'), '1.0.1', true);
        wp_enqueue_script('snn-socials-admin');

        // Localize script BEFORE adding inline script
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
            <h1>🚀 SNN Socials - Publish to Social Media</h1>

            <div class="snn-publish-container">
                <div class="snn-publish-form">
                    <h2>Create Post</h2>

                    <div class="snn-form-group">
                        <label for="snn-post-text">Post Text / Caption <span class="char-count"></span></label><br>
                        <textarea id="snn-post-text" rows="8" maxlength="5000" placeholder="Write your post text here...&#10;&#10;Share your thoughts, ideas, or updates with your audience across multiple platforms."></textarea>
                        <p class="description">Supports up to 5000 characters</p>
                    </div>

                    <div class="snn-form-group">
                        <label>Media (Image or Video)</label>
                        <div class="media-upload-area">
                            <button type="button" class="button button-secondary" id="snn-select-media">
                                <span class="dashicons dashicons-cloud-upload"></span> Select Media
                            </button>
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
                            <span class="dashicons dashicons-megaphone"></span> Publish Now
                        </button>
                    </div>

                    <div id="snn-publish-progress" style="display:none;">
                        <div class="progress-bar-container">
                            <div class="progress-bar"></div>
                        </div>
                        <p class="progress-text">Initializing...</p>
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

            <!-- API Settings Section (Collapsible) -->
            <div class="snn-api-settings-wrapper">
                <details class="snn-settings-accordion">
                    <summary>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <strong>API Settings & Credentials</strong>
                        <span class="description">Click to expand and configure your social media API credentials</span>
                    </summary>

                    <div class="snn-settings-content">
                        <form method="post" action="">
                            <?php wp_nonce_field('snn_socials_settings_nonce'); ?>

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

                            <p class="submit">
                                <input type="submit" name="snn_save_settings" class="button button-primary" value="Save API Settings">
                            </p>
                        </form>

                        <!-- Help Section -->
                        <div class="snn-help-section">
                            <h3>📖 Setup Instructions</h3>
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

        .snn-socials-wrap > h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .snn-publish-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .snn-publish-form {
            flex: 1;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e3e8ee;
        }

        .snn-publish-form h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 22px;
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 12px;
        }

        .snn-sidebar {
            width: 300px;
        }

        .snn-info-box {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid #e3e8ee;
        }

        .snn-info-box h3 {
            margin-top: 0;
            font-size: 16px;
            color: #1d2327;
            margin-bottom: 15px;
        }

        .snn-info-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .snn-info-box li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .snn-form-group {
            margin-bottom: 28px;
        }

        .snn-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
            color: #1d2327;
        }

        .snn-form-group label .char-count {
            float: right;
            font-weight: normal;
            color: #666;
            font-size: 12px;
        }

        .snn-form-group textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #dfe4ea;
            border-radius: 8px;
            font-size: 15px;
            line-height: 1.6;
            resize: vertical;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            transition: all 0.3s ease;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .snn-form-group textarea:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1), 0 1px 3px rgba(0,0,0,0.05);
        }

        .snn-form-group .description {
            margin: 8px 0 0 0;
            color: #646970;
            font-size: 12px;
        }

        .media-upload-area {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        #snn-select-media {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
            font-weight: 500;
            padding: 8px 20px;
            height: auto;
        }

        #snn-select-media:hover {
            background: #135e96;
            border-color: #135e96;
            color: #fff;
        }

        #snn-select-media:focus {
            background: #135e96;
            border-color: #135e96;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.2);
        }

        #snn-remove-media {
            color: #d63638;
            text-decoration: none;
        }

        #snn-remove-media:hover {
            color: #d63638;
            text-decoration: underline;
        }

        #snn-select-media .dashicons,
        #snn-remove-media .dashicons,
        #snn-publish-btn .dashicons {
            line-height: inherit;
            vertical-align: middle;
            margin-right: 5px;
        }

        .snn-platforms {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .snn-platform-option {
            display: block;
            padding: 16px;
            border: 2px solid #dfe4ea;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #fff;
            position: relative;
        }

        .snn-platform-option:hover {
            border-color: #2271b1;
            background: #f0f6fc;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(34, 113, 177, 0.15);
        }

        .snn-platform-option input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }

        .snn-platform-option input[type="checkbox"]:checked ~ .platform-label {
            color: #2271b1;
            font-weight: 600;
        }

        .snn-platform-option:has(input[type="checkbox"]:checked) {
            border-color: #2271b1;
            background: #f0f6fc;
            box-shadow: 0 2px 6px rgba(34, 113, 177, 0.2);
        }

        .platform-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .platform-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            font-weight: bold;
            font-size: 16px;
            border-radius: 6px;
            background: #f0f0f1;
        }

        #snn-media-preview {
            margin-top: 15px;
            padding: 16px;
            background: #fff;
            border-radius: 8px;
            border: 2px dashed #dfe4ea;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        #snn-media-preview img,
        #snn-media-preview video {
            max-width: 100%;
            max-height: 400px;
            height: auto;
            border-radius: 6px;
            display: block;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        #snn-media-preview p {
            margin: 6px 0;
            font-size: 13px;
            color: #646970;
        }

        #snn-publish-btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            height: auto;
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            border: none;
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
            transition: all 0.3s ease;
        }

        #snn-publish-btn:hover {
            background: linear-gradient(135deg, #135e96 0%, #0d4a73 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(34, 113, 177, 0.4);
        }

        #snn-publish-btn:active {
            transform: translateY(0);
        }

        #snn-publish-btn:disabled {
            background: #9ca3af;
            box-shadow: none;
            transform: none;
            cursor: not-allowed;
        }

        .dashicons-spin {
            animation: dashicons-spin 1s linear infinite;
        }

        @keyframes dashicons-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Progress Bar */
        #snn-publish-progress {
            margin-top: 20px;
            padding: 24px;
            background: linear-gradient(135deg, #e8f4fd 0%, #d6ebf7 100%);
            border-radius: 10px;
            border: 2px solid #b8ddf1;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15);
        }

        .progress-bar-container {
            width: 100%;
            height: 32px;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 12px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #2271b1, #135e96, #0d4a73);
            background-size: 200% 100%;
            width: 0%;
            transition: width 0.4s ease;
            position: relative;
            overflow: hidden;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .progress-bar::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.4) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-text {
            margin: 0;
            text-align: center;
            font-weight: 600;
            color: #135e96;
            font-size: 14px;
        }

        #snn-publish-status {
            margin-top: 20px;
        }

        .snn-status-item {
            padding: 16px 18px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 5px solid #ddd;
            animation: slideIn 0.4s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            font-size: 14px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .snn-status-item.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
            color: #155724;
        }

        .snn-status-item.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
            border-left-color: #dc3545;
            color: #721c24;
            word-break: break-word;
        }

        .snn-status-item.loading {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        .snn-status-item strong {
            font-size: 15px;
        }

        .snn-status-item pre {
            background: rgba(0,0,0,0.08);
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 12px 0 0 0;
            font-size: 11px;
            line-height: 1.4;
        }

        /* API Settings Accordion */
        .snn-api-settings-wrapper {
            margin-top: 40px;
        }

        .snn-settings-accordion {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .snn-settings-accordion summary {
            padding: 20px 25px;
            cursor: pointer;
            background: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .snn-settings-accordion summary:hover {
            background: #f0f0f0;
        }

        .snn-settings-accordion summary::-webkit-details-marker {
            display: none;
        }

        .snn-settings-accordion summary .dashicons {
            color: #2271b1;
        }

        .snn-settings-accordion summary .description {
            margin-left: auto;
            font-size: 13px;
            color: #666;
            font-weight: normal;
        }

        .snn-settings-content {
            padding: 25px;
        }

        .snn-settings-section {
            background: #f9f9f9;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .snn-settings-section h2 {
            margin-top: 0;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }

        .snn-help-section {
            background: #f0f6fc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #c3d9ed;
            margin-top: 30px;
        }

        .snn-help-section h3 {
            margin-top: 0;
        }

        .snn-help-section details {
            margin-bottom: 15px;
            padding: 15px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .snn-help-section summary {
            cursor: pointer;
            font-weight: 600;
            color: #2271b1;
            list-style: none;
        }

        .snn-help-section summary::-webkit-details-marker {
            display: none;
        }

        .snn-help-section summary::before {
            content: "▶ ";
            display: inline-block;
            margin-right: 5px;
            transition: transform 0.3s;
        }

        .snn-help-section details[open] summary::before {
            transform: rotate(90deg);
        }

        .snn-help-section ol {
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .snn-publish-container {
                flex-direction: column;
            }

            .snn-sidebar {
                width: 100%;
            }

            .snn-platforms {
                grid-template-columns: 1fr;
            }
        }
        ';
    }
    
    /**
     * Get admin JavaScript
     */
    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            console.log("SNN Socials script loaded");
            console.log("wp.media available:", typeof wp.media !== "undefined");

            var mediaId = "";
            var mediaUrl = "";
            var mediaType = "";
            var mediaFrame;

            // Character counter for textarea
            function updateCharCount() {
                var text = $("#snn-post-text").val();
                var count = text.length;
                var maxLength = $("#snn-post-text").attr("maxlength");
                $(".char-count").text(count + " / " + maxLength + " characters");
            }

            $("#snn-post-text").on("input", updateCharCount);
            updateCharCount();

            // Media uploader - Fixed version
            $("#snn-select-media").on("click", function(e) {
                e.preventDefault();
                console.log("Select media button clicked");

                // Check if media frame exists
                if (typeof wp === "undefined" || typeof wp.media === "undefined") {
                    alert("WordPress media library not loaded. Please refresh the page.");
                    console.error("wp.media is not defined");
                    return;
                }

                // If the media frame already exists, reopen it
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                // Create the media frame
                mediaFrame = wp.media({
                    title: "Select or Upload Media",
                    button: {
                        text: "Use this media"
                    },
                    multiple: false,
                    library: {
                        type: ["image", "video"]
                    }
                });

                // When an image is selected, run a callback
                mediaFrame.on("select", function() {
                    console.log("Media selected");
                    var attachment = mediaFrame.state().get("selection").first().toJSON();
                    console.log("Attachment:", attachment);

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
                        preview = "<video width=\"100%\" controls><source src=\"" + attachment.url + "\" type=\"" + attachment.mime + "\"></video>";
                    }
                    preview += "<p><strong>File:</strong> " + (attachment.filename || "N/A") + "</p>";

                    var fileSize = attachment.filesizeInBytes || attachment.filesize || 0;
                    preview += "<p><strong>Type:</strong> " + (attachment.mime || "N/A") + " | <strong>Size:</strong> " + (fileSize / 1024 / 1024).toFixed(2) + " MB</p>";

                    $("#snn-media-preview").html(preview).slideDown();
                    $("#snn-remove-media").show();
                });

                // Finally, open the modal
                mediaFrame.open();
                console.log("Media frame opened");
            });

            // Remove media
            $("#snn-remove-media").on("click", function(e) {
                e.preventDefault();
                console.log("Remove media clicked");

                $("#snn-media-preview").slideUp(function() {
                    $(this).html("");
                });
                $("#snn-media-id, #snn-media-url, #snn-media-type").val("");
                $("#snn-remove-media").hide();
                mediaId = "";
                mediaUrl = "";
                mediaType = "";
            });

            // Publish button with enhanced progress tracking
            $("#snn-publish-btn").on("click", function() {
                var text = $("#snn-post-text").val().trim();
                var platforms = [];

                $("input[name=\"platforms[]\"]:checked").each(function() {
                    platforms.push($(this).val());
                });

                // Get updated media values
                mediaUrl = $("#snn-media-url").val();
                mediaType = $("#snn-media-type").val();

                // Validation
                if (!text && !mediaUrl) {
                    alert("⚠️ Please add some text or media before publishing.");
                    return;
                }

                if (platforms.length === 0) {
                    alert("⚠️ Please select at least one platform.");
                    return;
                }

                console.log("Publishing to:", platforms);
                console.log("Media URL:", mediaUrl);

                // Clear previous status and show progress
                $("#snn-publish-status").html("");
                $("#snn-publish-progress").slideDown();
                $("#snn-publish-btn").prop("disabled", true);

                // Update button text with icon
                var originalButtonHtml = $("#snn-publish-btn").html();
                $("#snn-publish-btn").html("<span class=\"dashicons dashicons-update dashicons-spin\"></span> Publishing...");

                // Initialize progress
                $(".progress-bar").css("width", "10%");
                $(".progress-text").text("Initializing...");

                var startTime = Date.now();

                // Simulate smooth progress
                var progressInterval = setInterval(function() {
                    var currentWidth = parseFloat($(".progress-bar").css("width")) / parseFloat($(".progress-bar").parent().css("width")) * 100;
                    if (currentWidth < 90) {
                        $(".progress-bar").css("width", (currentWidth + 5) + "%");
                    }
                    var elapsed = Math.floor((Date.now() - startTime) / 1000);
                    $(".progress-text").text("Publishing to " + platforms.join(", ").toUpperCase() + "... (" + elapsed + "s)");
                }, 500);

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
                        console.log("Response:", response);
                        clearInterval(progressInterval);

                        // Complete progress
                        $(".progress-bar").css("width", "100%");
                        $(".progress-text").html("✅ Complete!");

                        setTimeout(function() {
                            $("#snn-publish-progress").slideUp();

                            var html = "";
                            var allSuccess = true;
                            var successCount = 0;
                            var failCount = 0;

                            $.each(response, function(platform, result) {
                                if (result.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                    allSuccess = false;
                                }

                                var status = result.success ? "success" : "error";
                                var icon = result.success ? "✅" : "❌";
                                html += "<div class=\"snn-status-item " + status + "\">";
                                html += "<strong>" + icon + " " + platform.toUpperCase() + "</strong>";
                                html += "<div style=\"margin-top: 6px;\">" + result.message + "</div>";

                                // Show raw error for debugging if it exists
                                if (!result.success && result.message) {
                                    try {
                                        var errorObj = JSON.parse(result.message);
                                        html += "<pre>" + JSON.stringify(errorObj, null, 2) + "</pre>";
                                    } catch(e) {
                                        // Message is not JSON, just display it
                                    }
                                }

                                html += "</div>";
                            });

                            // Summary message
                            if (allSuccess) {
                                html = "<div class=\"snn-status-item success\"><strong>🎉 All posts published successfully!</strong><div style=\"margin-top: 6px;\">Published to " + successCount + " platform(s)</div></div>" + html;
                            } else if (successCount > 0) {
                                html = "<div class=\"snn-status-item loading\"><strong>⚠️ Partial success</strong><div style=\"margin-top: 6px;\">" + successCount + " succeeded, " + failCount + " failed</div></div>" + html;
                            } else {
                                html = "<div class=\"snn-status-item error\"><strong>❌ Publishing failed</strong><div style=\"margin-top: 6px;\">All " + failCount + " platform(s) failed</div></div>" + html;
                            }

                            $("#snn-publish-status").html(html).hide().slideDown();
                            $("#snn-publish-btn").prop("disabled", false).html(originalButtonHtml);

                            // Clear form if all successful
                            if (allSuccess) {
                                setTimeout(function() {
                                    $("#snn-post-text").val("");
                                    $("#snn-media-preview").slideUp(function() {
                                        $(this).html("");
                                    });
                                    $("#snn-media-id, #snn-media-url, #snn-media-type").val("");
                                    $("#snn-remove-media").hide();
                                    $("input[name=\"platforms[]\"]").prop("checked", false);
                                    mediaUrl = "";
                                    mediaType = "";
                                    mediaId = "";
                                    updateCharCount();

                                    // Auto-hide status after 8 seconds
                                    setTimeout(function() {
                                        $("#snn-publish-status").slideUp(function() {
                                            $(this).html("").show();
                                        });
                                    }, 8000);
                                }, 2000);
                            }
                        }, 800);
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", xhr, status, error);
                        clearInterval(progressInterval);
                        $("#snn-publish-progress").slideUp();

                        var errorMessage = "An error occurred. Please try again.";
                        if (xhr.responseText) {
                            try {
                                var errorData = JSON.parse(xhr.responseText);
                                errorMessage = errorData.message || errorMessage;
                            } catch(e) {
                                errorMessage = xhr.responseText.substring(0, 500);
                            }
                        }

                        $("#snn-publish-status").html(
                            "<div class=\"snn-status-item error\">" +
                            "<strong>❌ AJAX Error</strong>" +
                            "<div style=\"margin-top: 8px;\">" + errorMessage + "</div>" +
                            "<pre>Status: " + status + "\\nError: " + error + "</pre>" +
                            "</div>"
                        ).hide().slideDown();

                        $("#snn-publish-btn").prop("disabled", false).html(originalButtonHtml);
                    }
                });
            });
        });
        ';
    }
}

// Initialize plugin
new SNN_Socials();