<?php
/**
 * Plugin Name: Telebridge Auto Categorization
 * Plugin URI: https://readystudio.ir
 * Description: ماژول خودکار تشخیص نوع پرامپت و انتخاب سایت‌های مناسب برای Telebridge Pro
 * Version: 2.0.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: telebridge-auto-cat
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires PHP: 7.4
 * Requires WP: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'TELEBRIDGE_AUTO_CAT_VERSION', '2.0.0' );
define( 'TELEBRIDGE_AUTO_CAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'TELEBRIDGE_AUTO_CAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Telebridge_Auto_Categorization {

    private static $instance = null;
    private $gapgpt_api = 'https://api.gapgpt.app/v1/chat/completions';
    private $debug_mode = false;

    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Setup hooks
     */
    public function __construct() {
        // Only load if required plugins exist
        add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );

        // Enable debug mode
        $this->debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

    /**
     * On plugins loaded - Check dependencies
     */
    public function on_plugins_loaded() {
        // Check if Telebridge Pro is active
        if ( ! $this->is_telebridge_pro_active() ) {
            add_action( 'admin_notices', [ $this, 'show_telebridge_missing_notice' ] );
            return;
        }

        // Check if AI Sites Manager is active
        if ( ! $this->is_ai_sites_manager_active() ) {
            add_action( 'admin_notices', [ $this, 'show_ai_sites_missing_notice' ] );
            return;
        }

        // Hook into Telebridge product creation
        add_action( 'rti_product_created', [ $this, 'auto_assign_ai_sites' ], 10, 4 );

        // Add admin notice on success
        add_action( 'admin_notices', [ $this, 'show_plugin_active_notice' ] );

        $this->log( 'Telebridge Auto Categorization loaded', 'success' );
    }

    /**
     * Check if Telebridge Pro is active
     */
    private function is_telebridge_pro_active() {
        return class_exists( 'Telebridge_Pro' );
    }

    /**
     * Check if AI Sites Manager Ultimate is active
     */
    private function is_ai_sites_manager_active() {
        return class_exists( 'AI_Sites_Manager_Ultimate' );
    }

    /**
     * Show notice if Telebridge Pro is missing
     */
    public function show_telebridge_missing_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e( 'Telebridge Auto Categorization:', 'telebridge-auto-cat' ); ?></strong>
                <?php _e( 'نیاز به Telebridge Pro دارد. لطفاً آن را فعال کنید.', 'telebridge-auto-cat' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show notice if AI Sites Manager is missing
     */
    public function show_ai_sites_missing_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e( 'Telebridge Auto Categorization:', 'telebridge-auto-cat' ); ?></strong>
                <?php _e( 'نیاز به AI Sites Manager Ultimate دارد. لطفاً آن را فعال کنید.', 'telebridge-auto-cat' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show plugin active notice
     */
    public function show_plugin_active_notice() {
        // Only show on Telebridge Pro settings page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'telebridge-pro' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>✅ <?php _e( 'Telebridge Auto Categorization:', 'telebridge-auto-cat' ); ?></strong>
                <?php _e( 'فعال شده. محصولات جدید به صورت خودکار دسته‌بندی می‌شوند.', 'telebridge-auto-cat' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Main function: Auto-assign AI Sites to product
     * 
     * Called by: do_action('rti_product_created', $product_id, $raw_prompt, $ai_key, $model)
     * 
     * @param int $product_id - WooCommerce product ID
     * @param string $raw_prompt - Original Telegram caption
     * @param string $ai_key - GapGPT API key
     * @param string $model - AI model (gpt-4o-mini)
     */
    public function auto_assign_ai_sites( $product_id, $raw_prompt, $ai_key, $model ) {
        
        // Step 0: Validate inputs
        if ( ! $this->validate_inputs( $product_id, $raw_prompt, $ai_key ) ) {
            return;
        }

        $this->log( '🚀 Starting auto-categorization for product ' . $product_id, 'info' );

        // Step 1: Get AI Sites Manager instance
        if ( ! class_exists( 'AI_Sites_Manager_Ultimate' ) ) {
            $this->log( 'AI_Sites_Manager_Ultimate not found', 'warning' );
            return;
        }

        try {
            $ai_manager = AI_Sites_Manager_Ultimate::get_instance();
            $all_ai_sites = $ai_manager->get_reference_list();
        } catch ( Exception $e ) {
            $this->log( 'Failed to get AI Sites: ' . $e->getMessage(), 'error' );
            return;
        }

        if ( empty( $all_ai_sites ) ) {
            $this->log( 'No AI sites found', 'warning' );
            return;
        }

        $this->log( '📚 Found ' . count( $all_ai_sites ) . ' total AI sites', 'info' );

        // Step 2: Detect prompt type
        $detected_type = $this->detect_prompt_type( $raw_prompt, $ai_key, $model );
        
        if ( ! $detected_type ) {
            $this->log( 'Could not detect type, using fallback', 'warning' );
            $detected_type = 'tool';
        }

        $this->log( '🎯 Detected type: ' . $detected_type, 'success' );

        // Step 3: Filter sites by type
        $relevant_sites = $this->filter_sites_by_type( $all_ai_sites, $detected_type );

        if ( empty( $relevant_sites ) ) {
            $this->log( 'No relevant sites for type ' . $detected_type, 'warning' );
            return;
        }

        $this->log( '🔍 Found ' . count( $relevant_sites ) . ' relevant sites', 'info' );

        // Step 4: Score sites with AI
        $scored_sites = $this->score_sites_with_ai( $raw_prompt, $relevant_sites, $ai_key, $model );

        if ( empty( $scored_sites ) ) {
            $this->log( 'Failed to score sites', 'error' );
            return;
        }

        // Step 5: Select top N sites
        $max_sites = apply_filters( 'telebridge_auto_cat_max_sites', 4 );
        $selected_sites = array_slice( $scored_sites, 0, $max_sites, true );

        // Step 6: Save to product meta
        $this->save_selected_sites( $product_id, $selected_sites );

        $this->log( '✅ Assigned ' . count( $selected_sites ) . ' sites to product ' . $product_id, 'success' );
    }

    /**
     * Validate input parameters
     */
    private function validate_inputs( $product_id, $raw_prompt, $ai_key ) {
        if ( ! $product_id ) {
            $this->log( 'Invalid product ID', 'error' );
            return false;
        }

        if ( empty( $raw_prompt ) ) {
            $this->log( 'Empty prompt', 'error' );
            return false;
        }

        if ( empty( $ai_key ) ) {
            $this->log( 'Missing GapGPT API key', 'error' );
            return false;
        }

        return true;
    }

    /**
     * Detect prompt type
     * Returns: 'image', 'video', 'text', 'audio', 'code', 'tool'
     */
    private function detect_prompt_type( $prompt, $api_key, $model ) {
        
        $system_msg = "You are an expert AI prompt analyzer.

Analyze this prompt and determine its PRIMARY type:
- 'image': For image/visual generation (Midjourney, DALL-E, etc.)
- 'video': For video/animation generation (Runway, Pika, etc.)
- 'audio': For music/audio generation (Suno, Udio, etc.)
- 'text': For text generation/chat (ChatGPT, Claude, etc.)
- 'code': For code generation (GitHub Copilot, Cursor, etc.)
- 'tool': For productivity tools (Notion, Gamma, etc.)

Return ONLY the type as ONE WORD in lowercase.
Example: 'image' or 'video'";

        $response = $this->call_gapgpt_api(
            $system_msg,
            substr( $prompt, 0, 1000 ),
            $api_key,
            $model,
            100
        );

        if ( ! $response ) {
            return null;
        }

        $type = trim( strtolower( $response ) );
        $valid_types = [ 'image', 'video', 'audio', 'text', 'code', 'tool' ];

        return in_array( $type, $valid_types ) ? $type : null;
    }

    /**
     * Filter sites by type
     */
    private function filter_sites_by_type( $all_sites, $type ) {
        $filtered = [];

        foreach ( $all_sites as $key => $site ) {
            $site_cat = $site['cat'] ?? 'tool';

            // Direct match
            if ( $site_cat === $type ) {
                $filtered[ $key ] = $site;
                continue;
            }

            // Related categories
            if ( $this->is_related_category( $type, $site_cat ) ) {
                $filtered[ $key ] = $site;
            }
        }

        return $filtered;
    }

    /**
     * Check if categories are related
     */
    private function is_related_category( $type1, $type2 ) {
        $relations = [
            'image' => [ 'image', 'tool', 'code' ],
            'video' => [ 'video', 'image', 'tool' ],
            'audio' => [ 'audio', 'tool' ],
            'text' => [ 'text', 'code', 'tool' ],
            'code' => [ 'code', 'text', 'tool' ],
            'tool' => [ 'tool', 'text', 'code' ]
        ];

        return isset( $relations[ $type1 ] ) && in_array( $type2, $relations[ $type1 ] );
    }

    /**
     * Score sites with AI
     */
    private function score_sites_with_ai( $prompt, $sites, $api_key, $model ) {
        
        if ( empty( $sites ) ) {
            return [];
        }

        // Build site list
        $sites_list = implode( "\n", array_map( function( $key, $data ) {
            return "- {$data['name']}";
        }, array_keys( $sites ), $sites ) );

        $system_msg = "You are an expert in AI tools. Score each platform's relevance (0-100).

Given a prompt and platforms, return ONLY a JSON object with names as keys and scores as values.
No markdown, no explanations.

Example: {\"Midjourney\": 95, \"DALL-E 3\": 88}";

        $user_msg = "PROMPT:\n" . substr( $prompt, 0, 500 ) . "\n\nPLATFORMS:\n" . $sites_list;

        $response = $this->call_gapgpt_api(
            $system_msg,
            $user_msg,
            $api_key,
            $model,
            400
        );

        if ( ! $response ) {
            $this->log( 'Failed to get AI scores', 'error' );
            // Fallback
            $result = [];
            foreach ( $sites as $key => $site ) {
                $result[ $key ] = [ 'site' => $site, 'score' => 50 ];
            }
            return $result;
        }

        // Clean and parse JSON
        $response = str_replace( [ '```json', '```', '`' ], '', $response );
        $response = trim( $response );
        $scores = json_decode( $response, true );

        if ( ! is_array( $scores ) ) {
            $this->log( 'Failed to parse scores', 'error' );
            // Fallback
            $result = [];
            foreach ( $sites as $key => $site ) {
                $result[ $key ] = [ 'site' => $site, 'score' => 50 ];
            }
            return $result;
        }

        // Map scores back to keys
        $result = [];
        foreach ( $sites as $key => $site ) {
            $name = $site['name'];
            $score = isset( $scores[ $name ] ) ? (int) $scores[ $name ] : 50;
            $score = max( 0, min( 100, $score ) );

            $result[ $key ] = [ 'site' => $site, 'score' => $score ];
        }

        // Sort by score descending
        usort( $result, function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        });

        // Re-key
        $sorted_result = [];
        foreach ( $result as $item ) {
            foreach ( $sites as $orig_key => $orig_site ) {
                if ( $orig_site['name'] === $item['site']['name'] ) {
                    $sorted_result[ $orig_key ] = $item;
                    break;
                }
            }
        }

        return $sorted_result;
    }

    /**
     * Call GapGPT API
     */
    private function call_gapgpt_api( $system_msg, $user_msg, $api_key, $model, $max_tokens = 300 ) {
        
        $body = [
            'model' => $model,
            'messages' => [
                [ 'role' => 'system', 'content' => $system_msg ],
                [ 'role' => 'user', 'content' => substr( $user_msg, 0, 2000 ) ]
            ],
            'temperature' => 0.5,
            'max_tokens' => $max_tokens
        ];

        $response = wp_remote_post( $this->gapgpt_api, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode( $body ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log( 'GapGPT API Error: ' . $response->get_error_message(), 'error' );
            return null;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $this->log( 'GapGPT HTTP ' . $http_code, 'error' );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
            $this->log( 'Invalid response structure', 'error' );
            return null;
        }

        return $body['choices'][0]['message']['content'];
    }

    /**
     * Save selected sites to product meta
     */
    private function save_selected_sites( $product_id, $scored_sites ) {
        
        // Extract keys for AI Sites Manager format
        $selected_keys = array_keys( $scored_sites );

        // Save to meta (AI Sites Manager format)
        update_post_meta( $product_id, '_ai_sites_checked', $selected_keys );

        // Save detailed scores
        $scores_data = array_map( function( $data ) {
            return [
                'name' => $data['site']['name'],
                'url' => $data['site']['url'],
                'cat' => $data['site']['cat'],
                'score' => $data['score']
            ];
        }, $scored_sites );

        update_post_meta( $product_id, '_telebridge_auto_scores', $scores_data );

        // Enable auto display
        update_post_meta( $product_id, '_ai_sites_auto_display', 'yes' );
    }

    /**
     * Logging utility
     */
    private function log( $message, $type = 'info' ) {
        if ( ! $this->debug_mode ) {
            return;
        }

        $prefix = '[Telebridge Auto Cat ' . $type . ']';
        error_log( $prefix . ' ' . $message );
    }
}

/**
 * Initialize plugin
 */
function telebridge_auto_categorization_init() {
    Telebridge_Auto_Categorization::get_instance();
}
add_action( 'plugins_loaded', 'telebridge_auto_categorization_init' );