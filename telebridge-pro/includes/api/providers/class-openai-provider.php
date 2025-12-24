<?php

/**
 * OpenAI Provider Implementation.
 *
 * Handles communication with OpenAI API (GPT-4o, GPT-4 Turbo, etc.).
 * deeply integrated with JSON Mode for 100% structured reliability.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/includes/api/providers
 * @author     TeleBridge Team
 */
class TeleBridge_OpenAI_Provider implements TeleBridge_AI_Provider_Interface {

	/**
	 * The API Endpoint for OpenAI Chat Completions.
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * The API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * The Model to use.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Initialize the class.
	 *
	 * @param string $api_key OpenAI Secret Key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
		// Allow user to filter the model, defaulting to gpt-4o for speed/quality balance
		$this->model = apply_filters( 'telebridge_openai_model', 'gpt-4o' );
	}

	/**
	 * Analyze text using OpenAI GPT Models.
	 *
	 * @param string $text   Raw Telegram caption.
	 * @param array  $schema Fields to extract (passed from JetEngine mapping).
	 * @return array|WP_Error Parsed JSON or Error.
	 */
	public function analyze_text( $text, $schema = array() ) {

		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API Key is missing.', 'telebridge-pro' ) );
		}

		// 1. Build the System Prompt with strict JSON enforcement
		$system_instruction = "You are an advanced Data Extraction AI designed for WordPress automation.\n";
		$system_instruction .= "Your task is to analyze Telegram posts and extract product/post details into a valid JSON object.\n";
		
		// 2. Define the Schema Structure
		if ( ! empty( $schema ) ) {
			$system_instruction .= "You MUST map the data to the following JSON keys strictly:\n";
			$system_instruction .= implode( ', ', $schema ) . "\n";
		} else {
			$system_instruction .= "Extract the following keys:\n";
			$system_instruction .= "- product_name (string)\n";
			$system_instruction .= "- price (number, remove currency symbols)\n";
			$system_instruction .= "- currency (string, inferred from context)\n";
			$system_instruction .= "- description (string, clean HTML formatted, remove emojis)\n";
			$system_instruction .= "- short_description (string, summary)\n";
			$system_instruction .= "- attributes (object, e.g., {'color': 'red', 'size': 'XL'})\n";
			$system_instruction .= "- category_suggestion (string)\n";
		}

		$system_instruction .= "\nIMPORTANT Rules:\n";
		$system_instruction .= "1. If a value is missing, use null.\n";
		$system_instruction .= "2. Translate 'riyal', 'toman', etc. to standard numbers.\n";
		$system_instruction .= "3. Do not include markdown formatting (```json) in response, just the raw JSON.\n";

		// 3. Prepare the Request Body
		$body = array(
			'model'           => $this->model,
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $system_instruction,
				),
				array(
					'role'    => 'user',
					'content' => $text,
				),
			),
			'temperature'     => 0.3, // Low temperature for factual extraction
			'response_format' => array( 'type' => 'json_object' ), // ENFORCE JSON MODE
		);

		// 4. Send Request
		$response = wp_remote_post( $this->api_endpoint, array(
			'body'    => json_encode( $body ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 45, // OpenAI can be slow sometimes
		) );

		// 5. Handle Network Errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'openai_network_error', $response->get_error_message() );
		}

		// 6. Handle API Errors (4xx, 5xx)
		$response_code = wp_remote_retrieve_response_code( $response );
		$body_content  = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body_content, true );

		if ( $response_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI Error';
			return new WP_Error( 'openai_api_error', $error_msg );
		}

		// 7. Parse the Success Response
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = $data['choices'][0]['message']['content'];
			$json    = json_decode( $content, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json;
			} else {
				return new WP_Error( 'json_parse_error', __( 'Failed to decode JSON from OpenAI.', 'telebridge-pro' ) );
			}
		}

		return new WP_Error( 'empty_response', __( 'OpenAI returned an empty response.', 'telebridge-pro' ) );
	}

	/**
	 * Validate connection by sending a minimal token request.
	 *
	 * @return bool
	 */
	public function validate_connection() {
		// Simple ping to check if Key is valid
		$response = wp_remote_post( $this->api_endpoint, array(
			'body'    => json_encode( array(
				'model'    => 'gpt-4o-mini', // Cheapest model for testing
				'messages' => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
				'max_tokens' => 5
			) ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 10,
		) );

		return ( wp_remote_retrieve_response_code( $response ) === 200 );
	}
}