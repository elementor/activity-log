<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Exporter_jsonl {
	/**
	 * Exporter name
	 *
	 * @var string
	 */
	public $name = 'JSON Lines';

	/**
	 * Exporter ID
	 *
	 * @var string
	 */
	public $id = 'jsonl';

	/**
	 * When true, write() accepts a Generator instead of a fully materialized array.
	 *
	 * @var bool
	 */
	public $supports_iterable = true;

	/**
	 * When false, the export omits the IP column.
	 *
	 * @var bool
	 */
	public $include_ip = false;

	const JSON_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/**
	 * Writes JSON Lines data for download
	 *
	 * @param array|Traversable $data Rows to output.
	 * @param array $columns Column names included in data set.
	 * @return void
	 */
	public function write( $data, $columns ) {
		$is_test_mode_off = ! defined( 'AAL_TESTMODE' ) || ( defined( 'AAL_TESTMODE' ) && ! AAL_TESTMODE );

		if ( $is_test_mode_off ) {
			header( 'Content-Type: application/x-ndjson; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="activity-log-export.jsonl"' );
		}

		$fp = fopen( 'php://output', 'w' );

		foreach ( $data as $row ) {
			fwrite( $fp, wp_json_encode( $row, self::JSON_ENCODE_FLAGS ) . "\n" );
		}

		if ( $is_test_mode_off ) {
			fflush( $fp );
			flush();
		}

		fclose( $fp );

		if ( $is_test_mode_off ) {
			exit;
		}
	}
}
