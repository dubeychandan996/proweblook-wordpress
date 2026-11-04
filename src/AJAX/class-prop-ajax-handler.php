<?php
/**
 * Generic WordPress AJAX registration wrapper.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Ajax_Handler
 */
class PROP_Ajax_Handler {

	/**
	 * Whether the AJAX action requires authentication.
	 *
	 * @var bool
	 */
	protected $private = true;

	/**
	 * AJAX action slug.
	 *
	 * @var string
	 */
	protected $action = '';

	/**
	 * Callback callable.
	 *
	 * @var callable|null
	 */
	protected $callback = null;

	/**
	 * Register WordPress AJAX hooks.
	 *
	 * @param string   $action   Action slug.
	 * @param callable $callback Callback handler.
	 * @param bool     $private  True for logged-in users only.
	 * @return void
	 */
	public function register( $action, $callback, $private = true ) {
		$this->set_action( $action );
		$this->set_callback( $callback );
		$this->set_private( $private );

		add_action( 'wp_ajax_' . $this->get_action(), array( $this, 'do_callback' ) );

		if ( ! $this->get_private() ) {
			add_action( 'wp_ajax_nopriv_' . $this->get_action(), array( $this, 'do_callback' ) );
		}
	}

	/**
	 * Execute the registered callback.
	 *
	 * @return void
	 */
	public function do_callback() {
		if ( is_callable( $this->callback ) ) {
			call_user_func( $this->callback );
		}
	}

	/**
	 * Set action slug.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	public function set_action( $action ) {
		$this->action = sanitize_key( (string) $action );
		return $this->get_action();
	}

	/**
	 * Get action slug.
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Set callback.
	 *
	 * @param callable $callback Callback callable.
	 * @return callable|bool
	 */
	public function set_callback( $callback ) {
		if ( ! is_callable( $callback ) ) {
			return false;
		}
		$this->callback = $callback;
		return $this->get_callback();
	}

	/**
	 * Get callback.
	 *
	 * @return callable|null
	 */
	public function get_callback() {
		return $this->callback;
	}

	/**
	 * Set private flag.
	 *
	 * @param bool $private Private flag.
	 * @return bool
	 */
	public function set_private( $private ) {
		$this->private = (bool) $private;
		return $this->get_private();
	}

	/**
	 * Get private flag.
	 *
	 * @return bool
	 */
	public function get_private() {
		return $this->private;
	}
}
