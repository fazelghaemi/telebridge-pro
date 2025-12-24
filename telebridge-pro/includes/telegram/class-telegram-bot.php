<?php

/**
 * Telegram Bot Handler.
 *
 * Handles incoming webhooks, processes messages, downloads media,
 * and orchestrates the AI analysis.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/includes/telegram
 * @author     TeleBridge Team
 */
class TeleBridge_Telegram_Bot {

	private $api_token;
	private $proxy_url;

	public function __construct() {
		$this->api_token = get_option( 'telebridge_telegram_bot_token' );
		$this->proxy_url = get_option( 'telebridge_proxy_url' ); // Cloudflare Worker URL
		
		// Hook into the core action triggered by the webhook listener
		add_action( 'telebridge_process_telegram_update', array( $this, 'process_update' ) );
	}

	/**
	 * Main entry point for processing an update.
	 *
	 * @param array $update The decoded JSON update from Telegram.
	 */
	public function process_update( $update ) {
		
		// Determine if it's a channel post or a private message
		$message = isset( $update['channel_post'] ) ? $update['channel_post'] : ( isset( $update['message'] ) ? $update['message'] : null );

		if ( ! $message ) {
			return;
		}

		// 1. Extract Basic Info
		$telegram_post_id = $message['message_id'];
		$chat_id          = $message['chat']['id'];
		$media_group_id   = isset( $message['media_group_id'] ) ? $message['media_group_id'] : null;
		
		// Check for duplicates
		if ( $this->post_exists( $telegram_post_id, $media_group_id ) ) {
			// If it's part of a media group, we might need to just append the image
			if ( $media_group_id ) {
				$this->append_media_to_existing_post( $message, $media_group_id );
			}
			return;
		}

		// 2. Get Caption (or Text)
		$caption = isset( $message['caption'] ) ? $message['caption'] : ( isset( $message['text'] ) ? $message['text'] : '' );
		
		// If empty caption and no media, skip (unless it's a media group part without caption)
		if ( empty( $caption ) && ! isset( $message['photo'] ) && ! isset( $message['video'] ) ) {
			return;
		}

		// 3. AI Analysis (Only for the first part of a message or single posts)
		// If it's a media group, usually only one message has the caption.
		$ai_response = array();
		if ( ! empty( $caption ) ) {
			$ai_response = $this->analyze_content_with_ai( $caption );
			
			if ( is_wp_error( $ai_response ) ) {
				error_log( 'TeleBridge AI Error: ' . $ai_response->get_error_message() );
				// Fallback data
				$ai_response = array(
					'product_name' => 'Telegram Post #' . $telegram_post_id,
					'description'  => $caption,
					'price'        => null
				);
			}
		} else {
			// No caption provided (e.g. 2nd image of an album)
			$ai_response = array(
				'product_name' => 'Gallery Image #' . $telegram_post_id,
				'description'  => '',
			);
		}

		// 4. Download Media (Image/Video)
		$attachment_id = 0;
		$file_title    = isset( $ai_response['product_name'] ) ? $ai_response['product_name'] : 'telegram-media';
		
		if ( isset( $message['photo'] ) ) {
			$photo_array   = end( $message['photo'] ); // Get largest size
			$attachment_id = $this->download_telegram_file( $photo_array['file_id'], $file_title );
		} elseif ( isset( $message['video'] ) ) {
			// For videos, file size might be an issue depending on server config
			$attachment_id = $this->download_telegram_file( $message['video']['file_id'], $file_title, 'video' );
		}

		// 5. Create or Update Post
		if ( $media_group_id && $parent_post_id = $this->get_post_by_media_group( $media_group_id ) ) {
			// If post already exists for this group, just add the image to gallery
			$this->add_image_to_gallery( $parent_post_id, $attachment_id );
		} else {
			// Create new post
			$this->create_wordpress_post( $ai_response, $attachment_id, $telegram_post_id, $media_group_id );
		}

	}

	/**
	 * Send caption to AI for structured data extraction.
	 */
	private function analyze_content_with_ai( $caption ) {
		$active_provider = get_option( 'telebridge_active_ai_provider', 'google' );
		$api_key         = get_option( "telebridge_api_key_{$active_provider}" );
		
		// If we had the JetEngine Class loaded, we could fetch schema here
		// $schema = TeleBridge_JetEngine_Handler::get_schema(); 
		// For now, pass empty to get default fields.
		$schema = array(); 

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', 'AI API Key is missing' );
		}

		// Ensure AI Manager is loaded
		if ( ! class_exists( 'TeleBridge_AI_Manager' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . '../api/class-ai-manager.php';
		}

		$ai = TeleBridge_AI_Manager::get_instance( $active_provider, $api_key );
		return $ai->analyze_text( $caption, $schema );
	}

	/**
	 * Download file using Proxy if available.
	 */
	private function download_telegram_file( $file_id, $title_prefix, $type = 'image' ) {
		
		// A. Get File Path info
		$base_api = $this->proxy_url ? untrailingslashit( $this->proxy_url ) : "https://api.telegram.org";
		$info_url = "{$base_api}/bot{$this->api_token}/getFile?file_id={$file_id}";
		
		$response = wp_remote_get( $info_url, array( 'timeout' => 30 ) );
		
		if ( is_wp_error( $response ) ) return 0;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $body['result']['file_path'] ) ) return 0;

		$file_path = $body['result']['file_path'];
		
		// B. Construct Download URL
		// If using Cloudflare Worker proxy, the file path is appended to the worker URL
		// Standard: https://api.telegram.org/file/bot<token>/<path>
		// Proxy: https://proxy.worker.dev/file/bot<token>/<path>
		if ( $this->proxy_url ) {
			$download_url = "{$base_api}/file/bot{$this->api_token}/{$file_path}";
		} else {
			$download_url = "https://api.telegram.org/file/bot{$this->api_token}/{$file_path}";
		}

		// C. Sideload
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Sanitized Filename
		$ext = ( $type === 'video' ) ? '.mp4' : '.jpg';
		$filename = sanitize_title( $title_prefix ) . '-' . uniqid() . $ext;
		
		// Using a temp file wrapper to handle headers properly if needed
		$tmp = download_url( $download_url );

		if ( is_wp_error( $tmp ) ) {
			error_log( 'TeleBridge Download Error: ' . $tmp->get_error_message() );
			return 0;
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * Create WordPress Post/Product.
	 */
	private function create_wordpress_post( $data, $image_id, $telegram_id, $media_group_id = null ) {
		
		$target_post_type = get_option( 'telebridge_target_post_type', 'post' );
		$post_status      = get_option( 'telebridge_default_status', 'draft' );

		$post_title = isset( $data['product_name'] ) ? $data['product_name'] : 'Telegram Import';

		$post_arr = array(
			'post_title'   => $post_title,
			'post_content' => isset( $data['description'] ) ? $data['description'] : '',
			'post_status'  => $post_status,
			'post_type'    => $target_post_type,
		);

		$post_id = wp_insert_post( $post_arr );

		if ( ! is_wp_error( $post_id ) ) {
			
			// Save Telegram Meta Data for duplicate checking
			update_post_meta( $post_id, '_telebridge_telegram_id', $telegram_id );
			if ( $media_group_id ) {
				update_post_meta( $post_id, '_telebridge_media_group_id', $media_group_id );
			}

			// Set Featured Image
			if ( $image_id > 0 ) {
				set_post_thumbnail( $post_id, $image_id );
			}

			// Handle Integrations
			if ( class_exists( 'Jet_Engine' ) ) {
				$je = new TeleBridge_JetEngine_Handler();
				$je->save_meta_data( $post_id, $data );
			}
			
			if ( class_exists( 'WooCommerce' ) && $target_post_type === 'product' ) {
				$wc = new TeleBridge_WooCommerce_Handler();
				$wc->convert_to_product( $post_id, $data );
			}
		}
	}

	/**
	 * Check if post exists by Telegram ID.
	 */
	private function post_exists( $telegram_id, $media_group_id = null ) {
		// First check specifically by message ID
		$args = array(
			'post_type'      => 'any',
			'meta_key'       => '_telebridge_telegram_id',
			'meta_value'     => $telegram_id,
			'posts_per_page' => 1,
			'fields'         => 'ids'
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) return true;

		return false;
	}

	/**
	 * Helper to find parent post for a media group.
	 */
	private function get_post_by_media_group( $media_group_id ) {
		$args = array(
			'post_type'      => 'any',
			'meta_key'       => '_telebridge_media_group_id',
			'meta_value'     => $media_group_id,
			'posts_per_page' => 1,
			'fields'         => 'ids'
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			return $query->posts[0];
		}
		return false;
	}

	/**
	 * Append new image to existing post (for Albums).
	 */
	private function append_media_to_existing_post( $message, $media_group_id ) {
		$post_id = $this->get_post_by_media_group( $media_group_id );
		if ( ! $post_id ) return;

		// We assume album parts are photos. Video handling can be added similarly.
		if ( isset( $message['photo'] ) ) {
			$photo_array = end( $message['photo'] );
			$att_id = $this->download_telegram_file( $photo_array['file_id'], get_the_title( $post_id ) . '-gallery' );
			
			if ( $att_id ) {
				$this->add_image_to_gallery( $post_id, $att_id );
			}
		}
	}

	/**
	 * Add attachment ID to WooCommerce Product Gallery or JetEngine Gallery.
	 */
	private function add_image_to_gallery( $post_id, $attachment_id ) {
		// WooCommerce Logic
		if ( get_post_type( $post_id ) === 'product' && class_exists( 'WooCommerce' ) ) {
			$product = wc_get_product( $post_id );
			$gallery_ids = $product->get_gallery_image_ids();
			$gallery_ids[] = $attachment_id;
			$product->set_gallery_image_ids( $gallery_ids );
			$product->save();
		} 
		// Fallback / JetEngine logic (saving as comma separated IDs in a meta field)
		else {
			// You would map this to a specific field key in settings, 
			// for now we append to a standard gallery meta key if exists or create one.
			$current_gallery = get_post_meta( $post_id, 'telebridge_gallery', true );
			if ( empty( $current_gallery ) ) {
				update_post_meta( $post_id, 'telebridge_gallery', $attachment_id );
			} else {
				// Check if it's an array or string (JetEngine usually uses string "id,id,id")
				if ( is_string( $current_gallery ) ) {
					update_post_meta( $post_id, 'telebridge_gallery', $current_gallery . ',' . $attachment_id );
				}
			}
		}
	}
}