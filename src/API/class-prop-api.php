<?php
/**
 * HTTP API client for ProWebLook Phone, WhatsApp & Email Validation services.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_API
 */
class PROP_API {

	/**
	 * Phone validation endpoint (preserved property for v1.x compatibility).
	 *
	 * @var string
	 */
	protected $endpoint = 'https://lookup.proweblook.com/api/v1/phonevalidation';

	/**
	 * WhatsApp validation endpoint.
	 *
	 * @var string
	 */
	protected $wa_endpoint = 'https://lookup.proweblook.com/api/v1/wavalidation';

	/**
	 * Email validation endpoint.
	 *
	 * @var string
	 */
	protected $email_endpoint = 'https://lookup.proweblook.com/api/v1/emailvalidation';

	/**
	 * Caller ID (CNAM) validation endpoint.
	 *
	 * @var string
	 */
	protected $caller_id_endpoint = 'https://lookup.proweblook.com/api/v1/calleridvalidation';

	/**
	 * Domain / IP DNSBL Blacklist checker endpoint.
	 *
	 * @var string
	 */
	protected $blacklist_endpoint = 'https://lookup.proweblook.com/api/v1/blacklistchecker';

	/**
	 * Edge IP Geolocation endpoint (20,000/mo free quota).
	 *
	 * @var string
	 */
	protected $ip_geo_endpoint = 'https://ipapi.proweblook.com/';

	/**
	 * Unified balance endpoint.
	 *
	 * @var string
	 */
	protected $zapier_balance_endpoint = 'https://proweblook.com/api/v1/zapier/get_balance';

	/**
	 * Legacy monolith balance fallback endpoint.
	 *
	 * @var string
	 */
	protected $legacy_balance_endpoint = 'https://proweblook.com/api/v1/checkbalance';

	/**
	 * Phone number to validate.
	 *
	 * @var string|null
	 */
	protected $phone = null;

	/**
	 * ProWebLook API Key.
	 *
	 * @var string|null
	 */
	protected $apikey = null;

	/**
	 * Last response object.
	 *
	 * @var object|null
	 */
	protected $response = null;

	/**
	 * Default ISO 3166-1 alpha-2 country code.
	 *
	 * @var string
	 */
	protected $country_code = '';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	protected $timeout = 15;

	/**
	 * Perform a phone validation request (preserves v1.x signature & return format).
	 *
	 * @param string $country_code Optional 2-letter ISO country code override.
	 * @return object|null
	 */
	public function request( $country_code = '' ) {
		$api_key = trim( (string) $this->get_apikey() );
		$phone   = trim( (string) $this->get_phone() );

		if ( '' === $api_key || '' === $phone ) {
			return null;
		}

		$resolved_country = ! empty( $country_code ) ? strtoupper( trim( (string) $country_code ) ) : strtoupper( trim( (string) $this->get_country_code() ) );
		if ( 0 === strpos( $phone, '+' ) ) {
			$resolved_country = '';
		}

		$cache_key = $this->build_cache_key( 'phone', $phone . '|' . $resolved_country );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return $this->set_response( (object) $cached );
		}

		$body = array(
			'phone_number' => $phone,
			'api_key'      => $api_key,
		);
		if ( ! empty( $resolved_country ) && '0' !== $resolved_country ) {
			$body['country_code'] = $resolved_country;
		}

		$result = wp_remote_post(
			$this->endpoint,
			array(
				'method'     => 'POST',
				'timeout'    => $this->get_timeout(),
				'blocking'   => true,
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
				'body'       => $body,
			)
		);

		if ( is_wp_error( $result ) ) {
			return null;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( 401 === $http_code || 403 === $http_code ) {
			return $this->set_response(
				(object) array(
					'status'    => 'API_KEY_INVALID_OR_DEPLETED',
					'http_code' => $http_code,
					'error'     => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Invalid API key.',
				)
			);
		}

		if ( 402 === $http_code ) {
			return $this->set_response(
				(object) array(
					'status'    => 'API_KEY_INVALID_OR_DEPLETED',
					'http_code' => $http_code,
					'error'     => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Insufficient phone API credits.',
				)
			);
		}

		if ( 429 === $http_code ) {
			return $this->set_response(
				(object) array(
					'status'    => 'RATE_LIMIT_EXCEEDED',
					'http_code' => $http_code,
				)
			);
		}

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return null;
		}

		if ( ! empty( $decoded['status'] ) && in_array( $decoded['status'], array( 'VALID_CONFIRMED', 'VALID_UNCONFIRMED', 'INVALID' ), true ) ) {
			$this->set_cached( $cache_key, $decoded );
		}

		return $this->set_response( (object) $decoded );
	}

	/**
	 * Perform a silent WhatsApp registration & business account check.
	 * Never sends an OTP or message to the target phone number.
	 *
	 * @param string $phone        Phone number in E.164 or local format.
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return object Normalized WhatsApp lookup result.
	 */
	public function check_whatsapp( $phone, $country_code = '' ) {
		$api_key          = trim( (string) $this->get_apikey() );
		$normalized_phone = $this->normalize_phone_string( $phone );
		$resolved_country = ! empty( $country_code ) ? strtoupper( trim( (string) $country_code ) ) : strtoupper( trim( (string) $this->get_country_code() ) );

		if ( 0 === strpos( $normalized_phone, '+' ) ) {
			$resolved_country = '';
		}

		if ( '' === $api_key || '' === $normalized_phone ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => 'Missing API key or phone number.',
			);
		}

		$cache_key = $this->build_cache_key( 'whatsapp', $normalized_phone . '|' . $resolved_country );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return (object) $cached;
		}

		$query_args = array(
			'api_key'      => $api_key,
			'phone_number' => $normalized_phone,
		);
		if ( ! empty( $resolved_country ) && '0' !== $resolved_country ) {
			$query_args['country_code'] = $resolved_country;
		}

		$url    = add_query_arg( rawurlencode_deep( $query_args ), $this->wa_endpoint );
		$result = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->get_timeout(),
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => $result->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( 401 === $http_code || 403 === $http_code ) {
			update_option( 'prop-api-key-invalid', 1 );
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'auth_error'    => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Invalid API key.',
			);
		}

		if ( 402 === $http_code ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'credit_error'  => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Insufficient WhatsApp API credits.',
			);
		}

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'WhatsApp verification service unavailable.',
			);
		}

		if ( isset( $decoded['status'] ) && false === $decoded['status'] && ! isset( $decoded['numberstatus'] ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'WhatsApp verification inconclusive.',
			);
		}

		$on_whatsapp = false;
		if ( isset( $decoded['numberstatus'] ) ) {
			$on_whatsapp = (bool) $decoded['numberstatus'];
		} elseif ( isset( $decoded['registered'] ) ) {
			$on_whatsapp = (bool) $decoded['registered'];
		} elseif ( isset( $decoded['is_whatsapp'] ) ) {
			$on_whatsapp = (bool) $decoded['is_whatsapp'];
		}

		$is_business  = ! empty( $decoded['businessnumber'] ) || ! empty( $decoded['is_business'] );
		$account_type = $on_whatsapp ? ( $is_business ? 'business' : 'personal' ) : 'none';

		$normalized = array(
			'ok'               => true,
			'network_error'    => false,
			'on_whatsapp'      => $on_whatsapp,
			'is_business'      => $is_business,
			'account_type'     => $account_type,
			'phone_number'     => $normalized_phone,
			'formatted_number' => isset( $decoded['formatted_number'] ) ? (string) $decoded['formatted_number'] : $normalized_phone,
			'carrier'          => isset( $decoded['carrier'] ) ? (string) $decoded['carrier'] : '',
			'linetype'         => isset( $decoded['linetype'] ) ? (string) $decoded['linetype'] : '',
			'countrycode'      => isset( $decoded['countrycode'] ) ? (string) $decoded['countrycode'] : $resolved_country,
		);

		$this->set_cached( $cache_key, $normalized );
		return (object) $normalized;
	}

	/**
	 * Verify email syntax, MX/mailbox deliverability, and disposable domain signals.
	 *
	 * @param string $email Email address to verify.
	 * @return object Normalized email verification result.
	 */
	public function verify_email( $email ) {
		$api_key       = trim( (string) $this->get_apikey() );
		$cleaned_email = strtolower( trim( (string) $email ) );

		if ( '' === $api_key || '' === $cleaned_email ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => 'Missing API key or email address.',
			);
		}

		$cache_key = $this->build_cache_key( 'email', $cleaned_email );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return (object) $cached;
		}

		$url = add_query_arg(
			rawurlencode_deep(
				array(
					'api_key' => $api_key,
					'email'   => $cleaned_email,
				)
			),
			$this->email_endpoint
		);

		$result = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->get_timeout(),
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => $result->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( 401 === $http_code || 403 === $http_code ) {
			update_option( 'prop-api-key-invalid', 1 );
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'auth_error'    => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Invalid API key.',
			);
		}

		if ( 402 === $http_code ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'credit_error'  => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Insufficient email API credits.',
			);
		}

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'http_code'     => $http_code,
				'error'         => 'Email verification service unavailable.',
			);
		}

		// status=false indicates an upstream lookup failure / invalid key / depleted credits (credit refunded).
		if ( isset( $decoded['status'] ) && false === $decoded['status'] ) {
			if ( ! isset( $decoded['balance'] ) && ! isset( $decoded['reason_code'] ) ) {
				update_option( 'prop-api-key-invalid', 1 );
			}
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'reason_code'   => isset( $decoded['reason_code'] ) ? (string) $decoded['reason_code'] : 'lookup_failed',
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Email verification temporarily unavailable.',
			);
		}

		$verdict       = isset( $decoded['verdict'] ) ? strtolower( (string) $decoded['verdict'] ) : 'unknown';
		$reason_code   = isset( $decoded['reason_code'] ) ? strtolower( (string) $decoded['reason_code'] ) : '';
		$email_format  = isset( $decoded['emailformat'] ) ? (bool) $decoded['emailformat'] : true;
		$email_exist   = isset( $decoded['emailexist'] ) ? (bool) $decoded['emailexist'] : true;
		$is_disposable = ( false !== strpos( $reason_code, 'disposable' ) ) || ! empty( $decoded['disposable'] ) || ! empty( $decoded['is_disposable'] );

		$normalized = array(
			'ok'            => true,
			'network_error' => false,
			'email'         => $cleaned_email,
			'verdict'       => $verdict,
			'reason_code'   => $reason_code,
			'emailformat'   => $email_format,
			'emailexist'    => $email_exist,
			'is_disposable' => $is_disposable,
			'balance'       => isset( $decoded['balance'] ) ? (int) $decoded['balance'] : null,
		);

		$this->set_cached( $cache_key, $normalized );
		return (object) $normalized;
	}

	/**
	 * Lookup registered Caller ID (CNAM) subscriber name & carrier metadata.
	 *
	 * @param string $phone        Phone number in E.164 or local format.
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return object Normalized Caller ID lookup result.
	 */
	public function lookup_caller_id( $phone, $country_code = '' ) {
		$api_key          = trim( (string) $this->get_apikey() );
		$normalized_phone = $this->normalize_phone_string( $phone );
		$resolved_country = ! empty( $country_code ) ? strtoupper( trim( (string) $country_code ) ) : strtoupper( trim( (string) $this->get_country_code() ) );

		if ( 0 === strpos( $normalized_phone, '+' ) ) {
			$resolved_country = '';
		}

		if ( '' === $api_key || '' === $normalized_phone ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => 'Missing API key or phone number.',
			);
		}

		$cache_key = $this->build_cache_key( 'callerid', $normalized_phone . '|' . $resolved_country );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return (object) $cached;
		}

		$query_args = array(
			'api_key'      => $api_key,
			'phone_number' => $normalized_phone,
		);
		if ( ! empty( $resolved_country ) && '0' !== $resolved_country ) {
			$query_args['country_code'] = $resolved_country;
		}

		$url    = add_query_arg( rawurlencode_deep( $query_args ), $this->caller_id_endpoint );
		$result = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->get_timeout(),
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept'    => 'application/json',
					'X-API-KEY' => $api_key,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => $result->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( 401 === $http_code || 403 === $http_code ) {
			update_option( 'prop-api-key-invalid', 1 );
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'auth_error'    => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Invalid API key.',
			);
		}

		if ( 402 === $http_code ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'credit_error'  => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Insufficient Caller ID API credits.',
			);
		}

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Caller ID service unavailable.',
			);
		}

		$payload = ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) ? $decoded['data'] : $decoded;
		if ( isset( $payload['status'] ) && false === $payload['status'] && ! isset( $payload['caller_name'] ) && ! isset( $payload['name'] ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => isset( $payload['error'] ) ? (string) $payload['error'] : 'Caller ID lookup inconclusive.',
			);
		}

		$caller_name = '';
		if ( ! empty( $payload['caller_name'] ) ) {
			$caller_name = trim( (string) $payload['caller_name'] );
		} elseif ( ! empty( $payload['name'] ) ) {
			$caller_name = trim( (string) $payload['name'] );
		}

		$normalized = array(
			'ok'           => true,
			'network_error'=> false,
			'found'        => ( '' !== $caller_name ),
			'caller_name'  => $caller_name,
			'caller_type'  => isset( $payload['caller_type'] ) ? (string) $payload['caller_type'] : '',
			'carrier'      => isset( $payload['carrier'] ) ? (string) $payload['carrier'] : '',
			'linetype'     => isset( $payload['linetype'] ) ? (string) $payload['linetype'] : ( isset( $payload['line_type'] ) ? (string) $payload['line_type'] : '' ),
			'countrycode'  => isset( $payload['countrycode'] ) ? (string) $payload['countrycode'] : $resolved_country,
			'location'     => isset( $payload['location'] ) ? (string) $payload['location'] : '',
			'phone_number' => $normalized_phone,
		);

		$this->set_cached( $cache_key, $normalized );
		return (object) $normalized;
	}

	/**
	 * Lookup shopper IP geolocation & datacenter/VPN/hosting signals via Edge GeoIP (`ipapi.proweblook.com`).
	 * Automatically skips private/loopback/reserved IPs on local development servers.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return object Normalized IP Geo result.
	 */
	public function lookup_ip_geo( $ip ) {
		$api_key  = trim( (string) $this->get_apikey() );
		$clean_ip = trim( (string) $ip );

		if ( '' === $api_key || '' === $clean_ip ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => 'Missing API key or IP address.',
			);
		}

		if ( false === filter_var( $clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return (object) array(
				'ok'              => false,
				'network_error'   => true,
				'skipped_private' => true,
				'error'           => 'Private, loopback, or reserved IP address skipped.',
			);
		}

		$cache_key = $this->build_cache_key( 'ipgeo', $clean_ip );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return (object) $cached;
		}

		$url    = add_query_arg(
			rawurlencode_deep(
				array(
					'api_key' => $api_key,
					'ip'      => $clean_ip,
				)
			),
			$this->ip_geo_endpoint
		);
		$result = wp_remote_get(
			$url,
			array(
				'timeout'    => min( 8, $this->get_timeout() ),
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => $result->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Edge GeoIP lookup unavailable.',
			);
		}

		$payload = ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) ? $decoded['data'] : $decoded;
		if ( isset( $payload['status'] ) && false === $payload['status'] ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => isset( $payload['error'] ) ? (string) $payload['error'] : 'Edge GeoIP lookup failed.',
			);
		}

		$raw_cc = '';
		if ( ! empty( $payload['country_code'] ) ) {
			$raw_cc = (string) $payload['country_code'];
		} elseif ( ! empty( $payload['countryCode'] ) ) {
			$raw_cc = (string) $payload['countryCode'];
		} elseif ( ! empty( $payload['country'] ) && 2 === strlen( trim( (string) $payload['country'] ) ) ) {
			$raw_cc = (string) $payload['country'];
		}
		$country_code = strtoupper( substr( trim( $raw_cc ), 0, 2 ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			$country_code = '';
		}

		$isp = '';
		if ( ! empty( $payload['isp'] ) ) {
			$isp = (string) $payload['isp'];
		} elseif ( ! empty( $payload['org'] ) ) {
			$isp = (string) $payload['org'];
		} elseif ( ! empty( $payload['as_org'] ) ) {
			$isp = (string) $payload['as_org'];
		}

		$asn          = isset( $payload['asn'] ) ? (string) $payload['asn'] : ( isset( $payload['as'] ) ? (string) $payload['as'] : '' );
		$combined_org = strtolower( $isp . ' ' . $asn );

		$proxy_keywords = array(
			'amazonaws',
			'amazon.com',
			'digitalocean',
			'google cloud',
			'linode',
			'akamai',
			'hetzner',
			'ovh',
			'vultr',
			'choopa',
			'm247',
			'datacamp',
			'tzulo',
			'leaseweb',
			'contabo',
			'scaleway',
			'nordvpn',
			'expressvpn',
			'surfshark',
			'protonvpn',
			'mullvad',
			'tor exit',
			'datacenter',
			'hosting',
			'vps',
			'proxy',
		);

		$is_hosting_or_proxy = ! empty( $payload['proxy'] ) || ! empty( $payload['hosting'] ) || ! empty( $payload['vpn'] );
		if ( ! $is_hosting_or_proxy && '' !== trim( $combined_org ) ) {
			foreach ( $proxy_keywords as $kw ) {
				if ( false !== strpos( $combined_org, $kw ) ) {
					$is_hosting_or_proxy = true;
					break;
				}
			}
		}

		$normalized = array(
			'ok'                  => true,
			'network_error'       => false,
			'ip'                  => isset( $payload['ip'] ) ? (string) $payload['ip'] : $clean_ip,
			'country_code'        => $country_code,
			'country_name'        => isset( $payload['country_name'] ) ? (string) $payload['country_name'] : ( isset( $payload['country'] ) ? (string) $payload['country'] : '' ),
			'region'              => isset( $payload['region'] ) ? (string) $payload['region'] : ( isset( $payload['region_name'] ) ? (string) $payload['region_name'] : '' ),
			'city'                => isset( $payload['city'] ) ? (string) $payload['city'] : '',
			'isp'                 => $isp,
			'asn'                 => $asn,
			'timezone'            => isset( $payload['timezone'] ) ? (string) $payload['timezone'] : '',
			'is_hosting_or_proxy' => $is_hosting_or_proxy,
		);

		$this->set_cached( $cache_key, $normalized );
		return (object) $normalized;
	}

	/**
	 * Check a domain or IP address against real-time DNSBL/RBL spam & malware blacklists.
	 *
	 * @param string $target      Domain name or IP address.
	 * @param string $target_type One of `domain`, `ip`, `url`, `auto`.
	 * @return object Normalized DNSBL blacklist lookup result.
	 */
	public function check_domain_blacklist( $target, $target_type = 'domain' ) {
		$api_key      = trim( (string) $this->get_apikey() );
		$clean_target = strtolower( trim( (string) $target ) );
		$clean_type   = in_array( $target_type, array( 'domain', 'ip', 'url', 'auto' ), true ) ? $target_type : 'auto';

		if ( '' === $api_key || '' === $clean_target ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => 'Missing API key or target domain/IP.',
			);
		}

		$cache_key = $this->build_cache_key( 'dnsbl', $clean_type . '|' . $clean_target );
		$cached    = $this->get_cached( $cache_key );
		if ( is_array( $cached ) ) {
			return (object) $cached;
		}

		$url    = add_query_arg(
			rawurlencode_deep(
				array(
					'api_key'     => $api_key,
					'target'      => $clean_target,
					'target_type' => $clean_type,
				)
			),
			$this->blacklist_endpoint
		);
		$result = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->get_timeout(),
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => $result->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $result );
		$decoded   = json_decode( wp_remote_retrieve_body( $result ), true );

		if ( 401 === $http_code || 403 === $http_code ) {
			update_option( 'prop-api-key-invalid', 1 );
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'auth_error'    => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Invalid API key.',
			);
		}

		if ( 402 === $http_code ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'credit_error'  => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'Insufficient Blacklist API credits.',
			);
		}

		if ( $http_code < 200 || $http_code >= 300 || ! is_array( $decoded ) ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'http_code'     => $http_code,
				'error'         => isset( $decoded['error'] ) ? (string) $decoded['error'] : 'DNSBL Blacklist service unavailable.',
			);
		}

		$payload = ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) ? $decoded['data'] : $decoded;
		if ( isset( $payload['status'] ) && false === $payload['status'] ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'error'         => isset( $payload['error'] ) ? (string) $payload['error'] : 'DNSBL lookup failed.',
			);
		}

		$listed_count = 0;
		if ( isset( $payload['listed_count'] ) ) {
			$listed_count = (int) $payload['listed_count'];
		} elseif ( isset( $payload['listedCount'] ) ) {
			$listed_count = (int) $payload['listedCount'];
		} elseif ( ! empty( $payload['listed'] ) ) {
			$listed_count = 1;
		}

		$checked_count = isset( $payload['total_checked'] ) ? (int) $payload['total_checked'] : ( isset( $payload['sources_checked'] ) ? (int) $payload['sources_checked'] : 0 );

		$normalized = array(
			'ok'            => true,
			'network_error' => false,
			'target'        => $clean_target,
			'listed'        => ( $listed_count > 0 || ! empty( $payload['listed'] ) ),
			'listed_count'  => $listed_count,
			'checked_count' => $checked_count,
		);

		$this->set_cached( $cache_key, $normalized );
		return (object) $normalized;
	}

	/**
	 * Determine whether a domain is a well-known consumer webmail provider so DNSBL
	 * domain checks only run on custom/corporate domains and never waste merchant credits.
	 *
	 * @param string $domain Domain name.
	 * @return bool
	 */
	public function is_free_webmail_domain( $domain ) {
		$clean = strtolower( trim( (string) $domain ) );
		$free  = array(
			'gmail.com',
			'googlemail.com',
			'yahoo.com',
			'yahoo.co.in',
			'yahoo.co.uk',
			'ymail.com',
			'outlook.com',
			'hotmail.com',
			'live.com',
			'msn.com',
			'icloud.com',
			'me.com',
			'mac.com',
			'aol.com',
			'proton.me',
			'protonmail.com',
			'zoho.com',
			'zohomail.in',
			'gmx.com',
			'yandex.com',
			'mail.com',
			'rediffmail.com',
			'fastmail.com',
		);
		return in_array( $clean, $free, true );
	}

	/**
	 * Fetch remaining credit balances across Phone, WhatsApp, and Email products.
	 *
	 * @param bool $force_refresh Skip transient cache when true.
	 * @return array
	 */
	public function get_balances( $force_refresh = false ) {
		$api_key = trim( (string) $this->get_apikey() );
		if ( '' === $api_key ) {
			return array(
				'ok'    => false,
				'error' => __( 'API Key is not configured yet.', 'proweblook-phone-validator' ),
			);
		}

		$bal_cache_key = 'prop_v2_bal_' . substr( hash( 'sha256', $api_key ), 0, 20 );
		if ( ! $force_refresh ) {
			$cached = get_transient( $bal_cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		// Primary: unified endpoint returning account_email + tagged-pool balances for email, phone, whatsapp.
		$primary = wp_remote_post(
			$this->zapier_balance_endpoint,
			array(
				'timeout'    => 10,
				'user-agent' => $this->get_user_agent(),
				'headers'    => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'       => '{}',
			)
		);

		if ( ! is_wp_error( $primary ) ) {
			$code = (int) wp_remote_retrieve_response_code( $primary );
			$body = json_decode( wp_remote_retrieve_body( $primary ), true );

			if ( 200 === $code && is_array( $body ) && ! empty( $body['ok'] ) && isset( $body['data'] ) && is_array( $body['data'] ) ) {
				$d   = $body['data'];
				$out = array(
					'ok'            => true,
					'account_email' => isset( $d['account_email'] ) ? (string) $d['account_email'] : '',
					'phone'         => isset( $d['phone']['amount'] ) ? (int) $d['phone']['amount'] : 0,
					'whatsapp'      => isset( $d['whatsapp']['amount'] ) ? (int) $d['whatsapp']['amount'] : 0,
					'email'         => isset( $d['email']['amount'] ) ? (int) $d['email']['amount'] : 0,
					'caller'        => isset( $d['caller']['amount'] ) ? (int) $d['caller']['amount'] : null,
					'blacklist'     => isset( $d['blacklist']['amount'] ) ? (int) $d['blacklist']['amount'] : null,
				);
				update_option( 'prop-api-key-invalid', 0 );
				set_transient( $bal_cache_key, $out, 60 );
				return $out;
			}

			if ( 401 === $code || 403 === $code ) {
				return array(
					'ok'    => false,
					'error' => __( 'Invalid ProWebLook API Key. Please copy your key from proweblook.com/user/my_profile.', 'proweblook-phone-validator' ),
				);
			}
		}

		// Fallback: monolith checkbalance endpoint.
		$fallback_url = add_query_arg(
			rawurlencode_deep( array( 'api_key' => $api_key ) ),
			$this->legacy_balance_endpoint
		);
		$fallback     = wp_remote_get(
			$fallback_url,
			array(
				'timeout'    => 10,
				'user-agent' => $this->get_user_agent(),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( ! is_wp_error( $fallback ) ) {
			$f_body = json_decode( wp_remote_retrieve_body( $fallback ), true );
			if ( is_array( $f_body ) && ! empty( $f_body['status'] ) ) {
				$out = array(
					'ok'            => true,
					'account_email' => '',
					'phone'         => isset( $f_body['phonebalance'] ) ? (int) $f_body['phonebalance'] : ( isset( $f_body['phone_balance'] ) ? (int) $f_body['phone_balance'] : 0 ),
					'whatsapp'      => isset( $f_body['wabalance'] ) ? (int) $f_body['wabalance'] : ( isset( $f_body['wa_balance'] ) ? (int) $f_body['wa_balance'] : 0 ),
					'email'         => isset( $f_body['email_balance'] ) ? (int) $f_body['email_balance'] : null,
					'caller'        => isset( $f_body['caller_balance'] ) ? (int) $f_body['caller_balance'] : null,
					'blacklist'     => isset( $f_body['blacklist_balance'] ) ? (int) $f_body['blacklist_balance'] : null,
				);
				update_option( 'prop-api-key-invalid', 0 );
				set_transient( $bal_cache_key, $out, 60 );
				return $out;
			}
		}

		return array(
			'ok'    => false,
			'error' => __( 'Could not reach ProWebLook balance service. Please verify your API Key and server connectivity.', 'proweblook-phone-validator' ),
		);
	}

	/**
	 * Clear all cached validation & balance transients created by this plugin.
	 *
	 * @return int Number of option rows deleted.
	 */
	public function clear_cache() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! isset( $wpdb->options ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_prop_v2_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_prop_v2_' ) . '%'
			)
		);
		return $deleted;
	}

	/**
	 * Normalize a phone number string.
	 *
	 * @param string $phone Raw phone string.
	 * @return string
	 */
	public function normalize_phone_string( $phone ) {
		$phone = trim( (string) $phone );
		$phone = preg_replace( '/[\s().-]+/', '', $phone );
		$phone = preg_replace( '/^00/', '+', (string) $phone );
		return (string) $phone;
	}

	/**
	 * Build a hashed transient key (< 45 chars for all WP versions).
	 *
	 * @param string $service Service identifier.
	 * @param string $target  Target payload string.
	 * @return string
	 */
	protected function build_cache_key( $service, $target ) {
		$key_fp = substr( hash( 'sha256', (string) $this->get_apikey() ), 0, 8 );
		return 'prop_v2_' . substr( hash( 'sha256', $service . '|' . strtolower( (string) $target ) . '|' . $key_fp ), 0, 28 );
	}

	/**
	 * Read from object cache or WordPress transient.
	 *
	 * @param string $cache_key Transient key.
	 * @return array|null
	 */
	protected function get_cached( $cache_key ) {
		$mem = wp_cache_get( $cache_key, 'prop' );
		if ( is_array( $mem ) ) {
			return $mem;
		}
		$trans = get_transient( $cache_key );
		if ( is_array( $trans ) ) {
			wp_cache_set( $cache_key, $trans, 'prop', 3600 );
			return $trans;
		}
		return null;
	}

	/**
	 * Store result in object cache and WordPress transient.
	 *
	 * @param string $cache_key Transient key.
	 * @param array  $data      Data to cache.
	 * @return void
	 */
	protected function set_cached( $cache_key, $data ) {
		/**
		 * Filters the validation transient cache TTL in seconds. Default: 12 hours (43200s).
		 *
		 * @param int $ttl Cache duration in seconds. Set 0 to disable caching.
		 */
		$ttl = (int) apply_filters( 'prop_cache_ttl', 12 * HOUR_IN_SECONDS );
		if ( $ttl <= 0 ) {
			return;
		}
		wp_cache_set( $cache_key, $data, 'prop', $ttl );
		set_transient( $cache_key, $data, $ttl );
	}

	/**
	 * Get User-Agent header string.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		$ver = defined( 'PROP_PLUGIN_CURRENT_VERSION' ) ? PROP_PLUGIN_CURRENT_VERSION : '2.0.0';
		return 'ProWebLook-WordPress/' . $ver . '; ' . home_url();
	}

	/**
	 * Get HTTP timeout in seconds.
	 *
	 * @return int
	 */
	protected function get_timeout() {
		return (int) apply_filters( 'prop_api_timeout', $this->timeout );
	}

	/**
	 * Get the phone validation endpoint.
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * Set the phone validation endpoint.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @return string
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = (string) $endpoint;
		return $this->get_endpoint();
	}

	/**
	 * Get the phone number.
	 *
	 * @return string|null
	 */
	public function get_phone() {
		return $this->phone;
	}

	/**
	 * Set the phone number.
	 *
	 * @param string $phone Raw phone number.
	 * @return string
	 */
	public function set_phone( $phone ) {
		$this->phone = $this->normalize_phone_string( $phone );
		return (string) $this->get_phone();
	}

	/**
	 * Get default ISO country code.
	 *
	 * @return string
	 */
	public function get_country_code() {
		return $this->country_code;
	}

	/**
	 * Set default ISO country code.
	 *
	 * @param string $code 2-letter ISO code.
	 * @return string
	 */
	public function set_country_code( $code ) {
		$this->country_code = (string) $code;
		return $this->get_country_code();
	}

	/**
	 * Get API key.
	 *
	 * @return string|null
	 */
	public function get_apikey() {
		return $this->apikey;
	}

	/**
	 * Set API key.
	 *
	 * @param string $apikey ProWebLook API key.
	 * @return string
	 */
	public function set_apikey( $apikey ) {
		$this->apikey = trim( (string) $apikey );
		return (string) $this->get_apikey();
	}

	/**
	 * Get last response object.
	 *
	 * @return object|null
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Set last response object.
	 *
	 * @param object|array $response Response payload.
	 * @return object
	 */
	public function set_response( $response ) {
		$this->response = (object) $response;
		return $this->get_response();
	}
}
