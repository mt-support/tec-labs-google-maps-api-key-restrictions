<?php
namespace Tribe\Extensions\GoogleMapsApiKeyRestrictions;

use TEC\Common\Contracts\Service_Provider;

/**
 * Class Plugin
 *
 * @since   1.0.0
 *
 * @package Tribe\Extensions\GoogleMapsApiKeyRestrictions
 */
class Plugin extends Service_Provider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '1.0.1';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'google-maps-api-key-restrictions';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_GOOGLE_MAPS_API_KEY_RESTRICTIONS_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Set up the plugin provider properties.
		$this->plugin_path = trailingslashit( dirname( static::FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.google_maps_api_key_restrictions', $this );
		$this->container->singleton( 'extension.google_maps_api_key_restrictions.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		$this->get_settings();

		// Start binds.

		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * @param mixed  $response
	 * @param array  $args
	 * @param string $url
	 *
	 * @return array|WP_Error
	 */
	public function pre_http_request( $response, $args, $url ) {
		$key = $this->get_option( 'gmaps_geo_restriction_key' );

		// Fallback is option is empty
		if ( empty( $key ) || $key == '' ) {
			$key = tribe_get_option( 'google_maps_js_api_key' );
		}
		// If this is not a Google Maps geocoding request, or if it is but our replacement
		// key is already in place, then we need do nothing more.
		if (
			0 !== strpos( $url, 'https://maps.googleapis.com/maps/api/geocode' )
			|| false !== strpos( $url, $key )
		) {
			return $response;
		}

		// Replace the API key.
		$url = add_query_arg( 'key', $key, $url );

		// Perform a new request with our alternative API key and return the result.
		return wp_remote_get( $url, $args );
	}

	/**
	 * Checks whether the plugin dependency manifest is satisfied or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the plugin dependency manifest is satisfied or not.
	 */
	protected function check_plugin_dependencies() {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies() {
		$plugin_register = new Plugin_Register();
		$plugin_register->register_plugin();

		$this->container->singleton( Plugin_Register::class, $plugin_register );
		$this->container->singleton( 'extension.google_maps_api_key_restrictions', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
	 * @see \Tribe\Extensions\GoogleMapsApiKeyRestrictions\Settings::set_options_prefix()
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tribe-ext-google-maps-api-key-restrictions' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Get all of this extension's options.
	 *
	 * @return array
	 */
	public function get_all_options() {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param $option
	 * @param string $default
	 *
	 * @return array
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}
}