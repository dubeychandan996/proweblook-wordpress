<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Translate ISO 3166-1 Country Codes to Country names and vice versa.
 *
 * @package PROP/API
 */

/**
 * Class PROP_Countries
 */
class PROP_Countries {

	/**
	 * The country list.
	 *
	 * @var array
	 */
	private static $countries = array();

	/**
	 * Populate the countries
	 */
	public static function populate() {

		self::$countries = array(
			'AF' => __( 'Afghanistan', 'proweblook-phone-validator' ),
			'AL' => __( 'Albania', 'proweblook-phone-validator' ),
			'DZ' => __( 'Algeria', 'proweblook-phone-validator' ),
			'AS' => __( 'American Samoa', 'proweblook-phone-validator' ),
			'AD' => __( 'Andorra', 'proweblook-phone-validator' ),
			'AO' => __( 'Angola', 'proweblook-phone-validator' ),
			'AI' => __( 'Anguilla', 'proweblook-phone-validator' ),
			'AQ' => __( 'Antarctica', 'proweblook-phone-validator' ),
			'AG' => __( 'Antigua & Barbuda', 'proweblook-phone-validator' ),
			'AR' => __( 'Argentina', 'proweblook-phone-validator' ),
			'AM' => __( 'Armenia', 'proweblook-phone-validator' ),
			'AW' => __( 'Aruba', 'proweblook-phone-validator' ),
			'AU' => __( 'Australia', 'proweblook-phone-validator' ),
			'AT' => __( 'Austria', 'proweblook-phone-validator' ),
			'AZ' => __( 'Azerbaijan', 'proweblook-phone-validator' ),
			'BS' => __( 'Bahamas', 'proweblook-phone-validator' ),
			'BH' => __( 'Bahrain', 'proweblook-phone-validator' ),
			'BD' => __( 'Bangladesh', 'proweblook-phone-validator' ),
			'BB' => __( 'Barbados', 'proweblook-phone-validator' ),
			'BY' => __( 'Belarus', 'proweblook-phone-validator' ),
			'BE' => __( 'Belgium', 'proweblook-phone-validator' ),
			'BZ' => __( 'Belize', 'proweblook-phone-validator' ),
			'BJ' => __( 'Benin', 'proweblook-phone-validator' ),
			'BM' => __( 'Bermuda', 'proweblook-phone-validator' ),
			'BT' => __( 'Bhutan', 'proweblook-phone-validator' ),
			'BO' => __( 'Bolivia', 'proweblook-phone-validator' ),
			'BA' => __( 'Bosnia', 'proweblook-phone-validator' ),
			'BW' => __( 'Botswana', 'proweblook-phone-validator' ),
			'BV' => __( 'Bouvet Island', 'proweblook-phone-validator' ),
			'BR' => __( 'Brazil', 'proweblook-phone-validator' ),
			'IO' => __( 'British Indian Ocean Territory', 'proweblook-phone-validator' ),
			'VG' => __( 'British Virgin Islands', 'proweblook-phone-validator' ),
			'BN' => __( 'Brunei', 'proweblook-phone-validator' ),
			'BG' => __( 'Bulgaria', 'proweblook-phone-validator' ),
			'BF' => __( 'Burkina Faso', 'proweblook-phone-validator' ),
			'BI' => __( 'Burundi', 'proweblook-phone-validator' ),
			'KH' => __( 'Cambodia', 'proweblook-phone-validator' ),
			'CM' => __( 'Cameroon', 'proweblook-phone-validator' ),
			'CA' => __( 'Canada', 'proweblook-phone-validator' ),
			'CV' => __( 'Cape Verde', 'proweblook-phone-validator' ),
			'BQ' => __( 'Caribbean Netherlands', 'proweblook-phone-validator' ),
			'KY' => __( 'Cayman Islands', 'proweblook-phone-validator' ),
			'CF' => __( 'Central African Republic', 'proweblook-phone-validator' ),
			'TD' => __( 'Chad', 'proweblook-phone-validator' ),
			'CL' => __( 'Chile', 'proweblook-phone-validator' ),
			'CN' => __( 'China', 'proweblook-phone-validator' ),
			'CX' => __( 'Christmas Island', 'proweblook-phone-validator' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'proweblook-phone-validator' ),
			'CO' => __( 'Colombia', 'proweblook-phone-validator' ),
			'KM' => __( 'Comoros', 'proweblook-phone-validator' ),
			'CG' => __( 'Congo - Brazzaville', 'proweblook-phone-validator' ),
			'CD' => __( 'Congo - Kinshasa', 'proweblook-phone-validator' ),
			'CK' => __( 'Cook Islands', 'proweblook-phone-validator' ),
			'CR' => __( 'Costa Rica', 'proweblook-phone-validator' ),
			'HR' => __( 'Croatia', 'proweblook-phone-validator' ),
			'CU' => __( 'Cuba', 'proweblook-phone-validator' ),
			'CW' => __( 'Curaçao', 'proweblook-phone-validator' ),
			'CY' => __( 'Cyprus', 'proweblook-phone-validator' ),
			'CZ' => __( 'Czechia', 'proweblook-phone-validator' ),
			'CI' => __( 'Côte d’Ivoire', 'proweblook-phone-validator' ),
			'DK' => __( 'Denmark', 'proweblook-phone-validator' ),
			'DJ' => __( 'Djibouti', 'proweblook-phone-validator' ),
			'DM' => __( 'Dominica', 'proweblook-phone-validator' ),
			'DO' => __( 'Dominican Republic', 'proweblook-phone-validator' ),
			'EC' => __( 'Ecuador', 'proweblook-phone-validator' ),
			'EG' => __( 'Egypt', 'proweblook-phone-validator' ),
			'SV' => __( 'El Salvador', 'proweblook-phone-validator' ),
			'GQ' => __( 'Equatorial Guinea', 'proweblook-phone-validator' ),
			'ER' => __( 'Eritrea', 'proweblook-phone-validator' ),
			'EE' => __( 'Estonia', 'proweblook-phone-validator' ),
			'ET' => __( 'Ethiopia', 'proweblook-phone-validator' ),
			'FK' => __( 'Falkland Islands', 'proweblook-phone-validator' ),
			'FO' => __( 'Faroe Islands', 'proweblook-phone-validator' ),
			'FJ' => __( 'Fiji', 'proweblook-phone-validator' ),
			'FI' => __( 'Finland', 'proweblook-phone-validator' ),
			'FR' => __( 'France', 'proweblook-phone-validator' ),
			'GF' => __( 'French Guiana', 'proweblook-phone-validator' ),
			'PF' => __( 'French Polynesia', 'proweblook-phone-validator' ),
			'TF' => __( 'French Southern Territories', 'proweblook-phone-validator' ),
			'GA' => __( 'Gabon', 'proweblook-phone-validator' ),
			'GM' => __( 'Gambia', 'proweblook-phone-validator' ),
			'GE' => __( 'Georgia', 'proweblook-phone-validator' ),
			'DE' => __( 'Germany', 'proweblook-phone-validator' ),
			'GH' => __( 'Ghana', 'proweblook-phone-validator' ),
			'GI' => __( 'Gibraltar', 'proweblook-phone-validator' ),
			'GR' => __( 'Greece', 'proweblook-phone-validator' ),
			'GL' => __( 'Greenland', 'proweblook-phone-validator' ),
			'GD' => __( 'Grenada', 'proweblook-phone-validator' ),
			'GP' => __( 'Guadeloupe', 'proweblook-phone-validator' ),
			'GU' => __( 'Guam', 'proweblook-phone-validator' ),
			'GT' => __( 'Guatemala', 'proweblook-phone-validator' ),
			'GG' => __( 'Guernsey', 'proweblook-phone-validator' ),
			'GN' => __( 'Guinea', 'proweblook-phone-validator' ),
			'GW' => __( 'Guinea-Bissau', 'proweblook-phone-validator' ),
			'GY' => __( 'Guyana', 'proweblook-phone-validator' ),
			'HT' => __( 'Haiti', 'proweblook-phone-validator' ),
			'HM' => __( 'Heard & McDonald Islands', 'proweblook-phone-validator' ),
			'HN' => __( 'Honduras', 'proweblook-phone-validator' ),
			'HK' => __( 'Hong Kong', 'proweblook-phone-validator' ),
			'HU' => __( 'Hungary', 'proweblook-phone-validator' ),
			'IS' => __( 'Iceland', 'proweblook-phone-validator' ),
			'IN' => __( 'India', 'proweblook-phone-validator' ),
			'ID' => __( 'Indonesia', 'proweblook-phone-validator' ),
			'IR' => __( 'Iran', 'proweblook-phone-validator' ),
			'IQ' => __( 'Iraq', 'proweblook-phone-validator' ),
			'IE' => __( 'Ireland', 'proweblook-phone-validator' ),
			'IM' => __( 'Isle of Man', 'proweblook-phone-validator' ),
			'IL' => __( 'Israel', 'proweblook-phone-validator' ),
			'IT' => __( 'Italy', 'proweblook-phone-validator' ),
			'JM' => __( 'Jamaica', 'proweblook-phone-validator' ),
			'JP' => __( 'Japan', 'proweblook-phone-validator' ),
			'JE' => __( 'Jersey', 'proweblook-phone-validator' ),
			'JO' => __( 'Jordan', 'proweblook-phone-validator' ),
			'KZ' => __( 'Kazakhstan', 'proweblook-phone-validator' ),
			'KE' => __( 'Kenya', 'proweblook-phone-validator' ),
			'KI' => __( 'Kiribati', 'proweblook-phone-validator' ),
			'KW' => __( 'Kuwait', 'proweblook-phone-validator' ),
			'KG' => __( 'Kyrgyzstan', 'proweblook-phone-validator' ),
			'LA' => __( 'Laos', 'proweblook-phone-validator' ),
			'LV' => __( 'Latvia', 'proweblook-phone-validator' ),
			'LB' => __( 'Lebanon', 'proweblook-phone-validator' ),
			'LS' => __( 'Lesotho', 'proweblook-phone-validator' ),
			'LR' => __( 'Liberia', 'proweblook-phone-validator' ),
			'LY' => __( 'Libya', 'proweblook-phone-validator' ),
			'LI' => __( 'Liechtenstein', 'proweblook-phone-validator' ),
			'LT' => __( 'Lithuania', 'proweblook-phone-validator' ),
			'LU' => __( 'Luxembourg', 'proweblook-phone-validator' ),
			'MO' => __( 'Macau', 'proweblook-phone-validator' ),
			'MK' => __( 'Macedonia', 'proweblook-phone-validator' ),
			'MG' => __( 'Madagascar', 'proweblook-phone-validator' ),
			'MW' => __( 'Malawi', 'proweblook-phone-validator' ),
			'MY' => __( 'Malaysia', 'proweblook-phone-validator' ),
			'MV' => __( 'Maldives', 'proweblook-phone-validator' ),
			'ML' => __( 'Mali', 'proweblook-phone-validator' ),
			'MT' => __( 'Malta', 'proweblook-phone-validator' ),
			'MH' => __( 'Marshall Islands', 'proweblook-phone-validator' ),
			'MQ' => __( 'Martinique', 'proweblook-phone-validator' ),
			'MR' => __( 'Mauritania', 'proweblook-phone-validator' ),
			'MU' => __( 'Mauritius', 'proweblook-phone-validator' ),
			'YT' => __( 'Mayotte', 'proweblook-phone-validator' ),
			'MX' => __( 'Mexico', 'proweblook-phone-validator' ),
			'FM' => __( 'Micronesia', 'proweblook-phone-validator' ),
			'MD' => __( 'Moldova', 'proweblook-phone-validator' ),
			'MC' => __( 'Monaco', 'proweblook-phone-validator' ),
			'MN' => __( 'Mongolia', 'proweblook-phone-validator' ),
			'ME' => __( 'Montenegro', 'proweblook-phone-validator' ),
			'MS' => __( 'Montserrat', 'proweblook-phone-validator' ),
			'MA' => __( 'Morocco', 'proweblook-phone-validator' ),
			'MZ' => __( 'Mozambique', 'proweblook-phone-validator' ),
			'MM' => __( 'Myanmar', 'proweblook-phone-validator' ),
			'NA' => __( 'Namibia', 'proweblook-phone-validator' ),
			'NR' => __( 'Nauru', 'proweblook-phone-validator' ),
			'NP' => __( 'Nepal', 'proweblook-phone-validator' ),
			'NL' => __( 'Netherlands', 'proweblook-phone-validator' ),
			'NC' => __( 'New Caledonia', 'proweblook-phone-validator' ),
			'NZ' => __( 'New Zealand', 'proweblook-phone-validator' ),
			'NI' => __( 'Nicaragua', 'proweblook-phone-validator' ),
			'NE' => __( 'Niger', 'proweblook-phone-validator' ),
			'NG' => __( 'Nigeria', 'proweblook-phone-validator' ),
			'NU' => __( 'Niue', 'proweblook-phone-validator' ),
			'NF' => __( 'Norfolk Island', 'proweblook-phone-validator' ),
			'KP' => __( 'North Korea', 'proweblook-phone-validator' ),
			'MP' => __( 'Northern Mariana Islands', 'proweblook-phone-validator' ),
			'NO' => __( 'Norway', 'proweblook-phone-validator' ),
			'OM' => __( 'Oman', 'proweblook-phone-validator' ),
			'PK' => __( 'Pakistan', 'proweblook-phone-validator' ),
			'PW' => __( 'Palau', 'proweblook-phone-validator' ),
			'PS' => __( 'Palestine', 'proweblook-phone-validator' ),
			'PA' => __( 'Panama', 'proweblook-phone-validator' ),
			'PG' => __( 'Papua New Guinea', 'proweblook-phone-validator' ),
			'PY' => __( 'Paraguay', 'proweblook-phone-validator' ),
			'PE' => __( 'Peru', 'proweblook-phone-validator' ),
			'PH' => __( 'Philippines', 'proweblook-phone-validator' ),
			'PN' => __( 'Pitcairn Islands', 'proweblook-phone-validator' ),
			'PL' => __( 'Poland', 'proweblook-phone-validator' ),
			'PT' => __( 'Portugal', 'proweblook-phone-validator' ),
			'PR' => __( 'Puerto Rico', 'proweblook-phone-validator' ),
			'QA' => __( 'Qatar', 'proweblook-phone-validator' ),
			'RO' => __( 'Romania', 'proweblook-phone-validator' ),
			'RU' => __( 'Russia', 'proweblook-phone-validator' ),
			'RW' => __( 'Rwanda', 'proweblook-phone-validator' ),
			'RE' => __( 'Réunion', 'proweblook-phone-validator' ),
			'WS' => __( 'Samoa', 'proweblook-phone-validator' ),
			'SM' => __( 'San Marino', 'proweblook-phone-validator' ),
			'SA' => __( 'Saudi Arabia', 'proweblook-phone-validator' ),
			'SN' => __( 'Senegal', 'proweblook-phone-validator' ),
			'RS' => __( 'Serbia', 'proweblook-phone-validator' ),
			'SC' => __( 'Seychelles', 'proweblook-phone-validator' ),
			'SL' => __( 'Sierra Leone', 'proweblook-phone-validator' ),
			'SG' => __( 'Singapore', 'proweblook-phone-validator' ),
			'SX' => __( 'Sint Maarten', 'proweblook-phone-validator' ),
			'SK' => __( 'Slovakia', 'proweblook-phone-validator' ),
			'SI' => __( 'Slovenia', 'proweblook-phone-validator' ),
			'SB' => __( 'Solomon Islands', 'proweblook-phone-validator' ),
			'SO' => __( 'Somalia', 'proweblook-phone-validator' ),
			'ZA' => __( 'South Africa', 'proweblook-phone-validator' ),
			'GS' => __( 'South Georgia & South Sandwich Islands', 'proweblook-phone-validator' ),
			'KR' => __( 'South Korea', 'proweblook-phone-validator' ),
			'SS' => __( 'South Sudan', 'proweblook-phone-validator' ),
			'ES' => __( 'Spain', 'proweblook-phone-validator' ),
			'LK' => __( 'Sri Lanka', 'proweblook-phone-validator' ),
			'BL' => __( 'St. Barthélemy', 'proweblook-phone-validator' ),
			'SH' => __( 'St. Helena', 'proweblook-phone-validator' ),
			'KN' => __( 'St. Kitts & Nevis', 'proweblook-phone-validator' ),
			'LC' => __( 'St. Lucia', 'proweblook-phone-validator' ),
			'MF' => __( 'St. Martin', 'proweblook-phone-validator' ),
			'PM' => __( 'St. Pierre & Miquelon', 'proweblook-phone-validator' ),
			'VC' => __( 'St. Vincent & Grenadines', 'proweblook-phone-validator' ),
			'SD' => __( 'Sudan', 'proweblook-phone-validator' ),
			'SR' => __( 'Suriname', 'proweblook-phone-validator' ),
			'SJ' => __( 'Svalbard & Jan Mayen', 'proweblook-phone-validator' ),
			'SZ' => __( 'Swaziland', 'proweblook-phone-validator' ),
			'SE' => __( 'Sweden', 'proweblook-phone-validator' ),
			'CH' => __( 'Switzerland', 'proweblook-phone-validator' ),
			'SY' => __( 'Syria', 'proweblook-phone-validator' ),
			'ST' => __( 'São Tomé & Príncipe', 'proweblook-phone-validator' ),
			'TW' => __( 'Taiwan', 'proweblook-phone-validator' ),
			'TJ' => __( 'Tajikistan', 'proweblook-phone-validator' ),
			'TZ' => __( 'Tanzania', 'proweblook-phone-validator' ),
			'TH' => __( 'Thailand', 'proweblook-phone-validator' ),
			'TL' => __( 'Timor-Leste', 'proweblook-phone-validator' ),
			'TG' => __( 'Togo', 'proweblook-phone-validator' ),
			'TK' => __( 'Tokelau', 'proweblook-phone-validator' ),
			'TO' => __( 'Tonga', 'proweblook-phone-validator' ),
			'TT' => __( 'Trinidad & Tobago', 'proweblook-phone-validator' ),
			'TN' => __( 'Tunisia', 'proweblook-phone-validator' ),
			'TR' => __( 'Turkey', 'proweblook-phone-validator' ),
			'TM' => __( 'Turkmenistan', 'proweblook-phone-validator' ),
			'TC' => __( 'Turks & Caicos Islands', 'proweblook-phone-validator' ),
			'TV' => __( 'Tuvalu', 'proweblook-phone-validator' ),
			'UM' => __( 'U.S. Outlying Islands', 'proweblook-phone-validator' ),
			'VI' => __( 'U.S. Virgin Islands', 'proweblook-phone-validator' ),
			'GB' => __( 'UK', 'proweblook-phone-validator' ),
			'US' => __( 'US', 'proweblook-phone-validator' ),
			'UG' => __( 'Uganda', 'proweblook-phone-validator' ),
			'UA' => __( 'Ukraine', 'proweblook-phone-validator' ),
			'AE' => __( 'United Arab Emirates', 'proweblook-phone-validator' ),
			'UY' => __( 'Uruguay', 'proweblook-phone-validator' ),
			'UZ' => __( 'Uzbekistan', 'proweblook-phone-validator' ),
			'VU' => __( 'Vanuatu', 'proweblook-phone-validator' ),
			'VA' => __( 'Vatican City', 'proweblook-phone-validator' ),
			'VE' => __( 'Venezuela', 'proweblook-phone-validator' ),
			'VN' => __( 'Vietnam', 'proweblook-phone-validator' ),
			'WF' => __( 'Wallis & Futuna', 'proweblook-phone-validator' ),
			'EH' => __( 'Western Sahara', 'proweblook-phone-validator' ),
			'YE' => __( 'Yemen', 'proweblook-phone-validator' ),
			'ZM' => __( 'Zambia', 'proweblook-phone-validator' ),
			'ZW' => __( 'Zimbabwe', 'proweblook-phone-validator' ),
			'AX' => __( 'Åland Islands', 'proweblook-phone-validator' ),
		);
	}

	/**
	 * Get list
	 *
	 * @return array
	 */
	public static function get_list() {
		return self::$countries;
	}


	/**
	 * Get the country by its code.
	 *
	 * @param string $code The country code.
	 *
	 * @return string
	 */
	public static function get_country( $code ) {

		return ( ! empty( self::$countries[ $code ] ) ) ? self::$countries[ $code ] : '';
	}

	/**
	 * Get the code of a country.
	 *
	 * @param string $country The country name.
	 *
	 * @return string
	 */
	public static function get_code( $country ) {

		$codes = array_flip( self::$countries );
		return ( ! empty( $codes[ $country ] ) ) ? $codes[ $country ] : '';
	}
}
