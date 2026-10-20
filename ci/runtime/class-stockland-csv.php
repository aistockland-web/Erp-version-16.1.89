<?php
/**
 * Bounded, streaming RFC 4180-style CSV reader for Daily POS imports.
 *
 * CSV is a source format only. Rows are handed to the existing Daily POS
 * validator/staging pipeline; this class never writes business records.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A one-sheet, streaming reader implementing the worksheet-reader interface
 * used by the existing import validator.
 */
class Stockland_CSV {
	const MAX_FILE_BYTES         = 8 * 1024 * 1024;
	const MAX_PHYSICAL_ROW_NODES = 15050;
	const MAX_CELLS_PER_ROW      = 250;
	const MAX_CELL_BYTES         = 65536;
	const SHEET_NAME             = 'CSV';

	/**
	 * Temporary upload path. The REST upload validator verifies the HTTP upload.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Validate size, non-empty content, text encoding and delimiter at open time.
	 *
	 * @param string $file Temporary upload path.
	 * @throws Exception If the CSV file is invalid or outside resource limits.
	 */
	public function __construct( $file ) {
		if ( ! is_string( $file ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			throw new Exception( 'The uploaded CSV file could not be read.' );
		}
		$size  = filesize( $file );
		if ( false === $size || $size <= 0 ) {
			throw new Exception( 'The CSV file is empty.' );
		}
		if ( $size > self::MAX_FILE_BYTES ) {
			throw new Exception( 'CSV file is too large. Maximum allowed size is 8 MB.' );
		}
		$this->file = $file;
		$delimiter = $this->detect_delimiter();
		if ( 1 > $delimiter['columns'] ) {
			throw new Exception( 'Could not detect a CSV delimiter. Upload a delimited CSV file with a header row.' );
		}
	}

	/**
	 * Return one logical sheet so the existing header/mapping workflow is reused.
	 *
	 * @return array<string> Sheet name.
	 */
	public function sheet_names() {
		return array( self::SHEET_NAME );
	}

	/**
	 * Stream parsed CSV records without loading the full file into memory.
	 *
	 * @param string $sheet Requested logical sheet.
	 * @param int    $max_physical_rows Maximum records to inspect.
	 * @return Generator<int, array> Records with row_number and numeric values.
	 * @throws Exception On malformed encoding, unsafe row size or row/cell limits.
	 */
	public function stream_rows( $sheet, $max_physical_rows = self::MAX_PHYSICAL_ROW_NODES ) {
		if ( self::SHEET_NAME !== $sheet ) {
			throw new Exception( 'CSV contains only the CSV data sheet.' );
		}
		$delimiter_data = $this->detect_delimiter();
		$delimiter      = $delimiter_data['delimiter'];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Bounded stream read.
		$handle = fopen( $this->file, 'rb' );
		if ( false === $handle ) {
			throw new Exception( 'Could not open the CSV stream.' );
		}
		$row_number = 0;
		try {
			while ( true ) {
				$values = fgetcsv( $handle, self::MAX_FILE_BYTES + 1, $delimiter, '"', '' );
				if ( false === $values ) {
					break;
				}
				++$row_number;
				if ( $row_number > $max_physical_rows || $row_number > self::MAX_PHYSICAL_ROW_NODES ) {
					throw new Exception( 'CSV exceeds the physical row safety limit. Split the file.' );
				}
				if ( count( $values ) > self::MAX_CELLS_PER_ROW ) {
					throw new Exception( 'CSV contains a row with too many columns.' );
				}
				$clean = array();
				foreach ( $values as $index => $value ) {
					$value = null === $value ? '' : (string) $value;
					if ( strlen( $value ) > self::MAX_CELL_BYTES ) {
						throw new Exception( 'CSV contains a cell larger than 64 KB.' );
					}
					if ( false !== strpos( $value, "\0" ) ) {
						throw new Exception( 'CSV contains a binary NUL byte; export it as UTF-8 text.' );
					}
					if ( 1 !== preg_match( '//u', $value ) ) {
						throw new Exception( 'CSV must contain valid UTF-8 text. Re-export it using UTF-8 encoding.' );
					}
					if ( 1 === $row_number && 0 === $index ) {
						$value = preg_replace( '/^\xEF\xBB\xBF/', '', $value );
					}
					$clean[] = $value;
				}
				yield array(
					'row_number' => $row_number,
					'values'     => $clean,
				);
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close stream.
			fclose( $handle );
		}
	}

	/**
	 * Infer comma, semicolon, tab or pipe delimiter using a short streaming sample.
	 *
	 * @return array{delimiter:string,columns:int} Detected delimiter and modal width.
	 * @throws Exception If the file cannot be sampled or has no usable delimiter.
	 */
	private function detect_delimiter() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Delimiter probe.
		$handle = fopen( $this->file, 'rb' );
		if ( false === $handle ) {
			throw new Exception( 'Could not inspect the CSV file.' );
		}
		$best = array(
			'delimiter' => ',',
			'columns'   => 0,
			'score'     => -1,
		);
		try {
			foreach ( array( ',', ';', "\t", '|' ) as $candidate ) {
				rewind( $handle );
				$widths = array();
				for ( $i = 0; $i < 10; $i++ ) {
					$row = fgetcsv( $handle, self::MAX_FILE_BYTES + 1, $candidate, '"', '' );
					if ( false === $row ) {
						break;
					}
					$width = count( $row );
					if ( $width > 1 ) {
						$widths[] = $width;
					}
				}
				if ( ! $widths ) {
					continue;
				}
				$frequencies = array_count_values( $widths );
				arsort( $frequencies, SORT_NUMERIC );
				$mode  = (int) array_key_first( $frequencies );
				$count = reset( $frequencies );
				$score = ( (int) $count * 10000 ) + ( count( $widths ) * 1000 );
				if ( $score > $best['score'] ) {
					$best = array(
						'delimiter' => $candidate,
						'columns'   => $mode,
						'score'     => $score,
					);
				}
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close stream.
			fclose( $handle );
		}
		if ( $best['columns'] < 2 ) {
			throw new Exception(
				'Could not detect a CSV delimiter. Use comma, semicolon, tab or pipe.'
			);
		}
		return $best;
	}
}
