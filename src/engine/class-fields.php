<?php
/**
 * Hidden fields added to protected forms.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

use ShieldCraft\Front\Assets;

/**
 * A honeypot text field that people never see, the signed token and the script's proof. The
 * wrapper is moved off-screen with inline styles (not display:none, which bots skip, and not a
 * stylesheet, which optimisers may drop), hidden from screen readers and out of the tab order.
 */
final class Fields {

	const TOKEN    = 'shieldcraft_token';
	const PROOF    = 'shieldcraft_js';
	const HONEYPOT = 'shieldcraft_website';

	/**
	 * Field set by WP Spam Protecter 1.0 (a cached old page can still send it).
	 */
	const LEGACY = 'wp-spam-protecter-year';

	/**
	 * Fields for a context.
	 *
	 * @param string $context Context.
	 * @return string HTML.
	 */
	public static function render( $context ) {
		Assets::enqueue();
		return sprintf(
			'<div class="shieldcraft-fields" data-shieldcraft="%1$s" aria-hidden="true" style="position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;">'
			. '<label for="%2$s">%3$s</label><input type="text" name="%4$s" id="%2$s" value="" tabindex="-1" autocomplete="off" />'
			. '<input type="hidden" name="%5$s" value="%6$s" /><input type="hidden" name="%7$s" value="" /></div>',
			esc_attr( $context ),
			esc_attr( wp_unique_id( 'shieldcraft-website-' ) ),
			esc_html__( 'Leave this field empty', 'wp-spam-protecter' ),
			esc_attr( self::HONEYPOT ),
			esc_attr( self::TOKEN ),
			esc_attr( Token::create( $context ) ),
			esc_attr( self::PROOF )
		);
	}

	/**
	 * Hidden field values sent with the current request, or null when the form had none of them.
	 *
	 * @param array|null $source Values to read instead of $_POST.
	 * @return array|null { token, js, honeypot, legacy }
	 */
	public static function from_request( $source = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- spam checks run on public forms, which carry their own token.
		$source = null === $source ? $_POST : $source;
		$names  = array(
			'token'    => self::TOKEN,
			'js'       => self::PROOF,
			'honeypot' => self::HONEYPOT,
		);
		$out    = array();
		$found  = false;
		foreach ( $names as $key => $name ) {
			$found       = $found || isset( $source[ $name ] );
			$out[ $key ] = isset( $source[ $name ] ) && is_scalar( $source[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $source[ $name ] ) ) : '';
		}
		$out['legacy'] = isset( $source[ self::LEGACY ] );
		if ( ! $found ) {
			return $out['legacy'] ? array( 'legacy' => true ) : null;
		}
		return $out;
	}
}
