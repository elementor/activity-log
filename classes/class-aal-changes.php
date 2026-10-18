<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

final class AAL_Changes {

	const MAX_FIELDS = 20;
	const MAX_LENGTH = 200;

	public static function diff( array $old, array $new ) {
		$flat_old = self::flatten( $old );
		$flat_new = self::flatten( $new );
		$changes  = array();
		foreach ( array_unique( array_merge( array_keys( $flat_old ), array_keys( $flat_new ) ) ) as $field ) {
			$old_raw = array_key_exists( $field, $flat_old ) ? $flat_old[ $field ] : null;
			$new_raw = array_key_exists( $field, $flat_new ) ? $flat_new[ $field ] : null;
			$old_norm = self::normalize( $old_raw );
			$new_norm = self::normalize( $new_raw );
			if ( $old_norm === $new_norm ) {
				continue;
			}
			$secret = self::is_secret( $field )
				|| self::has_nested_secret( $old_raw )
				|| self::has_nested_secret( $new_raw );
			$changes[ $field ] = array(
				( $secret && null !== $old_norm && '' !== $old_norm ) ? '[redacted]' : self::truncate( $old_norm ),
				( $secret && null !== $new_norm && '' !== $new_norm ) ? '[redacted]' : self::truncate( $new_norm ),
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
		return $value;
	}

	private static function truncate( $value ) {
		if ( null === $value ) {
			return null;
		}
		return mb_strlen( $value ) > self::MAX_LENGTH ? mb_substr( $value, 0, self::MAX_LENGTH ) . '…' : $value;
	}

	private static function has_nested_secret( $value ) {
		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return false;
		}
		foreach ( (array) $value as $key => $nested ) {
			if ( is_string( $key ) && self::is_secret( $key ) ) {
				return true;
			}
			if ( ( is_array( $nested ) || is_object( $nested ) ) && self::has_nested_secret( $nested ) ) {
				return true;
			}
		}
		return false;
	}

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
