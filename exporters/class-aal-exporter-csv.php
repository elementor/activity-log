<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Exporter_csv {
    /**
	 * Exporter name
	 *
	 * @var string
	 */
	public $name = 'CSV';

	/**
	 * Exporter ID
	 *
	 * @var string
	 */
	public $id = 'csv';

	/**
	 * When true, write() accepts a Generator instead of a fully materialized array.
	 *
	 * @var bool
	 */
	public $supports_iterable = true;

	const FORMULAS_START_CHARACTERS = [ '=', '-', '+', '@', "\t", "\r" ];

	/** PHP 8.4 deprecates relying on the default $escape argument. */
	const CSV_ENCLOSURE = '"';
	const CSV_ESCAPE = '\\';

	/**
	 * Writes CSV data for download
	 *
	 * @param array|Traversable $data Rows to output.
	 * @param array $columns Column names included in data set.
	 * @return void
	 */
	public function write( $data, $columns ) {
		$is_test_mode_off = ! defined( 'AAL_TESTMODE' ) || ( defined( 'AAL_TESTMODE' ) && ! AAL_TESTMODE );

		if ( $is_test_mode_off ) {
			header( 'Content-type: text/csv' );
			header( 'Content-Disposition: attachment; filename="activity-log-export.csv"' );
		}

		$fp = fopen( 'php://output', 'w' );
		
		$separator = apply_filters( 'aal_export_csv_separator', ',' );

		fputcsv( $fp, $columns, $separator, self::CSV_ENCLOSURE, self::CSV_ESCAPE );

		foreach ( $data as $row ) {
			$encoded_row = $this->get_encoded_row( $row );
			fputcsv( $fp, $encoded_row, $separator, self::CSV_ENCLOSURE, self::CSV_ESCAPE );
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

	private function get_encoded_row( $row ) {
		$result = [];

		foreach ( $row as $key => $value ) {
			$encoded_value = $value;
			if ( in_array( substr( (string) $value, 0, 1 ), self::FORMULAS_START_CHARACTERS, true ) ) {
				$encoded_value = "'" . $value;
			}

			$result[ $key ] = $encoded_value;
		}

		return $result;
	}
}
