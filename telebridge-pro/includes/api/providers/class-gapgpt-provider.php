<?php

/**
 * GapGPT Provider Implementation.
 *
 * Handles communication with GapGPT Platform.
 * Useful for regions with restricted access to global AI providers.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/includes/api/providers
 * @author     TeleBridge Team
 */
class TeleBridge_GapGPT_Provider implements TeleBridge_AI_Provider_Interface {

	/**
	 * The API Endpoint for GapGPT.
	 * NOTE: Check GapGPT documentation for the exact endpoint. 
	 * We assume standard OpenAI-compatible endpoint structure here.
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.gapgpt.app/v1/chat/completions';

	/**
	 * The API Token.
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Initialize the class.
	 *
	 * @param string $api_token GapGPT API Token.
	 */
	public function __construct( $api_token ) {
		$this->api_token = $api_token;
	}

	/**
	 * Analyze text using GapGPT.
	 *
	 * @param string $text   Raw Telegram caption.
	 * @param array  $schema Fields to extract.
	 * @return array|WP_Error Parsed JSON or Error.
	 */
	public function analyze_text( $text, $schema = array() ) {

		if ( empty( $this->api_token ) ) {
			return new WP_Error( 'missing_token', __( 'GapGPT Token is missing.', 'telebridge-pro' ) );
		}

		// 1. Prepare System Prompt (Optimized for GapGPT models)
		$system_instruction = "Act as a JSON Data Extractor for a WordPress plugin.\n";
		$system_instruction .= "Analyze the input text (Telegram Post) and return a VALID JSON object.\n";
		
		if ( ! empty( $schema ) ) {
			$system_instruction .= "Map data to these keys: " . implode( ', ', $schema ) . ".\n";
		} else {
			$system_instruction .= "Required keys: product_name, price, currency, description, attributes.\n";
		}

		$system_instruction .= "Rules:\n- Return ONLY raw JSON.\n- No markdown.\n- Use null for missing values.";

		// 2. Prepare Payload
		$body = array(
			'model'       => 'gpt-4o', // Usually GapGPT supports standard model names
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_instruction ),
				array( 'role' => 'user', 'content' => $text ),
			),
			'temperature' => 0.2, // Low creativity for data extraction
		);

		// 3. Send Request
		$response = wp_remote_post( $this->api_endpoint, array(
			'body'    => json_encode( $body ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_token, // Standard Bearer Token
			),
			'timeout' => 60, // GapGPT might take longer depending on load
		) );

		// 4. Handle Errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'gapgpt_network_error', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body_content  = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body_content, true );

		if ( $response_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown GapGPT Error';
			return new WP_Error( 'gapgpt_api_error', "Status $response_code: $error_msg" );
		}

		// 5. Parse JSON Response
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = $data['choices'][0]['message']['content'];
			
			// Sanitize Content (Remove Markdown if AI adds it)
			$content = str_replace( array( '```json', '```' ), '', $content );
			$json    = json_decode( $content, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json;
			} else {
				// Retry mechanism or specific error logging could go here
				return new WP_Error( 'json_parse_error', __( 'Failed to decode JSON from GapGPT.', 'telebridge-pro' ) );
			}
		}

		return new WP_Error( 'empty_response', __( 'GapGPT returned an empty response.', 'telebridge-pro' ) );
	}

	/**
	 * Validate connection.
	 *
	 * @return bool
	 */
	public function validate_connection() {
		$response = wp_remote_post( $this->api_endpoint, array(
			'body'    => json_encode( array(
				'model'      => 'gpt-3.5-turbo',
				'messages'   => array( array( 'role' => 'user', 'content' => 'Ping' ) ),
				'max_tokens' => 5
			) ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_token,
			),
			'timeout' => 15,
		) );

		return ( wp_remote_retrieve_response_code( $response ) === 200 );
	}
}