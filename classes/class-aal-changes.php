<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** Diff old/new field maps into a capped, redacted changes payload for log meta. */
final class AAL_Changes {

	const MAX_FIELDS = 20;
	const MAX_LENGTH = 200;

	/** @return array{changes?: array<string, array{0:?string, 1:?string}>, changes_omitted?: int} */
	public static function diff( array $old, array $new ) {
		$flat_old = self::flatten( $old );
		$flat_new = self::flatten( $new );
		$changes  = array();
		foreach ( array_unique( array_merge( array_keys( $flat_old ), array_keys( $flat_new ) ) ) as $field ) {
			$old_norm = self::normalize( array_key_exists( $field, $flat_old ) ? $flat_old[ $field ] : null );
			$new_norm = self::normalize( array_key_exists( $field, $flat_new ) ? $flat_new[ $field ] : null );
			if ( $old_norm === $new_norm ) {
				continue;
			}
			$secret = self::is_secret( $field );
			$changes[ $field ] = array(
				( $secret && null !== $old_norm && '' !== $old_norm ) ? '[redacted]' : $old_norm,
				( $secret && null !== $new_norm && '' !== $new_norm ) ? '[redacted]' : $new_norm,
			);
		}
		$result = array();
		$total  = count( $changes );
		if ( $total > self::MAX_FIELDS ) {
			$changes                   = array_slice( $changes, 0, self::MAX_FIELDS, true );
			$result['changes_omitted'] = $total - self::MAX_FIELDS;
		}
		if ( ! empty( $changes ) ) {
			$result['changes'] = $changes;
		}
		return $result;
	}

	/** Expand one-level associative arrays as field[key]; lists stay whole. */
	private static function flatten( array $map ) {
		$flat = array();
		foreach ( $map as $field => $value ) {
			$is_assoc = is_array( $value ) && ! empty( $value ) && count( array_filter( array_keys( $value ), 'is_string' ) ) === count( $value );
			if ( $is_assoc ) {
				foreach ( $value as $key => $nested ) {
					$flat[ $field . '[' . $key . ']' ] = $nested;
				}
			} else {
				$flat[ $field ] = $value;
			}
		}
		return $flat;
	}

	/** @return string|null */
	private static function normalize( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( is_bool( $value ) ) {
			$value = $value ? 'true' : 'false';
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		} else {
			$value = (string) $value;
		}
		return mb_strlen( $value ) > self::MAX_LENGTH ? mb_substr( $value, 0, self::MAX_LENGTH ) . '…' : $value;
	}

	/** Stream-style secret detection on the field name and any parent/nested parts. */
	private static function is_secret( $field ) {
		$parts = array( $field );
		if ( preg_match( '/^(.+)\[([^\]]+)\]$/', $field, $matches ) ) {
			$parts[] = $matches[1];
			$parts[] = $matches[2];
		}
		$is_secret = false;
		foreach ( $parts as $part ) {
			if ( preg_match( '/pass|secret|private_key|apikey|token|webhook|license|salt|credential|oauth|signature/i', $part ) ) {
				$is_secret = true;
				break;
			}
			if ( preg_match( '/public_key|publishable_key|site_key/i', $part ) ) {
				continue;
			}
			if ( preg_match( '/_key$/i', $part ) ) {
				$is_secret = true;
				break;
			}
		}
		return (bool) apply_filters( 'aal_changes_is_secret', $is_secret, $field );
	}
}
