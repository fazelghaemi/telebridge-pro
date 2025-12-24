<?php

/**
 * WooCommerce Integration Handler.
 *
 * Handles the creation and update of WooCommerce products from Telegram data.
 * It goes beyond simple price setting by handling attributes, SKUs, and inventory.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/includes/integrations
 * @author     TeleBridge Team
 */
class TeleBridge_WooCommerce_Handler {

	/**
	 * Convert a standard post to a fully functional WooCommerce Product.
	 *
	 * @param int   $post_id The ID of the newly created post.
	 * @param array $ai_data The JSON data extracted by AI.
	 */
	public function convert_to_product( $post_id, $ai_data ) {
		
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// 1. Get Product Instance
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			// If for some reason it's not a product object yet, force it (though post_type should be 'product')
			$product = new WC_Product_Simple( $post_id );
		}

		// 2. Set Basic Data
		if ( isset( $ai_data['price'] ) ) {
			$price = $this->sanitize_price( $ai_data['price'] );
			$product->set_regular_price( $price );
			$product->set_price( $price );
		}

		// 3. Generate SKU (Stock Keeping Unit)
		// We use the Telegram Post ID if available, or a random string
		$sku = 'TB-' . ( isset( $ai_data['post_id'] ) ? $ai_data['post_id'] : uniqid() );
		$product->set_sku( $sku );

		// 4. Set Inventory
		$product->set_manage_stock( true );
		$product->set_stock_status( 'instock' );
		$product->set_stock_quantity( get_option( 'telebridge_default_stock_qty', 10 ) );

		// 5. Handle Dynamic Attributes (The "Wow" Factor)
		// Example AI Output: "attributes": {"Color": "Red", "Size": "XL"}
		if ( ! empty( $ai_data['attributes'] ) && is_array( $ai_data['attributes'] ) ) {
			$this->set_product_attributes( $product, $ai_data['attributes'] );
		}

		// 6. Save Product
		$product->save();
	}

	/**
	 * Sanitize price string to float.
	 * Removes commas, currency symbols, etc.
	 *
	 * @param mixed $raw_price
	 * @return float
	 */
	private function sanitize_price( $raw_price ) {
		// Remove anything that isn't a digit or a decimal point
		return floatval( preg_replace( '/[^0-9.]/', '', $raw_price ) );
	}

	/**
	 * Dynamically create and assign attributes to the product.
	 *
	 * @param WC_Product $product
	 * @param array      $attributes_data Key-Value pair of attributes.
	 */
	private function set_product_attributes( $product, $attributes_data ) {
		$attributes = array();

		foreach ( $attributes_data as $name => $value ) {
			
			// Skip empty values
			if ( empty( $value ) ) continue;

			$attribute = new WC_Product_Attribute();
			
			// Set Attribute Name (e.g., "Color")
			$attribute->set_name( $name );
			
			// Set Options (e.g., "Red")
			// If multiple values are comma-separated in string, explode them
			$options = array_map( 'trim', explode( ',', $value ) );
			$attribute->set_options( $options );
			
			$attribute->set_position( 0 );
			$attribute->set_visible( true );
			$attribute->set_variation( false ); // Set to true if we upgrade to Variable Products later

			$attributes[] = $attribute;
		}

		$product->set_attributes( $attributes );
	}
}