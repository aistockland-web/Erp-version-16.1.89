<?php
/**
 * Plain-text view and clean rebuild of private HR documents so they can be edited inside the ERP.
 *
 * Reads the text of a DOCX or TXT document and rebuilds an edited version as a clean DOCX or TXT. Layout,
 * images and styling of the original are not carried over; the editor tells the user this. No external
 * entities are loaded and nothing is written outside the caller's private vault.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Text extraction and DOCX/TXT rebuilding for the private HR library. */
final class Stockland_HR_Document_Text {
	const MAX_CHARS = 200000;
	const W_NS      = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	/**
	 * Whether DOCX text editing is available on this server.
	 *
	 * @return bool True when ZipArchive and DOM are installed.
	 */
	public static function docx_supported() {
		return class_exists( 'ZipArchive' ) && class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' );
	}

	/**
	 * Extracts editable text from a decrypted document.
	 *
	 * @param string $path Readable temporary path of the decrypted document.
	 * @param string $ext Lower-case extension (docx or txt).
	 * @return array{editable:bool,text:string,note:string}
	 */
	public static function extract( $path, $ext ) {
		$ext = strtolower( (string) $ext );
		if ( 'txt' === $ext ) {
			$raw = file_get_contents( $path, false, null, 0, self::MAX_CHARS * 4 );
			if ( ! is_string( $raw ) || ! self::is_utf8( $raw ) ) {
				return array( 'editable' => false, 'text' => '', 'note' => 'This text file is not valid UTF-8, so it cannot be edited safely here.' );
			}
			return array( 'editable' => true, 'text' => self::normalise( $raw ), 'note' => '' );
		}
		if ( 'docx' !== $ext ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'This file type cannot be edited as text. Use "Replace with a file" to save a new version.' );
		}
		if ( ! self::docx_supported() ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'This server cannot read Word documents (the PHP zip extension is missing). Use "Replace with a file", or ask the host to enable ZipArchive.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'The Word document could not be opened.' );
		}
		$index = $zip->locateName( 'word/document.xml' );
		$stat  = false === $index ? false : $zip->statIndex( $index );
		if ( ! is_array( $stat ) || (int) $stat['size'] < 1 || (int) $stat['size'] > 20 * 1024 * 1024 ) {
			$zip->close();
			return array( 'editable' => false, 'text' => '', 'note' => 'The Word document body is missing or too large to edit here.' );
		}
		$xml = $zip->getFromIndex( $index );
		$zip->close();
		if ( ! is_string( $xml ) || false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'The Word document uses unsupported markup and cannot be edited here.' );
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'The Word document could not be read.' );
		}
		$xp = new DOMXPath( $dom );
		$xp->registerNamespace( 'w', self::W_NS );
		$body = $xp->query( '/w:document/w:body' )->item( 0 );
		if ( ! $body ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'The Word document has no body text.' );
		}
		$lines = array();
		foreach ( $body->childNodes as $node ) {
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}
			if ( 'p' === $node->localName ) {
				$lines[] = self::paragraph_text( $node, $xp );
			} elseif ( 'tbl' === $node->localName ) {
				foreach ( $xp->query( './w:tr', $node ) as $row ) {
					$cells = array();
					foreach ( $xp->query( './w:tc', $row ) as $cell ) {
						$parts = array();
						foreach ( $xp->query( './w:p', $cell ) as $p ) {
							$t = self::paragraph_text( $p, $xp );
							if ( '' !== $t ) {
								$parts[] = $t;
							}
						}
						$cells[] = implode( ' / ', $parts );
					}
					$lines[] = '| ' . implode( ' | ', $cells ) . ' |';
				}
			}
		}
		$text = self::normalise( implode( "\n", $lines ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > self::MAX_CHARS ) {
			return array( 'editable' => false, 'text' => '', 'note' => 'This document is too long to edit as text here. Use "Replace with a file".' );
		}
		return array(
			'editable' => true,
			'text'     => $text,
			'note'     => 'Editing saves a clean text version of this document. Page layout, images and fonts of the original file are not carried over.',
		);
	}

	/**
	 * Rebuilds a clean DOCX from edited text. Lines starting with "# " or "## " become headings.
	 *
	 * @param string $text Edited document text.
	 * @return string|WP_Error Binary DOCX content.
	 */
	public static function build_docx( $text ) {
		if ( ! self::docx_supported() ) {
			return new WP_Error( 'hr_docx_unavailable', 'This server cannot write Word documents (the PHP zip extension is missing).', array( 'status' => 503 ) );
		}
		$paragraphs = '';
		foreach ( explode( "\n", self::normalise( $text ) ) as $line ) {
			$size = 0;
			if ( 0 === strpos( $line, '## ' ) ) {
				$line = substr( $line, 3 );
				$size = 28;
			} elseif ( 0 === strpos( $line, '# ' ) ) {
				$line = substr( $line, 2 );
				$size = 34;
			}
			$props = $size ? '<w:rPr><w:b/><w:sz w:val="' . (int) $size . '"/></w:rPr>' : '';
			$runs  = '';
			foreach ( explode( "\t", $line ) as $i => $part ) {
				$runs .= ( $i > 0 ? '<w:r><w:tab/></w:r>' : '' ) . ( '' === $part ? '' : '<w:r>' . $props . '<w:t xml:space="preserve">' . htmlspecialchars( $part, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</w:t></w:r>' );
			}
			$paragraphs .= '<w:p>' . $runs . '</w:p>';
		}
		$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . self::W_NS . '"><w:body>' . $paragraphs . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';
		$types    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
		$rels     = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
		$temp     = tempnam( sys_get_temp_dir(), 'stockland-docx-' );
		if ( false === $temp ) {
			return new WP_Error( 'hr_docx_temp_failed', 'A temporary file for the document could not be created.', array( 'status' => 503 ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp, ZipArchive::OVERWRITE ) ) {
			@unlink( $temp );
			return new WP_Error( 'hr_docx_build_failed', 'The Word document could not be built.', array( 'status' => 500 ) );
		}
		$zip->addFromString( '[Content_Types].xml', $types );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'word/document.xml', $document );
		$closed = $zip->close();
		$bytes  = $closed ? file_get_contents( $temp ) : false;
		@unlink( $temp );
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return new WP_Error( 'hr_docx_build_failed', 'The Word document could not be built.', array( 'status' => 500 ) );
		}
		return $bytes;
	}

	/**
	 * Normalises line endings and strips control characters (tabs and newlines kept).
	 *
	 * @param string $text Raw text.
	 * @return string Clean text.
	 */
	public static function normalise( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		return rtrim( $text );
	}

	/**
	 * Concatenates the text runs of one Word paragraph.
	 *
	 * @param DOMElement $paragraph Paragraph node.
	 * @param DOMXPath   $xp XPath bound to the document.
	 * @return string Paragraph text.
	 */
	private static function paragraph_text( $paragraph, $xp ) {
		$out = self::heading_prefix( $paragraph, $xp );
		foreach ( $xp->query( './/w:t | .//w:tab | .//w:br', $paragraph ) as $n ) {
			if ( 't' === $n->localName ) {
				$out .= $n->textContent;
			} elseif ( 'tab' === $n->localName ) {
				$out .= "\t";
			} else {
				$out .= "\n";
			}
		}
		return $out;
	}

	/**
	 * Returns "# " or "## " when the paragraph is a heading (Word heading style or bold, large text), else an empty string.
	 *
	 * @param DOMElement $paragraph Paragraph node.
	 * @param DOMXPath   $xp XPath bound to the document.
	 * @return string Heading marker including the trailing space, or an empty string.
	 */
	private static function heading_prefix( $paragraph, $xp ) {
		$style = $xp->query( './w:pPr/w:pStyle/@w:val', $paragraph )->item( 0 );
		if ( $style && preg_match( '/^(?:Heading|Title)\s*([12])?$/i', trim( $style->nodeValue ), $m ) ) {
			return ( isset( $m[1] ) && '2' === $m[1] ) ? '## ' : '# ';
		}
		$runs  = $xp->query( './w:r[w:t]', $paragraph );
		$count = $runs->length;
		if ( 0 === $count ) {
			return '';
		}
		$max = 0;
		foreach ( $runs as $run ) {
			if ( 0 === $xp->query( './w:rPr/w:b', $run )->length ) {
				return '';
			}
			$sz  = $xp->query( './w:rPr/w:sz/@w:val', $run )->item( 0 );
			$max = max( $max, $sz ? (int) $sz->nodeValue : 0 );
		}
		if ( $max >= 34 ) {
			return '# ';
		}
		return $max >= 28 ? '## ' : '';
	}

	/**
	 * Checks UTF-8 validity.
	 *
	 * @param string $s Candidate text.
	 * @return bool True when valid UTF-8.
	 */
	private static function is_utf8( $s ) {
		return 1 === preg_match( '//u', $s );
	}
}
