<?php
/**
 * Theme Stuff
 *
 * Some stuff themes can use, and theme compatability. 
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 */

/**
 * Does the current theme support certain functionality?
 *
 * @since Astoundify Crowdfunding 1.3
 *
 * @param string $feature The name of the feature to check.
 * @return boolean If the feature is supported or not.
 */
function atcf_theme_supports( $feature ) {
	$supports = get_theme_support( 'appthemer-crowdfunding' );
	$supports = $supports[0];

	return isset ( $supports[ $feature ] );
}

/**
 * Extend WP_Query with some predefined defaults to query
 * only campaign items.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 */
class ATCF_Campaign_Query extends WP_Query {
	/**
	 * Extend WP_Query with some predefined defaults to query
	 * only campaign items.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $args
	 * @return void
	 */
	function __construct( $args = array() ) {
		$defaults = array(
			'post_type'      => array( 'download' ),
			'posts_per_page' => get_option( 'posts_per_page' )
		);

		$args = wp_parse_args( $args, $defaults );

		parent::__construct( $args );
	}
}

/**
 * Custom output for variable pricing.
 *
 * Themes can hook into `atcf_campaign_contribute_options` to output
 * their own prices, if they choose to implement a custom solution.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 */
function atcf_purchase_variable_pricing( $download_id ) {
	$variable_pricing = edd_has_variable_prices( $download_id );

	if ( ! $variable_pricing && ! get_post_meta( $download_id, 'campaign_norewards', true) )
		return;

	$prices = edd_get_variable_prices( $download_id );
	$type   = edd_single_price_option_mode( $download_id ) ? 'checkbox' : 'radio';

	do_action( 'edd_before_price_options', $download_id ); 
	do_action( 'atcf_campaign_contribute_options', $prices, $type, $download_id );
	do_action( 'edd_after_price_options', $download_id );
}

/**
 * Remove output of variable pricing, and add our own system.
 *
 * @since Astoundify Crowdfunding 0.3-alpha
 *
 * @return void
 */
function atcf_theme_variable_pricing() {
	global $edd_options;

	remove_action( 'edd_purchase_link_top', 'edd_purchase_variable_pricing' );

	if ( isset ( $edd_options[ 'atcf_settings_custom_pledge' ] ) ) {
		add_action( 'edd_purchase_link_end', 'atcf_purchase_variable_pricing' );
	} else {
		add_action( 'edd_purchase_link_top', 'atcf_purchase_variable_pricing' );
	}
}

/**
 * Check for theme support, and remove variable pricing display,
 * as we can assume the theme has implemented it somehow else.
 *
 * @since Astoundify Crowdfunding 0.3-alpha
 *
 * @return void
 */
function atcf_theme_custom_variable_pricing() {
	if ( ! current_theme_supports( 'appthemer-crowdfunding' ) )
		return;

	add_action( 'init', 'atcf_theme_variable_pricing' );
}
add_action( 'after_setup_theme', 'atcf_theme_custom_variable_pricing', 100 );

/**
 * Contribute now list options
 *
 * @since Campaignify 1.0
 *
 * @return void
 */
function atcf_campaign_contribute_options( $prices, $type, $download_id ) {
	$campaign = atcf_get_campaign( $download_id );
?>
	<div class="edd_price_options <?php echo $campaign->is_active() ? 'active' : 'expired'; ?>" <?php echo $campaign->is_donations_only() ? 'style="display: none"' : null; ?>>
		<ul>
			<?php foreach ( $prices as $key => $price ) : ?>
				<?php
					$amount  = $price[ 'amount' ];
					$limit   = isset ( $price[ 'limit' ] ) ? $price[ 'limit' ] : '';
					$bought  = isset ( $price[ 'bought' ] ) ? $price[ 'bought' ] : 0;
					$allgone = false;

					if ( $bought == absint( $limit ) && '' != $limit )
						$allgone = true;

					if ( edd_use_taxes() && edd_taxes_on_prices() )
						$amount += edd_calculate_tax( $amount );
				?>
				<li class="atcf-price-option <?php echo $allgone ? 'inactive' : null; ?>" data-price="<?php echo edd_sanitize_amount( $amount ); ?>-<?php echo $key; ?>">
					<div class="clear">
						<h3><label for="<?php echo esc_attr( 'edd_price_option_' . $download_id . '_' . $key ); ?>"><?php
							if ( $campaign->is_active() )
								if ( ! $allgone )
									printf(
										'<input type="radio" name="edd_options[price_id][]" id="%1$s" class="%2$s edd_price_options_input" value="%3$s"/>',
										esc_attr( 'edd_price_option_' . $download_id . '_' . $key ),
										esc_attr( 'edd_price_option_' . $download_id ),
										esc_attr( $key )
									);
						?> <span class="pledge-verb"><?php _e( 'Pledge', 'atcf' ); ?></span> <?php echo edd_currency_filter( edd_format_amount( $amount ) ); ?></label></h3>
						
						<div class="backers">
							<div class="backer-count">
								<i class="icon-user"></i> <?php printf( _n( '1 Backer', '%1$s Backers', $bought, 'atcf' ), $bought ); ?>
							</div>

							<?php if ( '' != $limit && ! $allgone ) : ?>
								<small class="limit"><?php printf( __( 'Limit of %d &mdash; %d remaining', 'atcf' ), $limit, $limit - $bought ); ?></small>
							<?php elseif ( $allgone ) : ?>
								<small class="gone"><?php _e( 'All gone!', 'atcf' ); ?></small>
							<?php endif; ?>
						</div>
					</div>
					<?php echo wpautop( wp_kses_data( $price[ 'name' ] ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div><!--end .edd_price_options-->
<?php
}
if ( ! has_action( 'atcf_campaign_contribute_options' ) )
	add_action( 'atcf_campaign_contribute_options', 'atcf_campaign_contribute_options', 10, 3 );

/**
 * Custom price field
 *
 * @since Fundify 1.3
 *
 * @return void
 */
function atcf_campaign_contribute_custom_price() {
	global $edd_options;
?>
	<h2><?php echo apply_filters( 'atcf_pledge_custom_title', __( 'Enter your pledge amount', 'atcf' ) ); ?></h2>

	<p class="atcf_custom_price_wrap">
	<?php if ( ! isset( $edd_options['currency_position'] ) || $edd_options['currency_position'] == 'before' ) : ?>
		<span class="currency left">
			<?php echo edd_currency_filter( '' ); ?>
		</span>

		<input type="text" name="atcf_custom_price" id="atcf_custom_price" class="left" value="" />
	<?php else : ?>
		<input type="text" name="atcf_custom_price" id="atcf_custom_price" class="right" value="" />
		<span class="currency right">
			<?php echo edd_currency_filter( '' ); ?>
		</span>
	<?php endif; ?>
	</p>
<?php
}
add_action( 'edd_purchase_link_top', 'atcf_campaign_contribute_custom_price', 5 );

/**
 * If the option to disable custom pledging has been checked,
 * then remove a bunch of stuff we do to move the fields around,
 * add fields, etc.
 *
 * @since Fundify 1.0
 *
 * @return void
 */
function atcf_disable_custom_pledging() {
	global $edd_options;

	if ( isset ( $edd_options[ 'atcf_settings_custom_pledge' ] ) )
		return;

	remove_action( 'edd_purchase_link_top', 'atcf_campaign_contribute_custom_price', 5 );
	remove_action( 'init', 'atcf_theme_variable_pricing' );
	//add_action( 'edd_purchase_link_top', 'atcf_purchase_variable_pricing' );
	
	remove_filter( 'edd_add_to_cart_item', 'atcf_edd_add_to_cart_item' );
	remove_filter( 'edd_ajax_pre_cart_item_template', 'atcf_edd_add_to_cart_item' );
	remove_filter( 'edd_cart_item_price', 'atcf_edd_cart_item_price', 10, 3 );
}
add_action( 'init', 'atcf_disable_custom_pledging' );

/**
 * When a campaign is over, show a message.
 *
 * @since Astoundify Crowdfunding 1.3
 *
 * @return void
 */
function atcf_campaign_notes( $campaign ) {
	$end_date = date( get_option( 'date_format' ), strtotime( $campaign->end_date() ) );

	if ( 'fixed' == $campaign->type() ) {
?>
	<?php if ( ! $campaign->is_active() && ! $campaign->is_funded() ) : ?>
		<div class="edd_errors">
			<p class="edd_error"><?php printf( __( '<strong>Funding Unsuccessful</strong>. This project reached the deadline without achieving its funding goal on %s.', 'atcf' ), $end_date ); ?></p>
		</div>
	<?php elseif ( $campaign->is_funded() && ! $campaign->is_active() ) : ?>
		<div class="edd_errors">
			<p class="edd_error"><?php printf( __( '<strong>Funding Successful</strong>. This project reached its goal before %s.', 'atcf' ), $end_date ); ?></p>
		</div>
	<?php endif; ?>
<?php
	} elseif ( 'flexible' == $campaign->type() ) {
?>
	<?php if ( ! $campaign->is_active() ) : ?>
		<div class="edd_errors">
			<p class="edd_error"><?php printf( __( '<strong>Campaign Complete</strong>. This project has ended on %s. No more contributions can be made.', 'atcf' ), $end_date ); ?></p>
		</div>
	<?php endif; ?>
<?php
	} else {
		do_action( 'atcf_campaign_notes_before_' . $campaign->type(), $campaign );
	}
}
add_action( 'atcf_campaign_before', 'atcf_campaign_notes' );

function atcf_campaign_preview_note() {
	global $post;

	if ( ! is_preview() )
		return;
?>
	<div class="edd_errors">
		<p class="edd_error"><?php printf( __( 'This is a preview of your %1$s. <a href="%2$s">Edit</a>', 'atcf' ), strtolower( edd_get_label_singular() ), add_query_arg( array( 'edit' => true ), get_permalink( $post->ID ) ) ); ?></p>
	</div>
<?php
}
add_action( 'atcf_campaign_before', 'atcf_campaign_preview_note' );

add_action( 'atcf_campaign_before', 'edd_print_errors' );
add_action( 'atcf_shortcode_submit_hidden', 'edd_print_errors' );