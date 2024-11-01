<?php
ini_set('allow_url_fopen', 'On');

/**
 * Customhelper File Doc Comment
 *
 * @package     CustomHelper
 */
class Customhelper {


	/**
	 * Check the API is valid or not
	 *
	 * @param string $url for API URL.
	 * @param string $key for API Token.
	 */
	public function check_api_valid_or_not( $url, $key ) {

		$tcg_api_token     = $key;
		$tcg_api_end_point = $url;

		$response = wp_remote_get( $tcg_api_end_point . '/status?token=' . $tcg_api_token , array(
			'headers' => array(
				'content' => 'application/json',
			),
		) );
		$body     = wp_remote_retrieve_body( $response );

		if ( ! empty( $body ) && 'OK' === $body ) {
			$msg = array(
				'status' => 'true',
				'msg'    => 'Connection established successfully',
			);
		} else {
			$msg = array(
				'status' => 'false',
				'msg'    => 'Invalid API key',
			);
		}
		return $msg;
	}

	/**
	 * Get Total TCGS Shipping Codes.
	 *
	 * @param string $api_url for API URL.
	 */
	public function get_shipping_codes( $api_url ) {


		$response = wp_remote_get( $api_url . '/shipping-codes' , array(
			'headers' => array(
				'content' => 'application/json',
			),
		) );
		$shipping_codesresponse     = wp_remote_retrieve_body( $response );

		$decoded_codes        = json_decode( $shipping_codesresponse );
		$total_shipping_codes = array();
		if(isset($decoded_codes) && !empty($decoded_codes)){
			foreach ( $decoded_codes as $key => $value ) {
				$total_shipping_codes[ $value->code ] = $value->title;
			}
		}
		return $total_shipping_codes;
	}

	public function get_waybill( $api_url ) {

		$response = wp_remote_get( $api_url );
		$body     = wp_remote_retrieve_body( $response );
		return $body;
	}

	public function get_tracking_number( $api_url,$reference_id,$api_key ) {

		$url = "{$api_url}/collection-details?token={$api_key}&reference={$reference_id}";
		$response = wp_remote_get( $url );
		$body     = wp_remote_retrieve_body( $response );
		return $body;
	}

	/**
	 * Get Total TCGS Shipping Options.
	 *
	 * @param string $api_url for API URL.
	 */
	public function get_available_shipping_options( $url, $encoded_shipping_opt_request ) {

		$headers = array(
			'content' => 'application/json',
			'accept' => 'application/json',
			'content-type' => 'application/json'
		);
		$args = array(
			'body'        => $encoded_shipping_opt_request,
			'timeout'     => '5',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'cookies'     => array(),
		);

		$response = wp_remote_post( $url , $args );
		
		$avail_shipping_options_response     = wp_remote_retrieve_body( $response );

		$decoded_options        = json_decode( $avail_shipping_options_response );
		return $decoded_options;
		
		
	}

	/**
	 *
	 *
	 * Get Possible rates according to selected Shipping codes.
	 *
	 * @param string $url for API URL.
	 * @param string $encodedshipping_rate JSON variable for request body.  */
	public function get_possible_rates( $url, $encodedshipping_rate ) {

		$headers = array(
			'content' => 'application/json',
			'accept' => 'application/json',
			'content-type' => 'application/json'
		);
		$args = array(
			'body'        => $encodedshipping_rate,
			'timeout'     => '5',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'cookies'     => array(),
		);

		$response = wp_remote_post( $url , $args );
		
		$avail_possible_rates     = wp_remote_retrieve_body( $response );
		return $avail_possible_rates;
		
	}

	/**
	 * Create TCG Order on Place order
	 *
	 * @param string $url for API URL.
	 * @param string $encodedshipping_rate JSON variable for request body.
	 */
	public function create_tcg_order( $url, $encodedshipping_rate ) {

		$headers = array(
			'content' => 'application/json',
			'accept' => 'application/json',
			'content-type' => 'application/json'
		);
		$args = array(
			'body'        => $encodedshipping_rate,
			'timeout'     => '20',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'cookies'     => array(),
		);
		$response = wp_remote_post( $url , $args );
		$create_order     = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );
		return $create_order;		
	}

	/**
	 * Get Lat. & Long using google maps API
	 *
	 * @param string $address for address data.
	 * @param string $api_key for Google maps API Key.
	 */
	public function get_lat_long( $address, $api_key ) {

		$response = wp_remote_get( 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode( $address ) . '&sensor=false&key=' . $api_key , array(
			'headers' => array(
				'content' => 'application/json',
			),
		) );
		$geo     = wp_remote_retrieve_body( $response );
		
		$geo = json_decode( $geo, true ); // Convert the JSON to an array.
		
		$lat_long_array = array();
		if ( isset( $geo['status'] ) && 'OK' === $geo['status'] ) {
			$latitude              = $geo['results'][0]['geometry']['location']['lat']; // Latitude.
			$longitude             = $geo['results'][0]['geometry']['location']['lng']; // Longitude.
			$lat_long_array['lat'] = (string) $latitude;
			$lat_long_array['lng'] = (string) $longitude;
		}
		return $lat_long_array;
	}


	/**
	 * Set Shipping settings in a array
	 *
	 */
	public function get_settings( $method_instance ) {
		
		$method_data = array();
		$method_data['connection_server'] = $method_instance->get_option( 'connection_server' );
		$method_data['prod_url'] = $method_instance->get_option( 'prod_url' );
		$method_data['prod_apikey'] = $method_instance->get_option( 'prod_apikey' );
		$method_data['stage_url'] = $method_instance->get_option( 'stage_url' );
		$method_data['stage_apikey'] = $method_instance->get_option( 'stage_apikey' );
		$method_data['maps_apikey'] = $method_instance->get_option( 'maps_apikey' );
		$method_data['shop_street_and_name'] = $method_instance->get_option( 'shop_street_and_name' );
		$method_data['shop_suburb'] = $method_instance->get_option( 'shop_suburb' );
		$method_data['shop_state'] = $method_instance->get_option( 'shop_state' );
		$method_data['shop_city'] = $method_instance->get_option( 'shop_city' );
		$method_data['shop_postal_code'] = $method_instance->get_option( 'shop_postal_code' );
		$method_data['shop_country'] = $method_instance->get_option( 'shop_country' );
		$method_data['shop_email'] = $method_instance->get_option( 'shop_email' );
		$method_data['company_name'] = $method_instance->get_option( 'company_name' );
		$method_data['shop_phone'] = $method_instance->get_option( 'shop_phone' );
		$method_data['address_type'] = $method_instance->get_option( 'address_type' );
		$method_data['excludes'] = $method_instance->get_option( 'excludes' );
		$method_data['percentage_markup'] = $method_instance->get_option( 'percentage_markup' );
		$method_data['automatically_submit_collection_order'] = $method_instance->get_option( 'automatically_submit_collection_order' );
		$method_data['remove_waybill_description'] = $method_instance->get_option( 'remove_waybill_description' );
		$method_data['price_rate_override_per_service'] = $method_instance->get_option( 'price_rate_override_per_service' );
		$method_data['label_override_per_service'] = $method_instance->get_option( 'label_override_per_service' );
		$method_data['product_length_per_parcel_1'] = $method_instance->get_option( 'product_length_per_parcel_1' );
		$method_data['product_width_per_parcel_1'] = $method_instance->get_option( 'product_width_per_parcel_1' );
		$method_data['product_height_per_parcel_1'] = $method_instance->get_option( 'product_height_per_parcel_1' );
		$method_data['product_length_per_parcel_2'] = $method_instance->get_option( 'product_length_per_parcel_2' );
		$method_data['product_width_per_parcel_2'] = $method_instance->get_option( 'product_width_per_parcel_2' );
		$method_data['product_height_per_parcel_2'] = $method_instance->get_option( 'product_height_per_parcel_2' );
		$method_data['product_length_per_parcel_3'] = $method_instance->get_option( 'product_length_per_parcel_3' );
		$method_data['product_width_per_parcel_3'] = $method_instance->get_option( 'product_width_per_parcel_3' );
		$method_data['product_height_per_parcel_3'] = $method_instance->get_option( 'product_height_per_parcel_3' );
		$method_data['product_height_per_parcel_4'] = $method_instance->get_option( 'product_height_per_parcel_4' );
		$method_data['product_length_per_parcel_5'] = $method_instance->get_option( 'product_length_per_parcel_5' );
		$method_data['product_width_per_parcel_5'] = $method_instance->get_option( 'product_width_per_parcel_5' );
		$method_data['product_height_per_parcel_5'] = $method_instance->get_option( 'product_height_per_parcel_5' );
		$method_data['product_length_per_parcel_6'] = $method_instance->get_option( 'product_length_per_parcel_6' );
		$method_data['product_width_per_parcel_6'] = $method_instance->get_option( 'product_width_per_parcel_6' );
		$method_data['product_height_per_parcel_6'] = $method_instance->get_option( 'product_height_per_parcel_6' );
		$method_data['free_shipping'] = $method_instance->get_option( 'free_shipping' );
		$method_data['rates_for_free_shipping'] = $method_instance->get_option( 'rates_for_free_shipping' );
		$method_data['amount_for_free_shipping'] = $method_instance->get_option( 'amount_for_free_shipping' );
		$method_data['product_free_shipping'] = $method_instance->get_option( 'product_free_shipping' );
		$method_data['usemonolog'] = $method_instance->get_option( 'usemonolog' );
		$method_data['enablemethodbox'] = $method_instance->get_option( 'enablemethodbox' );
		$method_data['enablenonstandardpackingbox'] = $method_instance->get_option( 'enablenonstandardpackingbox' );
		return $method_data;
	}
}
