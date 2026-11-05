<?php
/**
 * Unified Phone, WhatsApp & Email Validator Engine.
 * Preserves 100% backward compatibility with PROP_Phone_Validator v1.x.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Phone_Validator
 */
class PROP_Phone_Validator {

	/**
	 * Whether the last checked target is valid.
	 *
	 * @var bool
	 */
	protected $is_valid = true;

	/**
	 * Phone number to validate.
	 *
	 * @var string
	 */
	protected $phone = '';

	/**
	 * Phone number status code.
	 *
	 * @var string
	 */
	protected $phone_status = '';

	/**
	 * Last validation error message suitable for displaying to end users.
	 *
	 * @var string
	 */
	protected $error_message = '';

	/**
	 * Whether VoIP numbers should be rejected.
	 *
	 * @var bool
	 */
	protected $block_voip = false;

	/**
	 * Whether disposable emails should be rejected.
	 *
	 * @var bool
	 */
	protected $block_disposable_email = true;

	/**
	 * Whether custom/corporate email domains should also be screened against DNSBL blacklists.
	 *
	 * @var bool
	 */
	protected $check_dnsbl = false;

	/**
	 * Whether DNSBL-listed email domains should be rejected on form/checkout submission.
	 *
	 * @var bool
	 */
	protected $block_dnsbl = false;

	/**
	 * API client instance.
	 *
	 * @var PROP_API|null
	 */
	protected $api = null;

	/**
	 * Last phone API response object.
	 *
	 * @var object|null
	 */
	protected $last_phone_result = null;

	/**
	 * Last WhatsApp API response object.
	 *
	 * @var object|null
	 */
	protected $last_whatsapp_result = null;

	/**
	 * Last Email API response object.
	 *
	 * @var object|null
	 */
	protected $last_email_result = null;

	/**
	 * Last Caller ID (CNAM) API response object.
	 *
	 * @var object|null
	 */
	protected $last_caller_id_result = null;

	/**
	 * Last Edge IP Geolocation response object.
	 *
	 * @var object|null
	 */
	protected $last_ip_geo_result = null;

	/**
	 * Last Domain DNSBL Blacklist response object.
	 *
	 * @var object|null
	 */
	protected $last_blacklist_result = null;

	/**
	 * Validate a phone number (preserves v1.x signature and PROP_phone_valid filter).
	 *
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return bool
	 */
	public function validate( $country_code = '' ) {
		$this->error_message     = '';
		$this->phone_status      = '';
		$this->last_phone_result = null;

		$api = $this->get_api();
		if ( ! $api ) {
			$this->set_is_valid( true );
			return true;
		}

		$api->set_phone( $this->get_phone() );
		$result   = $api->request( $country_code );
		$response = $api->get_response();

		if ( ! empty( $result->status ) ) {
			$this->set_phone_status( $result->status );
		}

		$this->last_phone_result = $response;
		$this->set_is_valid( true );

		// Fail-open on transient network failures so customer checkouts are never blocked by upstream downtime.
		if ( null === $result || null === $response ) {
			$this->set_is_valid( true );
			return (bool) apply_filters( 'PROP_phone_valid', $this->get_is_valid(), $response );
		}

		$valid_statuses = array(
			'VALID_CONFIRMED',
			'VALID_UNCONFIRMED',
			'DELAYED',
			'API_KEY_INVALID_OR_DEPLETED',
			'RATE_LIMIT_EXCEEDED',
		);

		if ( ! in_array( $this->get_phone_status(), $valid_statuses, true ) ) {
			$this->set_is_valid( false );
			$this->error_message = __( 'Please enter a valid, callable phone number.', 'proweblook-phone-validator' );
		}

		if ( 'API_KEY_INVALID_OR_DEPLETED' === $this->get_phone_status() ) {
			update_option( 'prop-api-key-invalid', 1 );
		}

		// Optional VoIP / virtual number blocking.
		if ( $this->get_is_valid() && $this->block_voip && ! empty( $response->linetype ) ) {
			$linetype = strtolower( trim( (string) $response->linetype ) );
			if ( 'voip' === $linetype ) {
				$this->set_is_valid( false );
				$this->set_phone_status( 'VOIP_BLOCKED' );
				$this->error_message = __( 'Virtual or VoIP phone numbers are not accepted. Please enter a mobile or landline number.', 'proweblook-phone-validator' );
			}
		}

		/**
		 * Filters whether the phone number is valid.
		 *
		 * @param bool        $is_valid Whether the number is valid.
		 * @param object|null $response Raw API response object.
		 */
		return (bool) apply_filters( 'PROP_phone_valid', $this->get_is_valid(), $response );
	}

	/**
	 * Silently check whether a phone number is registered on WhatsApp.
	 * Fails open if upstream network/credits fail so store checkouts never break unexpectedly.
	 *
	 * @param string $phone        Phone number to check.
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return bool
	 */
	public function validate_whatsapp( $phone, $country_code = '' ) {
		$this->error_message        = '';
		$this->last_whatsapp_result = null;

		$api = $this->get_api();
		if ( ! $api ) {
			return true;
		}

		$res                        = $api->check_whatsapp( $phone, $country_code );
		$this->last_whatsapp_result = $res;

		if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
			return (bool) apply_filters( 'prop_whatsapp_valid', true, $res );
		}

		$is_valid = ! empty( $res->on_whatsapp );
		if ( ! $is_valid ) {
			$this->error_message = __( 'Please enter an active WhatsApp phone number so we can confirm your order.', 'proweblook-phone-validator' );
		}

		/**
		 * Filters whether the WhatsApp check passed.
		 *
		 * @param bool   $is_valid Whether the phone number has an active WhatsApp account.
		 * @param object $res      Normalized WhatsApp response object.
		 */
		return (bool) apply_filters( 'prop_whatsapp_valid', $is_valid, $res );
	}

	/**
	 * Validate an email address for deliverability and disposable domain risk.
	 * Fails open on upstream network/credit errors.
	 *
	 * @param string    $email            Email address to check.
	 * @param bool|null $block_disposable Override disposable blocking setting.
	 * @return bool
	 */
	public function validate_email_address( $email, $block_disposable = null ) {
		$this->error_message         = '';
		$this->last_email_result     = null;
		$this->last_blacklist_result = null;

		$cleaned = trim( (string) $email );
		if ( '' === $cleaned ) {
			return true;
		}

		if ( ! is_email( $cleaned ) ) {
			$this->error_message = __( 'Please enter a valid email address.', 'proweblook-phone-validator' );
			return false;
		}

		$api = $this->get_api();
		if ( ! $api ) {
			return true;
		}

		$res                     = $api->verify_email( $cleaned );
		$this->last_email_result = $res;

		if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
			return (bool) apply_filters( 'prop_email_valid', true, $res );
		}

		$reject_disposable = ( null !== $block_disposable ) ? (bool) $block_disposable : $this->block_disposable_email;
		$is_valid          = true;

		if ( false === $res->emailformat || 'invalid_format' === $res->verdict ) {
			$is_valid            = false;
			$this->error_message = __( 'Please enter a properly formatted email address.', 'proweblook-phone-validator' );
		} elseif ( $reject_disposable && ! empty( $res->is_disposable ) ) {
			$is_valid            = false;
			$this->error_message = __( 'Disposable or temporary email addresses are not accepted. Please use a permanent email address.', 'proweblook-phone-validator' );
		} elseif ( 'undeliverable' === $res->verdict || ( false === $res->emailexist && 'unknown' !== $res->verdict ) ) {
			$is_valid            = false;
			$this->error_message = __( 'This email address could not be verified as deliverable. Please check for typos.', 'proweblook-phone-validator' );
		}

		// Optional corporate/custom Domain DNSBL Blacklist screening (skips consumer webmail like Gmail/Yahoo).
		if ( $is_valid && $this->check_dnsbl ) {
			$dnsbl_ok = $this->validate_domain_blacklist( $cleaned, $this->block_dnsbl, true );
			if ( ! $dnsbl_ok ) {
				$is_valid = false;
			}
		}

		/**
		 * Filters whether the email address passed ProWebLook verification.
		 *
		 * @param bool   $is_valid Whether the email address is accepted.
		 * @param object $res      Normalized email verification response object.
		 */
		return (bool) apply_filters( 'prop_email_valid', $is_valid, $res );
	}

	/**
	 * Screen an email address's domain (or raw domain) against real-time DNSBL/RBL blacklists.
	 * Fails open on network/credit errors and optionally skips consumer webmail domains.
	 *
	 * @param string    $email_or_domain   Email address or domain name.
	 * @param bool|null $block_listed      Whether to reject listed domains (defaults to $this->block_dnsbl).
	 * @param bool      $skip_free_webmail Whether to skip known free webmail providers (default true).
	 * @return bool
	 */
	public function validate_domain_blacklist( $email_or_domain, $block_listed = null, $skip_free_webmail = true ) {
		$this->last_blacklist_result = null;

		$raw = strtolower( trim( (string) $email_or_domain ) );
		if ( '' === $raw ) {
			return true;
		}

		$domain = $raw;
		if ( false !== strpos( $raw, '@' ) ) {
			$parts  = explode( '@', $raw );
			$domain = trim( (string) end( $parts ) );
		}
		$domain = preg_replace( '#^https?://#i', '', $domain );
		$domain = trim( strtok( (string) $domain, '/' ), '.' );

		if ( '' === $domain || false === strpos( $domain, '.' ) ) {
			return true;
		}

		$api = $this->get_api();
		if ( ! $api ) {
			return true;
		}

		if ( $skip_free_webmail && $api->is_free_webmail_domain( $domain ) ) {
			$this->last_blacklist_result = (object) array(
				'ok'                   => true,
				'network_error'        => false,
				'skipped_free_webmail' => true,
				'target'               => $domain,
				'listed'               => false,
				'listed_count'         => 0,
				'checked_count'        => 0,
			);
			return true;
		}

		$res                         = $api->check_domain_blacklist( $domain, 'domain' );
		$this->last_blacklist_result = $res;

		if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
			return (bool) apply_filters( 'prop_blacklist_valid', true, $res );
		}

		$should_block = ( null !== $block_listed ) ? (bool) $block_listed : $this->block_dnsbl;
		$is_valid     = true;

		if ( $should_block && ! empty( $res->listed ) ) {
			$is_valid            = false;
			$this->error_message = __( 'This email domain is listed on spam or security blacklists. Please use a clean business or personal email address.', 'proweblook-phone-validator' );
		}

		/**
		 * Filters whether a domain passed DNSBL blacklist validation.
		 *
		 * @param bool   $is_valid Whether the domain is accepted.
		 * @param object $res      Normalized blacklist lookup response object.
		 */
		return (bool) apply_filters( 'prop_blacklist_valid', $is_valid, $res );
	}

	/**
	 * Compare registered Caller ID (CNAM) subscriber name against customer / company name.
	 *
	 * @param string $caller_name  Registered CNAM name from telecom database.
	 * @param string $lead_name    Submitted customer / billing full name.
	 * @param string $company_name Optional submitted billing company name.
	 * @return string One of `match`, `partial`, `mismatch`, `unavailable`.
	 */
	public function compare_caller_name( $caller_name, $lead_name, $company_name = '' ) {
		$stop_words = array( 'llc', 'inc', 'ltd', 'pvt', 'limited', 'corp', 'corporation', 'company', 'co', 'gmbh', 'plc', 'mr', 'mrs', 'ms', 'dr' );

		$tokenize = function ( $str ) use ( $stop_words ) {
			$clean = strtolower( preg_replace( '/[^a-zA-Z0-9\s]/', ' ', (string) $str ) );
			$parts = preg_split( '/\s+/', trim( $clean ) );
			$out   = array();
			foreach ( (array) $parts as $p ) {
				if ( strlen( $p ) >= 2 && ! in_array( $p, $stop_words, true ) ) {
					$out[] = $p;
				}
			}
			return array_values( array_unique( $out ) );
		};

		$caller_tokens = $tokenize( $caller_name );
		$target_tokens = $tokenize( trim( (string) $lead_name . ' ' . (string) $company_name ) );

		if ( empty( $caller_tokens ) || empty( $target_tokens ) ) {
			return 'unavailable';
		}

		$caller_joined = implode( ' ', $caller_tokens );
		$target_joined = implode( ' ', $target_tokens );

		if ( strlen( $caller_joined ) >= 4 && strlen( $target_joined ) >= 4 ) {
			if ( false !== strpos( $caller_joined, $target_joined ) || false !== strpos( $target_joined, $caller_joined ) ) {
				return 'match';
			}
		}

		$shared      = array_intersect( $caller_tokens, $target_tokens );
		$shared_cnt  = count( $shared );
		$has_4char   = false;
		foreach ( $shared as $tok ) {
			if ( strlen( $tok ) >= 4 ) {
				$has_4char = true;
				break;
			}
		}

		if ( $shared_cnt >= 2 || ( 1 === $shared_cnt && $has_4char && ( count( $caller_tokens ) <= 2 || count( $target_tokens ) <= 2 ) ) ) {
			return 'match';
		}

		if ( $shared_cnt >= 1 ) {
			return 'partial';
		}

		return 'mismatch';
	}

	/**
	 * Lookup Caller ID (CNAM) and compute fuzzy subscriber name match against customer/company name.
	 *
	 * @param string $phone        Phone number.
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @param string $lead_name    Optional customer full name.
	 * @param string $company_name Optional customer company name.
	 * @return object
	 */
	public function lookup_caller_id_signal( $phone, $country_code = '', $lead_name = '', $company_name = '' ) {
		$this->last_caller_id_result = null;
		$api                         = $this->get_api();
		if ( ! $api ) {
			return (object) array(
				'ok'            => false,
				'network_error' => true,
				'name_match'    => 'unavailable',
			);
		}

		$res             = $api->lookup_caller_id( $phone, $country_code );
		$res->name_match = ( ! empty( $res->ok ) && ! empty( $res->found ) )
			? $this->compare_caller_name( $res->caller_name, $lead_name, $company_name )
			: 'unavailable';

		$this->last_caller_id_result = $res;

		/**
		 * Filters the Caller ID signal result.
		 *
		 * @param object $res          Caller ID result object including `name_match`.
		 * @param string $lead_name    Customer name compared against.
		 * @param string $company_name Company name compared against.
		 */
		return (object) apply_filters( 'prop_caller_id_result', $res, $lead_name, $company_name );
	}

	/**
	 * Lookup shopper IP geolocation and compare against expected billing/shipping country.
	 *
	 * @param string $ip               Shopper IPv4/IPv6 address.
	 * @param string $expected_country Expected 2-letter ISO country code (e.g. WooCommerce Billing Country).
	 * @return object
	 */
	public function lookup_ip_geo_signal( $ip, $expected_country = '' ) {
		$this->last_ip_geo_result = null;
		$api                      = $this->get_api();
		if ( ! $api ) {
			return (object) array(
				'ok'               => false,
				'network_error'    => true,
				'country_mismatch' => false,
			);
		}

		$res                   = $api->lookup_ip_geo( $ip );
		$exp                   = strtoupper( substr( trim( (string) $expected_country ), 0, 2 ) );
		$res->expected_country = $exp;
		$res->country_mismatch = ( ! empty( $res->ok ) && ! empty( $res->country_code ) && '' !== $exp && preg_match( '/^[A-Z]{2}$/', $exp ) && $res->country_code !== $exp );

		$this->last_ip_geo_result = $res;

		/**
		 * Filters the Edge IP Geo signal result.
		 *
		 * @param object $res              IP Geo result object including `country_mismatch`.
		 * @param string $expected_country Expected ISO country code.
		 */
		return (object) apply_filters( 'prop_ip_geo_result', $res, $expected_country );
	}

	/**
	 * Calculate a composite 0–100 Trust Score across up to 6 real-time ProWebLook signals.
	 *
	 * @param array $signals Associative array of normalized signal snapshots (`phone`, `whatsapp`, `email`, `caller_id`, `ip_geo`, `blacklist`).
	 * @return array { score: int, risk_level: string, reasons: string[], positives: string[] }
	 */
	public function calculate_trust_score( $signals = array() ) {
		$score     = 65;
		$reasons   = array();
		$positives = array();

		// 1. Phone Carrier & Line Type.
		if ( ! empty( $signals['phone'] ) && is_array( $signals['phone'] ) ) {
			$p        = $signals['phone'];
			$p_status = isset( $p['status'] ) ? strtoupper( (string) $p['status'] ) : '';
			$linetype = isset( $p['linetype'] ) ? strtolower( (string) $p['linetype'] ) : '';
			$is_voip  = ( false !== strpos( $linetype, 'voip' ) ) || ( false !== strpos( $linetype, 'virtual' ) ) || ( 'VOIP_BLOCKED' === $p_status );

			if ( 'INVALID' === $p_status ) {
				$score    -= 35;
				$reasons[] = __( 'Invalid phone number', 'proweblook-phone-validator' );
			} elseif ( $is_voip ) {
				$score    -= 18;
				$reasons[] = __( 'VoIP / virtual phone line', 'proweblook-phone-validator' );
			} elseif ( in_array( $p_status, array( 'VALID_CONFIRMED', 'VALID_UNCONFIRMED' ), true ) ) {
				$score      += 12;
				$positives[] = __( 'Verified mobile/landline carrier', 'proweblook-phone-validator' );
			}
		}

		// 2. Silent WhatsApp Presence.
		if ( ! empty( $signals['whatsapp'] ) && is_array( $signals['whatsapp'] ) ) {
			$w = $signals['whatsapp'];
			if ( isset( $w['status'] ) && 'active' === $w['status'] ) {
				$is_biz      = isset( $w['type'] ) && 'business' === $w['type'];
				$score      += $is_biz ? 12 : 10;
				$positives[] = $is_biz ? __( 'Active WhatsApp Business', 'proweblook-phone-validator' ) : __( 'Active WhatsApp', 'proweblook-phone-validator' );
			} elseif ( isset( $w['status'] ) && 'inactive' === $w['status'] ) {
				$score    -= 10;
				$reasons[] = __( 'Phone not registered on WhatsApp', 'proweblook-phone-validator' );
			}
		}

		// 3. Email Deliverability & Disposable Check.
		if ( ! empty( $signals['email'] ) && is_array( $signals['email'] ) ) {
			$e       = $signals['email'];
			$verdict = isset( $e['verdict'] ) ? strtolower( (string) $e['verdict'] ) : '';
			$reason  = isset( $e['reason'] ) ? strtolower( (string) $e['reason'] ) : '';

			if ( false !== strpos( $reason, 'disposable' ) || ! empty( $e['is_disposable'] ) ) {
				$score    -= 35;
				$reasons[] = __( 'Disposable / temporary email domain', 'proweblook-phone-validator' );
			} elseif ( 'undeliverable' === $verdict ) {
				$score    -= 25;
				$reasons[] = __( 'Undeliverable email mailbox', 'proweblook-phone-validator' );
			} elseif ( 'deliverable' === $verdict ) {
				$score      += 10;
				$positives[] = __( 'Deliverable email mailbox', 'proweblook-phone-validator' );
			}
		}

		// 4. Caller ID (CNAM) Name-Match.
		if ( ! empty( $signals['caller_id'] ) && is_array( $signals['caller_id'] ) ) {
			$c     = $signals['caller_id'];
			$match = isset( $c['name_match'] ) ? (string) $c['name_match'] : 'unavailable';
			if ( 'match' === $match ) {
				$score      += 12;
				$positives[] = __( 'Caller ID matches customer name', 'proweblook-phone-validator' );
			} elseif ( 'partial' === $match ) {
				$score      += 5;
				$positives[] = __( 'Caller ID partial name match', 'proweblook-phone-validator' );
			} elseif ( 'mismatch' === $match ) {
				$score    -= 20;
				$reasons[] = sprintf(
					/* translators: %s: registered CNAM subscriber name */
					__( 'Caller ID mismatch (registered to %s)', 'proweblook-phone-validator' ),
					isset( $c['caller_name'] ) ? (string) $c['caller_name'] : 'other subscriber'
				);
			}
		}

		// 5. Edge IP Geolocation & Billing-Country Mismatch / VPN-Proxy.
		if ( ! empty( $signals['ip_geo'] ) && is_array( $signals['ip_geo'] ) ) {
			$g = $signals['ip_geo'];
			if ( ! empty( $g['country_mismatch'] ) ) {
				$score    -= 16;
				$reasons[] = sprintf(
					/* translators: 1: IP country code, 2: billing country code */
					__( 'IP country (%1$s) differs from Billing country (%2$s)', 'proweblook-phone-validator' ),
					isset( $g['country_code'] ) ? (string) $g['country_code'] : '?',
					isset( $g['expected_country'] ) ? (string) $g['expected_country'] : '?'
				);
			} elseif ( ! empty( $g['country_code'] ) ) {
				$score      += 5;
				$positives[] = __( 'IP country matches billing country', 'proweblook-phone-validator' );
			}

			if ( ! empty( $g['is_hosting_or_proxy'] ) ) {
				$score    -= 14;
				$reasons[] = __( 'VPN / Datacenter / Hosting IP detected', 'proweblook-phone-validator' );
			}
		}

		// 6. Domain DNSBL Blacklist.
		if ( ! empty( $signals['blacklist'] ) && is_array( $signals['blacklist'] ) ) {
			$b = $signals['blacklist'];
			if ( ! empty( $b['listed'] ) ) {
				$score    -= 25;
				$reasons[] = sprintf(
					/* translators: %d: number of DNSBL blocklists */
					__( 'Email domain listed on %d DNSBL blacklist(s)', 'proweblook-phone-validator' ),
					isset( $b['listed_count'] ) ? max( 1, (int) $b['listed_count'] ) : 1
				);
			} elseif ( empty( $b['skipped_free_webmail'] ) ) {
				$score      += 4;
				$positives[] = __( 'Clean corporate domain reputation', 'proweblook-phone-validator' );
			}
		}

		$score = max( 0, min( 100, (int) round( $score ) ) );
		if ( $score >= 75 ) {
			$risk_level = 'trusted';
		} elseif ( $score >= 45 ) {
			$risk_level = 'review';
		} else {
			$risk_level = 'high_risk';
		}

		$summary = array(
			'score'      => $score,
			'risk_level' => $risk_level,
			'reasons'    => $reasons,
			'positives'  => $positives,
		);

		/**
		 * Filters the calculated 0–100 Trust Score summary.
		 *
		 * @param array $summary Calculated trust score array.
		 * @param array $signals Input signals array.
		 */
		return (array) apply_filters( 'prop_trust_score', $summary, $signals );
	}

	/**
	 * Configure whether VoIP phone numbers should be blocked.
	 *
	 * @param bool $block True to reject VoIP numbers.
	 * @return void
	 */
	public function set_block_voip( $block ) {
		$this->block_voip = (bool) $block;
	}

	/**
	 * Check whether VoIP blocking is enabled.
	 *
	 * @return bool
	 */
	public function get_block_voip() {
		return $this->block_voip;
	}

	/**
	 * Configure whether disposable emails should be blocked.
	 *
	 * @param bool $block True to reject disposable emails.
	 * @return void
	 */
	public function set_block_disposable_email( $block ) {
		$this->block_disposable_email = (bool) $block;
	}

	/**
	 * Configure whether custom/corporate email domains are checked against DNSBL blacklists.
	 *
	 * @param bool $check True to run DNSBL domain checks.
	 * @return void
	 */
	public function set_check_dnsbl( $check ) {
		$this->check_dnsbl = (bool) $check;
	}

	/**
	 * Check whether DNSBL domain screening is enabled.
	 *
	 * @return bool
	 */
	public function get_check_dnsbl() {
		return $this->check_dnsbl;
	}

	/**
	 * Configure whether DNSBL-listed email domains are rejected.
	 *
	 * @param bool $block True to reject DNSBL-listed domains.
	 * @return void
	 */
	public function set_block_dnsbl( $block ) {
		$this->block_dnsbl = (bool) $block;
	}

	/**
	 * Check whether DNSBL domain blocking is enabled.
	 *
	 * @return bool
	 */
	public function get_block_dnsbl() {
		return $this->block_dnsbl;
	}

	/**
	 * Get the last user-facing validation error message.
	 *
	 * @param string $fallback Fallback error string if none set.
	 * @return string
	 */
	public function get_error_message( $fallback = '' ) {
		if ( ! empty( $this->error_message ) ) {
			return $this->error_message;
		}
		if ( ! empty( $fallback ) ) {
			return $fallback;
		}
		return __( 'Please enter a valid phone number.', 'proweblook-phone-validator' );
	}

	/**
	 * Get last phone enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_phone_result() {
		return $this->last_phone_result;
	}

	/**
	 * Get last WhatsApp enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_whatsapp_result() {
		return $this->last_whatsapp_result;
	}

	/**
	 * Get last Email enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_email_result() {
		return $this->last_email_result;
	}

	/**
	 * Get last Caller ID (CNAM) enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_caller_id_result() {
		return $this->last_caller_id_result;
	}

	/**
	 * Get last Edge IP Geo enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_ip_geo_result() {
		return $this->last_ip_geo_result;
	}

	/**
	 * Get last Domain DNSBL Blacklist response.
	 *
	 * @return object|null
	 */
	public function get_last_blacklist_result() {
		return $this->last_blacklist_result;
	}

	/**
	 * Get validity of last phone check.
	 *
	 * @return bool
	 */
	public function get_is_valid() {
		return $this->is_valid;
	}

	/**
	 * Set validity flag.
	 *
	 * @param bool $is_valid Validity state.
	 * @return bool
	 */
	protected function set_is_valid( $is_valid ) {
		$this->is_valid = (bool) $is_valid;
		return $this->get_is_valid();
	}

	/**
	 * Get phone number.
	 *
	 * @return string
	 */
	public function get_phone() {
		return $this->phone;
	}

	/**
	 * Set phone number.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	public function set_phone( $phone ) {
		$this->phone = (string) $phone;
		return $this->get_phone();
	}

	/**
	 * Set phone status code.
	 *
	 * @param string $status Status code.
	 * @return string
	 */
	public function set_phone_status( $status ) {
		$this->phone_status = (string) $status;
		return $this->get_phone_status();
	}

	/**
	 * Get phone status code.
	 *
	 * @return string
	 */
	public function get_phone_status() {
		return $this->phone_status;
	}

	/**
	 * Get API client.
	 *
	 * @return PROP_API|null
	 */
	public function get_api() {
		return $this->api;
	}

	/**
	 * Set API client.
	 *
	 * @param object $api API client object.
	 * @return object
	 */
	public function set_api( $api ) {
		$this->api = $api;
		return $this->get_api();
	}
}
