<?php
/**
 * Single Campaign
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class ATCF_Campaign {
	public $ID;
	public $data;

	function __construct( $post ) {
		$this->data = get_post( $post );
		$this->ID   = $this->data->ID;
	}

	/**
	 * Getter
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param string $key The meta key to fetch
	 * @return string $meta The fetched value
	 */
	public function __get( $key ) {
		$meta = apply_filters( 'atcf_campaign_meta_' . $key, $this->data->__get( $key ) );

		return $meta;
	}

	/**
	 * Campaign Featured
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return sting Campaign Featured
	 */
	public function featured() {
		return $this->__get( '_campaign_featured' );
	}

	/**
	 * Needs Shipping
	 *
	 * @since Astoundify Crowdfunding 0.9
	 *
	 * @return sting Requires Shipping
	 */
	public function needs_shipping() {
		$physical = $this->__get( '_campaign_physical' );

		return apply_filters( 'atcf_campaign_needs_shipping', $physical, $this );
	}

	/**
	 * Campaign Goal
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param boolean $formatted Return formatted currency or not
	 * @return sting $goal A goal amount (formatted or not)
	 */
	public function goal( $formatted = true ) {
		$goal = $this->__get( 'campaign_goal' );

		if ( ! $goal )
			return 0;

		if ( $formatted )
			return edd_currency_filter( edd_format_amount( $goal ) );

		return $goal;
	}

	/**
	 * Campaign Type
	 *
	 * @since Astoundify Crowdfunding 0.7
	 *
	 * @return string $type The type of campaign
	 */
	public function type() {
		$type = $this->__get( 'campaign_type' );

		if ( ! $type )
			$type = atcf_campaign_type_default();

		return $type;
	}

	/**
	 * Campaign Location
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return sting Campaign Location
	 */
	public function location() {
		return $this->__get( 'campaign_location' );
	}

	/**
	 * Campaign Author
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return sting Campaign Author
	 */
	public function author() {
		return $this->__get( 'campaign_author' );
	}

	/**
	 * Campaign Contact Email
	 *
	 * @since Astoundify Crowdfunding 0.5
	 *
	 * @return sting Campaign Contact Email
	 */
	public function contact_email() {
		return antispambot( $this->__get( 'campaign_contact_email' ) );
	}

	/**
	 * Campaign End Date
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return sting Campaign End Date
	 */
	public function end_date() {
		return $this->__get( 'campaign_end_date' );
	}

	/**
	 * Is Endless
	 *
	 * @since Astoundify Crowdfunding 1.4
	 *
	 * @return boolean
	 */
	public function is_endless() {
		return $this->__get( 'campaign_endless' );
	}

	/**
	 * Is donations only.
	 *
	 * @since Astoundify Crowdfunding 1.5
	 *
	 * @return boolean
	 */
	public function is_donations_only() {
		return $this->__get( 'campaign_norewards' );
	}

	/**
	 * Campaign Video
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return sting Campaign Video
	 */
	public function video() {
		return $this->__get( 'campaign_video' );
	}

	/**
	 * Campaign Updates
	 *
	 * @since Astoundify Crowdfunding 0.9
	 *
	 * @return sting Campaign Updates
	 */
	public function updates() {
		return $this->__get( 'campaign_updates' );
	}

	/**
	 * Campaign Backers Logs
	 *
	 * Use EDD logs to get all sales. This includes both preapproved
	 * payments (if they have Plugin installed) or standard payments.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param bool $unique
	 * @return array Campaign Backers
	 */
	public function backers( $unique = false ) {
		global $edd_logs;

		$backers_args = apply_filters( 'atcf_campaign_backers_args', array(
	       'post_parent'    => $this->ID,
	       'log_type'       => atcf_has_preapproval_gateway() ? 'preapproval' : 'sale',
	       'post_status'    => array( 'publish' ),
	       'posts_per_page' => -1
	    ) );

		$backers = $edd_logs->get_connected_logs( $backers_args );

		if ( ! $backers )
			return array();

		if ( $unique )
			return $this->unique_backers( $backers );

		return $backers;
	}

	/**
	 * Unique Campaign Backers
	 *
	 * A log is made for each download item, so if someone purchases the same thing 4 times
	 * at once, 4 logs are created. Sort those out, and only return unique logs.
	 *
	 * @since Astoundify Crowdfunding 1.7.2
	 *
	 * @param array $backers
	 * @return sting Campaign Backers
	 */
	public function unique_backers( $backers = null ) {
		if ( is_null( $backers ) ) {
			$backers  = $this->backers();
		}		
		
		$_backers = array();

		foreach ( $backers as $backer ) {
			$payment_id = get_post_meta( $backer->ID, '_edd_log_payment_id', true );

			if ( in_array( $payment_id, $_backers ) )
				continue;
			else {
				$_backers[] = $payment_id;
			}
		}

		return $_backers;
	}

	/**
	 * Campaign Backers Count
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return int Campaign Backers Count
	 */
	public function backers_count() {
		$prices = edd_get_variable_prices( $this->ID );
		$count  = 0;

		if ( empty( $prices ) ) {
			return $count;
		}

		foreach ( $prices as $price ) {
			$count += isset( $price[ 'bought' ] ) ? $price[ 'bought' ] : 0;
		}

		return $count;
	}

	/**
	 * Campaign Time Remaining
	 *
	 * @since Astoundify Crowdfunding 1.7
	 *
	 * @param string The format to output
	 * @return int The remaining time in the specified format.
	 */
	public function time_remaining( $format ) {
		$now     = current_time( 'timestamp' );
		$expires = strtotime( $this->end_date(), $now );

		if ( $now > $expires )
			return 0;

		$diff = $expires - $now;

		switch ( $format ) {
			case 'days' :
				$off = $diff / 86400;
			break;

			case 'hours' :
				$off = $diff / ( 60 * 60 );
			break;
		}

		$remaining = floor( $off );

		return apply_filters( 'atcf_campaign_time_remainig', $remaining, $this );
	}

	/**
	 * Campaign Days Remaining
	 *
	 * Calculate the end date, minus today's date, and output a number.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return int The number of days remaining
	 */
	public function days_remaining() {
		//_deprecated_function( __FUNCTION__, '1.7.2', 'time_remaining()' );

		return $this->time_remaining( 'days' );
	}

	/**
	 * Campaign Hours Remaining
	 *
	 * Calculate the end date, minus today's date, and output a number.
	 *
	 * @since Astoundify Crowdfunding 1.4
	 *
	 * @return int The hours remaining
	 */
	public function hours_remaining() {
		//_deprecated_function( __FUNCTION__, '1.7.2', 'time_remaining()' );

		return $this->time_remaining( 'hours' );
	}

	/**
	 * Campaign Percent Completed
	 *
	 * MATH!
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param boolean $formatted Return formatted currency or not
	 * @return sting $percent The percent completed (formatted with a % or not)
	 */
	public function percent_completed( $formatted = true ) {
		$goal    = $this->goal(false);
		$current = $this->current_amount(false);

		if ( 0 == $goal )
			return $formatted ? 0 . '%' : 0;

		$percent = ( $current / $goal ) * 100;
		$percent = round( $percent );

		if ( $formatted )
			return $percent . '%';

		return $percent;
	}

	/**
	 * Current amount funded.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @global $wpdb
	 * @param boolean $formatted Return formatted currency or not
	 * @return sting $total The amount funded (currency formatted or not)
	 */
	public function current_amount( $formatted = true ) {

		// Don't do this more than once
		if ( !isset( $this->current_amount ) ) {

			global $wpdb;

			// Allow plugins/themes to filter which IDs are matched in post_parent.
			$campaign_ids = apply_filters( 'atcf_campaign_pledged_amount_id', array( $this->ID ), $this );
			$campaign_ids = array_filter( $campaign_ids, 'is_int' );
			$campaign_ids = implode( ',', $campaign_ids );

			// Fetches the SUM of all payments made to this campaign.
			$query = apply_filters( 'atcf_campaign_pledged_query', 
				"SELECT SUM(m.meta_value) 
				FROM ( 
					SELECT DISTINCT m1.post_id, m1.meta_value 
					FROM $wpdb->postmeta m1 
					INNER JOIN $wpdb->posts p1
					ON p1.ID = m1.post_id
					INNER JOIN $wpdb->postmeta m2 
					ON m2.meta_value = m1.post_id 
					INNER JOIN $wpdb->posts p2 
					ON p2.ID = m2.post_id
					WHERE p1.post_status IN ('publish', 'preapproval')
					AND p2.post_parent IN ( $campaign_ids )
					AND m1.meta_key = '_edd_payment_total' 
				) m", 
			$campaign_ids, $this );

			$this->current_amount = $wpdb->get_var( $query );
		}
	
		if ( $formatted )
			return edd_currency_filter( edd_format_amount( $this->current_amount ) );

		return $this->current_amount;
	}

	function failed_payments() {
		return $this->__get( '_campaign_failed_payments' );
	}

	/**
	 * Campaign Active
	 *
	 * Check if the campaign has expired based on time, or it has
	 * manually been expired (via meta)
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return boolean
	 */
	public function is_active() {
		$active  = true;

		$expires = strtotime( $this->end_date() );
		$now     = current_time( 'timestamp' );

		if ( $now > $expires )
			$active = false;

		if ( $this->__get( '_campaign_expired' ) )
			$active = false;

		if ( $this->is_collected() )
			$active = false;

		if ( $this->is_endless() )
			$active = true;

		return apply_filters( 'atcf_campaign_active', $active, $this );
	}

	/**
	 * Funds Collected
	 *
	 * When funds are collected in bulk, remember that, so we can end the
	 * campaign, and not repeat things.
	 *
	 * @since Astoundify Crowdfunding 0.3-alpha
	 *
	 * @return boolean
	 */
	public function is_collected() {
		return $this->__get( '_campaign_bulk_collected' );
	}

	/**
	 * Campaign Funded
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return boolean
	 */
	public function is_funded() {
		if ( $this->current_amount(false) >= $this->goal(false) )
			return true;

		return false;
	}
}

function atcf_get_campaign( $campaign ) {
	return apply_filters( 'atcf_get_campaign', new ATCF_Campaign( $campaign ) );
}

/**
 * Price Options Heading
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @param string $heading Price options heading
 * @return string Modified price options heading
 */
function atcf_edd_price_options_heading( $heading ) {
	return __( 'Reward Options:', 'atcf' );
}

/**
 * Reward toggle text
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @param string $heading Reward toggle text
 * @return string Modified reward toggle text
 */
function atcf_edd_variable_pricing_toggle_text( $text ) {
	return __( 'Enable multiple reward options', 'atcf' );
}

/**
 * Campaign Types
 *
 * @since Astoundify Crowdfunding 0.9
 */
function atcf_campaign_types() {
	$types = array(
		'fixed'    => array(
			'title'       => __( 'All-or-nothing', 'atcf' ),
			'description' => __( 'Only collect pledged funds when the campaign ends if the set goal is met.', 'atcf' )
		),
		'flexible' => array(
			'title'       => __( 'Flexible', 'atcf' ),
			'description' => __( 'Collect funds pledged at the end of the campaign no matter what.', 'atcf' )
		)
	);

	if ( ! atcf_has_preapproval_gateway() ) {
		$types = array(
			'donation'    => array(
				'title'       => __( 'Donation', 'atcf' ),
				'description' => __( 'Funds will be collected automatically as pledged.', 'atcf' )
			)
		);
	}

	return apply_filters( 'atcf_campaign_types', $types );
}

/**
 * @missing
 *
 * @since Astoundify Crowdfunding unknown
 */
function atcf_campaign_types_active() {
	global $edd_options;

	$types  = atcf_campaign_types();
	$active = isset ( $edd_options[ 'atcf_campaign_types' ] ) ? $edd_options[ 'atcf_campaign_types' ] : null;

	if ( ! $active ) {
		$keys = array();

		foreach ( $types as $key => $type )
			$keys[ $key ] = $type[ 'title' ] . ' &mdash; <small>' . $type[ 'description' ] . '</small>';

		return $keys;
	}

	return $active;
}

/**
 * @missing
 *
 * @since Astoundify Crowdfunding unknown
 */
function atcf_campaign_type_default() {
	$type = apply_filters( 'atcf_campaign_type_default', atcf_has_preapproval_gateway() ? 'fixed' : 'donation' );

	return $type;
}