<?php
/**
 * Content rules: links, blocked words, emails and IP addresses, writing systems.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine\Checks;

use ShieldCraft\Engine\Request;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * Rules the site owner sets. They also run on trackbacks and pingbacks, which have no hidden
 * fields.
 */
final class Content implements Check {

	/**
	 * Run.
	 *
	 * @param Submission $submission Submission.
	 * @param Verdict    $verdict    Verdict.
	 */
	public function run( Submission $submission, Verdict $verdict ) {
		$settings = Settings::get();

		if ( $settings['links'] ) {
			$links = self::count_links( $submission->message );
			if ( $links > $settings['max_links'] ) {
				/* translators: %d: number of links. */
				$verdict->add( 'links', Verdict::SPAM, sprintf( _n( '%d link', '%d links', $links, 'wp-spam-protecter' ), $links ) );
			}
		}

		$word = self::blocked_word( $submission );
		if ( '' !== $word ) {
			$verdict->add( 'blocked_word', Verdict::SPAM, $word );
		}

		if ( '' !== $submission->email && self::email_in( $submission->email, Settings::lines( 'blocked_emails' ) ) ) {
			$verdict->add( 'blocked_email', Verdict::SPAM );
		}

		if ( Request::ip_in( $submission->ip, Settings::lines( 'blocked_ips' ) ) ) {
			$verdict->add( 'blocked_ip', Verdict::SPAM );
		}

		if ( $settings['blocked_scripts'] ) {
			$script = self::mostly_in( $submission->message, $settings['blocked_scripts'] );
			if ( '' !== $script ) {
				$verdict->add( 'script', Verdict::SPAM, $script );
			}
		}
	}

	/**
	 * Links in a text: URLs, "www." addresses and BBCode [url] tags.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function count_links( $text ) {
		$text  = (string) $text;
		$count = preg_match_all( '~(?:https?://|\bwww\.)[^\s<>"\'\[\]]+~i', $text );
		// "[url=example.com]" has no scheme, so the first pattern misses it.
		$count = $count + preg_match_all( '~\[url=(?!https?://|www\.)[^\]\s]+\]~i', $text );
		return (int) $count;
	}

	/**
	 * First blocked word found in the sender's details or message, or ''. Words come from the
	 * settings and, when enabled, from Settings → Discussion → "Disallowed Comment Keys".
	 *
	 * @param Submission $submission Submission.
	 * @return string
	 */
	public static function blocked_word( Submission $submission ) {
		$words = Settings::lines( 'blocked_words' );
		if ( Settings::get( 'disallowed_keys' ) ) {
			foreach ( preg_split( '/\R/', (string) get_option( 'disallowed_keys' ) ) as $key ) {
				$key = strtolower( trim( $key ) );
				if ( '' !== $key ) {
					$words[] = $key;
				}
			}
		}
		if ( ! $words ) {
			return '';
		}
		$haystack = strtolower( implode( "\n", array( $submission->name, $submission->email, $submission->url, wp_strip_all_tags( $submission->message ) ) ) );
		foreach ( array_unique( $words ) as $word ) {
			if ( false !== strpos( $haystack, $word ) ) {
				return $word;
			}
		}
		return '';
	}

	/**
	 * Whether an email matches an entry: the address, its domain ("example.com", "@example.com")
	 * or a pattern with "*" ("*@example.com", "spam*@*").
	 *
	 * @param string   $email   Email.
	 * @param string[] $entries Entries (lower case).
	 * @return bool
	 */
	public static function email_in( $email, array $entries ) {
		$email  = strtolower( trim( $email ) );
		$at     = strrpos( $email, '@' );
		$domain = false === $at ? '' : substr( $email, $at + 1 );
		foreach ( $entries as $entry ) {
			$entry = ltrim( $entry, '@' );
			if ( $entry === $email || $entry === $domain ) {
				return true;
			}
			if ( false !== strpos( $entry, '*' ) && preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $entry, '/' ) ) . '$/', $email ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Blocked writing system used for most of the letters of a text, or ''.
	 *
	 * @param string   $text    Text.
	 * @param string[] $scripts Unicode script names.
	 * @return string
	 */
	public static function mostly_in( $text, array $scripts ) {
		$letters = preg_match_all( '/\p{L}/u', (string) $text );
		if ( ! $letters ) {
			return '';
		}
		foreach ( $scripts as $script ) {
			if ( ! in_array( $script, Settings::SCRIPTS, true ) ) {
				continue;
			}
			$count = preg_match_all( '/\p{' . $script . '}/u', $text );
			if ( $count * 2 > $letters ) {
				return $script;
			}
		}
		return '';
	}
}
