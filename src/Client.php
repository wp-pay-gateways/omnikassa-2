<?php

namespace Pronamic\WordPress\Pay\Gateways\OmniKassa2;

/**
 * Title: OmniKassa 2.0 client
 * Description:
 * Copyright: Copyright (c) 2017
 * Company: Pronamic
 *
 * @author Remco Tolsma
 * @version 1.0.0
 * @since 1.0.0
 */
class Client {
	/**
	 * 
	 * @var string
	 */
	const URL_ACCEPTANCE = 'https://betalen-acpt3.rabobank.nl/omnikassa-api/';

	/**
	 * 
	 * @var string
	 */
	const URL_PRODUCTION = 'https://betalen.rabobank.nl/omnikassa-api/';

	const URL_SANDBOX =  'https://betalen.rabobank.nl/omnikassa-api-sandbox/';

	//////////////////////////////////////////////////

	/**
	 * Error
	 *
	 * @var WP_Error
	 */
	private $error;

	//////////////////////////////////////////////////

	/**
	 * The URL.
	 *
	 * @var string
	 */
	private $url;

	//////////////////////////////////////////////////

	/**
	 * Error
	 *
	 * @return WP_Error
	 */
	public function get_error() {
		return $this->error;
	}

	//////////////////////////////////////////////////

	/**
	 * Get the URL
	 *
	 * @return the action URL
	 */
	public function get_url() {
		return $this->url;
	}

	/**
	 * Set the action URL
	 *
	 * @param string $url an URL
	 */
	public function set_url( $url ) {
		$this->url = $url;
	}

	//////////////////////////////////////////////////

	/**
	 *
	 */
	public function get_refresh_token() {
		return $this->refresh_token;
	}

	/**
	 *
	 */
	public function set_refresh_token( $refresh_token ) {
		$this->refresh_token = $refresh_token;
	}

	//////////////////////////////////////////////////

	/**
	 *
	 */
	public function get_signing_key() {
		return $this->signing_key;
	}

	/**
	 *
	 */
	public function set_signing_key( $signing_key ) {
		$this->signing_key = $signing_key;
	}

	//////////////////////////////////////////////////

	/**
	 * Get access token.
	 */
	public function get_access_token_data() {
		$url = $this->get_url() . 'gatekeeper/refresh';

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_refresh_token(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error = new \WP_Error( 'omnikassa_2_error', 'Could not parse response.' );

			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body );

		if ( is_object( $data ) && isset( $data->errorCode ) && isset( $data->errorMessage ) ) {
			$this->error = new \WP_Error( 'omnikassa_2_error', $data->errorMessage, $data );

			return false;
		}

		if ( is_object( $data ) && '200' != wp_remote_retrieve_response_code( $response ) ) { // WPCS: loose comparison ok.
			return false;
		}

		if ( ! is_object( $data ) ) {
			$this->error = new \WP_Error( 'omnikassa_2_error', 'Could not parse response.' );

			return false;
		}

		return $data;
	}

	public function order_announce( $config, $order ) {
		$url = $this->get_url() . 'order/server/api/order';

		$object = $order->get_json();
		$object->signature = Security::get_order_signature( $order, $config->signing_key );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $config->access_token,
			),
			'body'    => json_encode( $object ),
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body );

		if ( is_object( $data ) && isset( $data->errorCode ) && isset( $data->errorMessage ) ) {
			$this->error = new \WP_Error( 'omnikassa_2_error', $data->errorMessage, $data );
		}

		if ( is_object( $data ) && '201' != wp_remote_retrieve_response_code( $response ) ) { // WPCS: loose comparison ok.
			$this->error = new \WP_Error( 'omnikassa_2_error', $data->consumerMessage, $data );

			return false;
		}

		if ( ! is_object( $data ) ) {
			$this->error = new \WP_Error( 'omnikassa_2_error', 'Could not parse response.' );

			return false;
		}

		return $data;
	}
}
