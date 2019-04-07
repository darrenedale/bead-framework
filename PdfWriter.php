<?php

/**
 * Defines the PdfWriter class.
 *
 * This class is based (now very loosely) on the class FPDF v1.53. The original
 * note for that class follows this.
 *
 * The class also integrates Steven Wittens' unicode support into the FPDF
 * source to produce the baseline against which this class is created. Steven's
 * original notes follow Olivier's.
 *
 * There are several other fragments that have been inserted, mostly sourced
 * from the FPDF website (http://www.fpdf.org/). Where this is the case, it is
 * noted in the source.
 *
 * <pre>
 *******************************************************************************
 * Software: FPDF                                                              *
 * Version:  1.53                                                              *
 * Date:     2004-12-31                                                        *
 * Author:   Olivier PLATHEY                                                   *
 * License:  Freeware                                                          *
 *                                                                             *
 * You may use, modify and redistribute this software as you wish.             *
 *******************************************************************************
 * </pre>
 * <pre>
 *******************************************************************************
 * Software: UFPDF, Unicode Free PDF generator                                 *
 * Version:  0.1                                                               *
 *           based on FPDF 1.52 by Olivier PLATHEY                             *
 * Date:     2004-09-01                                                        *
 * Author:   Steven Wittens &lt;steven@acko.net&gt;                            *
 * License:  GPL                                                               *
 *                                                                             *
 * UFPDF is a modification of FPDF to support Unicode through UTF-8.           *
 *                                                                             *
 *******************************************************************************
 * </pre>
 *
 * \warning This class may depend on the availbility of the `convert` tool
 * from the ImageMagick package for automatic transparency support in PNG
 * images. It is used as a last resort to try to ensure that transparency mask
 * images have the DeviceGray colourspace if the GMagick and IMagick PHP
 * extensions are not present. If the command is absent and neither extension
 * is present, PNG images with alpha channels will not have their alpha channel
 * supported in the final PDF document. This also means that the class cannot
 * be deployed on non-unix platforms without a code change and still have this
 * fallback method of colourspace conversion in use.
 *
 * ### Dependencies
 * - classes.AppLog.php
 * - classes/equit/Colour.php
 *
 * \todo
 * - identify cause and fix division by zero in image() - related to parsing
 *   of image info in parse[Png|Jpeg|...]
 * - refactor image alpha channel support so that it is transparent to the user
 *   of the class
 * - a few methods need documentation.
 * - reorganise methods so that order is:
 *     constructor/destructor
 *     private static
 *     protected static
 *     public static
 *     private
 *     protected
 *     public
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated to `[]` syntax for array literals.
 *
 * @file PdfWriter.php
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 */

namespace Equit;

use DateTime;
use Exception;
use Gmagick;
use GmagickException;
use Imagick;

/**
 * Class enabling creation of PDF documents.
 *
 * This class is a simplified version Olivier Plathey's FPDF class and Steven Wittens UFPDF class.
 *
 * The class makes it very easy to create and export PDF files. Both images and text can be placed on the page at
 * arbitrary locations, and text can be both single-line and flowed over multiple lines. Images can be resized
 * arbitrarily either preserving or not preserving the original image's aspect ratio.
 *
 * Unlike the standard PDF way, the coordinate system of the PdfWriter has its origin at the top-left corner of the page
 * extending right and down (PDF's internal coordinate space originates at the bottom left and extends right and up).
 * While this makes it more difficult for the novice user to translate his/her skills from this class to creating PDFs
 * directly, it does make it easier to start using PDFs using the class as most (textual) electronic information flows
 * from top to bottom rather than from bottom to top.
 *
 * The system of measurement used by the class is mm. All methods that require measurements expect mm, except font
 * sizes, which expect points. All all measurements can also be retrieved in points, using the methods suffixed with
 * `Pt`.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
 *
 * ### Events
 * This module does not emit any events.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not read any settings.
 *
 * ### Session Data
 * This module does not create a session context.
 *
 * @class PdfWriter
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @ingroup libequit
 * @package libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class PdfWriter {
	const ClassVersion = 0.8;

	/* viewer layout mode constants */
	const ContinuousLayout = 0;
	const SingleLayout = 1;
	const TwoColumnLayout = 2;

	/* viewer zoom constants */
	const ViewerDefaultZoom = -1;
	const FullWidthZoom = -2;
	const FullPageZoom = -3;
	const RealZoom = -4;

	/* page orientation constants
	 * must start at 1 because 0 can mean "use existing orientation" when
	 * passing details internally */
	const PortraitOrientation = 1;
	const LandscapeOrientation = 2;

	/* font style flags */
	const PlainStyle = 0x00;
	const BoldStyle = 0x01;
	const ItalicStyle = 0x02;
	const UnderlinedStyle = 0x04;

	/* horizontal alignment types */
	const AlignLeft = 0;
	const AlignCentre = 1;
	const AlignRight = 2;

	/* cell border flags */
	const NoBorder = 0x00;
	const TopBorder = 0x01;
	const BottomBorder = 0x02;
	const LeftBorder = 0x04;
	const RightBorder = 0x08;
	const HorizontalBorders = 0x03;
	const VerticalBorders = 0x0c;
	const AllBorders = 0x0f;

	/** The default page format.*/
	const DefaultPageFormat = "a4";

	/** The default page orientation. */
	const DefaultOrientation = PdfWriter::PortraitOrientation;

	/** The default compression setting. */
	const DefaultCompression = true;

	/** The default page margins. (1cm = 28.35pt) * 2.5cm = 70.875pt */
	const DefaultMargin = 70.875;

	/** The  default inner margin (padding) in rendered cells - 2.835pt = 1mm */
	const DefaultCellMargin = 2.835;

	/** The default thickness of rendered lines - 0.567pt = 0.2mm */
	const DefaultLineWidth = 0.567;

	/** The PDF version of documents created by this class. */
	const PdfVersion = '1.4';

	/** Points per mm conversion factor. */
	const PtPerMm = 2.83464567;        /* how many pt = 1 mm */

	/** Mm per pt conversion factor. */
	const MmPerPt = 0.352777778;    /* how many mm = 1 pt */

	/** The standard PDF font definitions. */
	private static $s_coreFonts = [
		"courier" => "Courier", "courierB" => "Courier-Bold", "courierI" => "Courier-Oblique", "courierBI" => "Courier-BoldOblique",
		"helvetica" => "Helvetica", "helveticaB" => "Helvetica-Bold", "helveticaI" => "Helvetica-Oblique", "helveticaBI" => "Helvetica-BoldOblique",
		"times" => "Times-Roman", "timesB" => "Times-Bold", "timesI" => "Times-Italic", "timesBI" => "Times-BoldItalic",
		"symbol" => "Symbol",
		"zapfdingbats" => "ZapfDingbats",
	];

	/**
	 * Available page formats.
	 *
	 * Sizes are in points, width first then height.
	 */
	private static $s_availablePageFormats = [
		"a3" => [841.89, 1190.55],
		"a4" => [595.28, 841.89],
		"a5" => [420.94, 595.28],
		"letter" => [612, 792],
		"legal" => [612, 1008],
	];

	/* document meta-info */
	private $m_title;                               /* title */
	private $m_subject;                             /* subject */
	private $m_author;                              /* author */
	private $m_keywords;                            /* keywords */
	private $m_creator;                             /* creator */
	private $m_pageCountPlaceholder;                      /* alias for total number of pages */
	private $m_duplex = false;                        /* single- or double-sided document; default is single */
	private $m_creationDate;                        /* the date the document was created */

	/* preferred view mode information */
	private $m_zoomMode;                            /* zoom display mode */
	private $m_layoutMode;                          /* layout display mode */

	/* state information */
	private $m_pageNumber;                          /* current page number */
	private $m_objectNumber;                        /* current object number */
	private $m_offsets;                             /* array of object offsets */
	private $m_buffer = '';                         /* buffer holding in-memory PDF data */
	private $m_pages = [];                          /* array containing pages */
	private $m_state = 0;                           /* current document state */
	private $m_compress;                            /* compression flag */
	private $m_documentOrientation;                 /* default page orientation for document */
	private $m_currentOrientation;                  /* current page orientation */
	private $m_pageOrientationChanges = [];         /* array, one entry per page: true if page differs from document orientation, false if the same */
	private $m_formatWidthPt, $m_formatHeightPt;    /* dimensions of document page format in points */
	private $m_formatWidth, $m_formatHeight;        /* dimensions of document page format in user unit */
	private $m_currentPageWidthPt;                  /* dimensions of current page in points */
	private $m_currentPageHeightPt;
	private $m_currentPageWidth;                    /* dimensions of current page in user unit */
	private $m_currentPageHeight;
	private $m_lMargin;                             /* left margin of current page */
	private $m_tMargin;                             /* top margin of current page */
	private $m_rMargin;                             /* right margin of current page */
	private $m_bMargin;                             /* page break margin */
	private $m_cMargin;                             /* cell margin */
	private $m_x, $m_y;                             /* current position in mm for cell positioning */
	private $m_lastCellHeight = 0;                  /* height of last cell printed */
	private $m_lineWidth;                           /* line width in user unit */

	/* embedded element content */
	private $m_fonts = [];                     /* array of used fonts */
	private $m_fontFiles = [];                 /* array of font files */
	private $m_diffs = [];                     /* array of encoding differences */
	private $m_images = [];                    /* array of used images */

	private $m_tmpFiles = [];                  /* temporary files */

	private $m_debugOutFlag = false;                /* a flag to use when debugging that methods can set when they call outputToBuffer() to force it to output some debugging information to the message log */

	/* font information */
	private $m_currentFontFamily = '';              /* current font family */
	private $m_currentFontStyle = '';               /* current font style */
	private $m_underlineFlag = false;               /* underlining flag */
	private $m_currentFontInfo;                     /* current font info */
	private $m_currentFontSizePt = 12;              /* current font size in points */
	private $m_currentFontSize;                     /* current font size in user unit */

	/* colour information
	 * colours can be set using rgb, grey level or Colour objects, and are
	 * converted to PDF commands and cached when set. the first bunch of
	 * attributes store the PDF commands, the second bunch store the Colour
	 * objects. The latter are not used internally, just stored for retrieval by
	 * client code */
	private $m_colourFlag = false;                  /* indicates whether fill and text colors are different */
	private $m_drawColour;                          /* PDF commands for drawing color */
	private $m_fillColour;                          /* PDF commands for filling color */
	private $m_textColour;                          /* PDF commands for text color */
	private $m_drawColourObj;                       /* drawing colour Colour object */
	private $m_fillColourObj;                       /* fill colour Colour object */
	private $m_textColourObj;                       /* text color Colour object */

	/* formatting information */
	private $m_wordSpacing = 0;                     /* word spacing */
	private $m_autoPageBreak;                       /* automatic page breaking */
	private $m_pageBreakTrigger;                    /* threshold used to trigger page breaks */

	/* alpha blending */
	private $m_extGStates = [];                /* ExtGStates for alpha support */
	private $m_currentAlpha = 1.0;                  /* the current alpha value */

	/**
	 * Initialise a new PDF document.
	 *
	 * @param $orientation int _optional_ The orientation of the new document.
	 * @param $format string _optional_ The page format
	 *
	 * The default orientation is portrait; the default page format is A4.
	 *
	 * At present it is not possible to alter the unit of measurement during the lifetime of the object because at the
	 * moment objects store measurements both in pt and the user unit so to change would invalidate all the existing
	 * user unit measurement. It is intended that the class will be updated to provide user units by reverse converting
	 * the point measurement so that the unit of measurement can be altered during the lifetime of the object.
	 *
	 * @throws \Exception If a DateTime() object cannot be created (which will never happen).
	 */
	public function __construct(int $orientation = self::DefaultOrientation, string $format = self::DefaultPageFormat) {
		$this->m_creationDate    = new DateTime();
		$this->m_pageNumber      = 0;
		$this->m_objectNumber    = 2;
		$this->m_currentFontSize = self::ptToMm($this->m_currentFontSizePt);
		$this->m_drawColour      = "0 G";
		$this->m_drawColourObj   = new Colour(0, 0, 0);
		$this->m_fillColour      = "0 g";
		$this->m_fillColourObj   = new Colour(0, 0, 0);
		$this->m_textColour      = "0 g";
		$this->m_textColourObj   = new Colour(0, 0, 0);

		/* page format */
		$format = $this->_validatePageFormat($format, $valid);

		if(!$valid) {
			AppLog::error("Unrecognised page format: using default (" . self::DefaultPageFormat . ")", __FILE__, __LINE__, __FUNCTION__);
			$format = self::$s_availablePageFormats[self::DefaultPageFormat];
		}

		/* set dimensions in pt */
		$this->m_formatWidthPt  = $format[0];
		$this->m_formatHeightPt = $format[1];

		/* set dimensions in user units */
		$this->m_formatWidth  = self::ptToMm($this->m_formatWidthPt);
		$this->m_formatHeight = self::ptToMm($this->m_formatHeightPt);

		/* LibEquit\Page orientation */
		$this->m_documentOrientation = $this->m_currentOrientation = $this->_validateOrientation($orientation, $valid);

		if(!$valid) {
			AppLog::error("Unrecognised orientation ($orientation): using default orientation (" . self::DefaultOrientation . ")", __FILE__, __LINE__, __FUNCTION__);
		}

		if($this->m_documentOrientation == self::PortraitOrientation) {
			$this->m_currentPageWidthPt  = $this->m_formatWidthPt;
			$this->m_currentPageHeightPt = $this->m_formatHeightPt;
		}
		else {
			$this->m_currentPageWidthPt  = $this->m_formatHeightPt;
			$this->m_currentPageHeightPt = $this->m_formatWidthPt;
		}

		$this->m_currentPageWidth  = self::ptToMm($this->m_currentPageWidthPt);
		$this->m_currentPageHeight = self::ptToMm($this->m_currentPageHeightPt);

		/*LibEquit\Page margins (1 cm) */
		$margin = self::ptToMm(self::DefaultMargin);
		$this->setMargins($margin, $margin);

		/*Interior cell margin */
		$this->m_cMargin = self::ptToMm(self::DefaultCellMargin);

		/*Line width (0.2 mm) */
		$this->m_lineWidth = self::ptToMm(self::DefaultLineWidth);

		/*Automatic page break */
		$this->setAutoPageBreak(true, /* 2 * */
			$margin);

		/*Full width display mode */
		$this->setDisplayMode(self::FullWidthZoom);

		/*Enable compression */
		$this->setCompression(self::DefaultCompression);
	}

	/**
	 * Destroy the object.
	 *
	 * Ensures any temp files still in use are cleaned up.
	 */
	public function __destruct() {
		$this->cleanTempFiles();
	}

	/**
	 * Get a string that conforms to the PDF specification's definition
	 * of a numeric value.
	 *
	 * @param $n float|int the number to convert.
	 * @param $dp int is the number of decimal places to use. The default is
	 * 2.
	 *
	 * @return string The number formatted for use as a numeric value in a PDF
	 * stream, or an empty string if `$n` is not a number or numeric string.
	 */
	public static function postscriptNumber($n, int $dp = 2): string {
		if(is_string($n) && is_numeric($n)) {
			$n = doubleval($n);
		}

		if(is_int($n)) {
			return number_format($n, 0, ".", "");
		}
		else if(is_real($n)) {
			return number_format($n, is_numeric($dp) ? intval($dp) : 2, ".", "");
		}

		return "";
	}

	/**
	 * Convert points to mm.
	 *
	 * @param $v float The number of points to convert.
	 *
	 * @return float The number of mm.
	 */
	public static function ptToMm(float $v): float {
		return self::MmPerPt * $v;
	}

	/**
	 * Convert mm to points.
	 *
	 * @param $v float The number of mm to convert.
	 *
	 * @return float The number of points.
	 */
	public static function mmToPt(float $v): float {
		return self::PtPerMm * $v;
	}

	/**
	 * Convert a font style string to a style mask.
	 *
	 * @param $s `string` The style string to convert.
	 *
	 * This method will translate a string containing the letters B, I and/or U
	 * for bold, italic and underscore, respectively, into a bitmask of style
	 * constants. The provided string is not case sensitive and may contain
	 * multiple instances of B, I and/or U.
	 *
	 * This is used internally to convert user-provided style strings into
	 * style constants.
	 *
	 * @return int The style, or PlainStyle if the provided string contains
	 * no style identifiers.
	 */
	protected static function stringToStyle(string $s): int {
		$ret = self::PlainStyle;

		for($i = 0; $i < strlen($s); ++$i) {
			switch($s{$i}) {
				case "b":
				case "B":
					$ret |= self::BoldStyle;
					break;

				case "i":
				case "I":
					$ret |= self::ItalicStyle;
					break;

				case "u":
				case "U":
					$ret |= self::UnderlinedStyle;
					break;
			}
		}

		return $ret;
	}

	/**
	 * Convert a style mask to a font style string.
	 *
	 * @param $s `int` The style mask to convert.
	 *
	 * This method will translate a bitmask of style constants into a string
	 * containing the letters B, I and/or U for bold, italic and underscore,
	 * respectively.
	 *
	 * @return string The stylestring.
	 */
	protected static function styleToString(int $s): string {
		$ret = "";

		if($s & self::BoldStyle) {
			$ret .= "B";
		}

		if($s & self::ItalicStyle) {
			$ret .= "I";
		}

		if($s & self::UnderlinedStyle) {
			$ret .= "U";
		}

		return $ret;
	}

	/**
	 * Convert a UTF-8 encoded string to unicode codepoints.
	 *
	 * @param $str `string` The string to convert.
	 *
	 * @return array[int] The unicode code points for the characters in the
	 * string.
	 */
	protected static function utf8ToCodepoints(string $str): array {
		$out = [];
		$l   = strlen($str);

		for($i = 0; $i < $l; $i++) {
			$c = ord($str{$i});

			if($c < 0x80) {
				// ASCII
				$out[] = ord($str{$i});
			}
			else if($c < 0xC0) {
				// Lost continuation byte
				$out[] = 0xFFFD;
				continue;
			}
			else {
				// Multibyte sequence leading byte
				if($c < 0xE0) {
					$s = 2;
				}
				else if($c < 0xF0) {
					$s = 3;
				}
				else if($c < 0xF8) {
					$s = 4;
				}
				else {
					// 5/6 byte sequences not possible for Unicode.
					$out[] = 0xFFFD;

					while(ord($str{$i + 1}) >= 0x80 && ord($str{$i + 1}) < 0xC0) {
						++$i;
					}

					continue;
				}

				$q = [$c];

				// Fetch rest of sequence
				while(ord($str{$i + 1}) >= 0x80 && ord($str{$i + 1}) < 0xC0) {
					++$i;
					$q[] = ord($str{$i});
				}

				// Check length
				if(count($q) != $s) {
					$out[] = 0xFFFD;
					continue;
				}

				switch($s) {
					case 2:
						$cp = (($q[0] ^ 0xC0) << 6) | ($q[1] ^ 0x80);

						if($cp < 0x80) {
							// Overlong sequence
							$out[] = 0xFFFD;
						}
						else {
							$out[] = $cp;
						}

						continue;

					case 3:
						$cp = (($q[0] ^ 0xE0) << 12) | (($q[1] ^ 0x80) << 6) | ($q[2] ^ 0x80);

						if($cp < 0x800) {
							// Overlong sequence
							$out[] = 0xFFFD;
						}
						else if($c > 0xD800 && $c < 0xDFFF) {
							// Check for UTF-8 encoded surrogates (caused by a bad UTF-8 encoder)
							$out[] = 0xFFFD;
						}
						else {
							$out[] = $cp;
						}
						continue;

					case 4:
						$cp = (($q[0] ^ 0xF0) << 18) | (($q[1] ^ 0x80) << 12) | (($q[2] ^ 0x80) << 6) | ($q[3] ^ 0x80);

						if($cp < 0x10000) {
							// Overlong sequence
							$out[] = 0xFFFD;
						}
						else if($cp >= 0x10FFFF) {
							// Outside of the Unicode range
							$out[] = 0xFFFD;
						}
						else {
							$out[] = $cp;
						}

						continue;
				}
			}
		}

		return $out;
	}

	/**
	 * Convert a UTF-8 encoded string to a UTF-16 encoded string.
	 *
	 * @param $txt string The text to convert.
	 * @param $bom bool Whether or not to include the byte order mark at
	 * the start of the UTF-16 content.
	 *
	 * The UTF-16 characters in the converted strings are big-endian.
	 *
	 * @return string The UTF-16 representation of the string.
	 */
	protected static function utf8ToUtf16be(string $txt, bool $bom = true): string {
		$l   = strlen($txt);
		$out = $bom ? "\xFE\xFF" : "";

		for($i = 0; $i < $l; ++$i) {
			$c = ord($txt{$i});

			if($c < 0x80) {
				// ASCII
				$out .= "\x00" . $txt{$i};
			}
			else if($c < 0xC0) {
				// Lost continuation byte
				$out .= "\xFF\xFD";
				continue;
			}
			else {
				// Multibyte sequence leading byte
				if($c < 0xE0) {
					$s = 2;
				}
				else if($c < 0xF0) {
					$s = 3;
				}
				else if($c < 0xF8) {
					$s = 4;
				}
				else {
					// 5/6 byte sequences not possible for Unicode.
					$out .= "\xFF\xFD";

					while(ord($txt{$i + 1}) >= 0x80 && ord($txt{$i + 1}) < 0xC0) {
						++$i;
					}

					continue;
				}

				$q = [$c];

				// Fetch rest of sequence
				while(ord($txt{$i + 1}) >= 0x80 && ord($txt{$i + 1}) < 0xC0) {
					++$i;
					$q[] = ord($txt{$i});
				}

				// Check length
				if(count($q) != $s) {
					$out .= "\xFF\xFD";
					continue;
				}

				switch($s) {
					case 2:
						$cp = (($q[0] ^ 0xC0) << 6) | ($q[1] ^ 0x80);

						if($cp < 0x80) {
							// Overlong sequence
							$out .= "\xFF\xFD";
						}
						else {
							$out .= chr($cp >> 8);
							$out .= chr($cp & 0xFF);
						}

						continue;

					case 3:
						$cp = (($q[0] ^ 0xE0) << 12) | (($q[1] ^ 0x80) << 6) | ($q[2] ^ 0x80);

						if($cp < 0x800) {
							// Overlong sequence
							$out .= "\xFF\xFD";
						}
						else if($c > 0xD800 && $c < 0xDFFF) {
							// Check for UTF-8 encoded surrogates (caused by a bad UTF-8 encoder)
							$out .= "\xFF\xFD";
						}
						else {
							$out .= chr($cp >> 8);
							$out .= chr($cp & 0xFF);
						}

						continue;

					case 4:
						$cp = (($q[0] ^ 0xF0) << 18) | (($q[1] ^ 0x80) << 12) | (($q[2] ^ 0x80) << 6) | ($q[3] ^ 0x80);

						if($cp < 0x10000) {
							// Overlong sequence
							$out .= "\xFF\xFD";
						}
						else if($cp >= 0x10FFFF) {
							// Outside of the Unicode range
							$out .= "\xFF\xFD";
						}
						else {
							// Use surrogates
							$cp -= 0x10000;
							$s1 = 0xD800 | ($cp >> 10);
							$s2 = 0xDC00 | ($cp & 0x3FF);

							$out .= chr($s1 >> 8);
							$out .= chr($s1 & 0xFF);
							$out .= chr($s2 >> 8);
							$out .= chr($s2 & 0xFF);
						}

						continue;
				}
			}
		}

		return $out;
	}

	/**
	 * Fetch the path to the font files.
	 *
	 * @return string The path to font files.
	 */
	protected static function fontPath(): string {
		return "fonts/PdfWriter/";
	}

	/**
	 * Clean up any temp files used.
	 */
	private function cleanTempFiles(): void {
		foreach($this->m_tmpFiles as $tmp) {
			@unlink($tmp);
		}

		$this->m_tmpFiles = [];
	}

	/**
	 * Set the page margins.
	 *
	 * @param $left float The left margin.
	 * @param $top float The top margin.
	 * @param $right float _optional_ The right margin.
	 *
	 * The default for the right margin is to set it to the same as the left margin.
	 */
	public function setMargins(float $left, float $top, ?float $right = null): void {
		$this->m_lMargin = $left;
		$this->m_tMargin = $top;
		$this->m_rMargin = (isset($right) ? $right : $left);
	}

	/**
	 * Set the left margin in user units.
	 *
	 * @param $margin float The margin.
	 */
	public function setLeftMargin(float $margin): void {
		/* if cursor currently rests on margin, move it also */
		if($this->m_pageNumber > 0 && $this->m_x == $this->m_lMargin) {
			$this->m_x = $margin;
		}

		$this->m_lMargin = $margin;

		/* make sure cursor is inside new margin */
		if($this->m_pageNumber > 0 && $this->m_x < $margin) {
			$this->m_x = $margin;
		}
	}

	/**
	 * Set the top margin in user units.
	 *
	 * @param $margin float The margin.
	 */
	public function setTopMargin(float $margin): void {
		$this->m_tMargin = $margin;
	}

	/**
	 * Set the right margin in user units.
	 *
	 * @param $margin float The margin.
	 */
	public function setRightMargin(float $margin): void {
		$this->m_rMargin = $margin;
	}

	/**
	 * Get the left margin in user units.
	 *
	 * @return float The left margin.
	 */
	public function leftMargin(): float {
		return $this->m_lMargin;
	}

	/**
	 * Get the right margin in user units.
	 *
	 * @return float The right margin.
	 */
	public function rightMargin(): float {
		return $this->m_rMargin;
	}

	/**
	 * Get the top margin in user units.
	 *
	 * @return float The top margin.
	 */
	public function topMargin(): float {
		return $this->m_tMargin;
	}

	/**
	 * Get the bottom margin in user units.
	 *
	 * @return float The bottom margin.
	 */
	public function bottomMargin(): float {
		return $this->m_tMargin;
	}

	/**
	 * Set whether the document will automatically insert page breaks.
	 *
	 * @param $auto bool Whether to turn on or off the auto page break facility.
	 * @param $margin float _optional_ The margin above the bottom of the page at which to trigger a page break.
	 *
	 * The margin defaults to 0, indicating that the automatic page break
	 * will be triggered on the bottom of the page.
	 */
	public function setAutoPageBreak(bool $auto, float $margin = 0.0): void {
		if(0 > $margin) {
			AppLog::warning("negative threshold margin trimmed to 0", __FILE__, __LINE__, __FUNCTION__);
			$margin = 0;
		}

		/* Set auto page break mode and triggering margin */
		$this->m_autoPageBreak    = $auto;
		$this->m_bMargin          = $margin;
		$this->m_pageBreakTrigger = $this->m_currentPageHeight - $margin;
	}

	/**
	 * Set the display mode for the document.
	 *
	 * @param $zoom int The display zoom level for the document.
	 * @param $layoutMode int _optional_ The way the document will be displayed.
	 *
	 * `$zoom` must be a zoom factor constant or an integer greater than 0
	 * indicating the zoom percentage. It defines the relative size of the
	 * document when displayed.
	 *
	 * The `$layoutMode` must be one of the layout constants. The default
	 * is `ContinuousLayout`, indicating that the document should be
	 * displayed as one long column of pages.
	 *
	 * These settings are what the document suggests to the viewing
	 * application about how it should be displayed; whether or not the
	 * viewer respects this is up to the application.
	 *
	 * @return bool `true` if the display mode was set, `false` otherwise
	 */
	public function setDisplayMode(int $zoom, int $layoutMode = PdfWriter::ContinuousLayout): bool {
		/* relies on lazy evaluation and non-optimisation of boolean
		 * expressions - don't combine otherwise can't guarantee both
		 * calls will be made in all circumstances.
		 */
		$ret = $this->setDisplayZoom($zoom);
		$ret = $this->setDisplayLayout($layoutMode) && $ret;
		return $ret;
	}

	/**
	 * Set the display mode for the document.
	 *
	 * @param $zoom `int` The display zoom level for the document.
	 *
	 * `$zoom` must be a zoom factor constant or an integer greater than 0
	 * indicating the zoom percentage. It defines the relative size of the
	 * document when displayed.
	 *
	 * This setting is suggested to the viewing application when the document
	 * is displayed; whether or not the viewer respects this is up to the
	 * application.
	 *
	 * @return bool `true` if the zoom mode was set, `false` otherwise
	 */
	public function setDisplayZoom($zoom): bool {
		if($zoom !== self::ViewerDefaultZoom && $zoom !== self::FullWidthZoom && $zoom !== self::FullPageZoom && $zoom !== self::RealZoom && $zoom < 1) {
			AppLog::error('invalid zoom mode', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_zoomMode = $zoom;
		return true;
	}

	/**
	 * Set the display layout mode for the document.
	 *
	 * @param $layoutMode `int` The way the document will be displayed.
	 *
	 * The `$layoutMode` must be one of the layout constants. The default
	 * is `ContinuousLayout`, indicating that the document should be
	 * displayed as one long column of pages.
	 *
	 * This setting is suggested to the viewing application when the document
	 * is displayed; whether or not the viewer respects this is up to the
	 * application.
	 *
	 * @return bool `true` if the layout mode was set, `false` otherwise
	 */
	public function setDisplayLayout($layoutMode): bool {
		/*Set display mode in viewer */
		if($layoutMode !== self::SingleLayout && $layoutMode !== self::ContinuousLayout && $layoutMode !== self::TwoColumnLayout) {
			AppLog::error('invalid layout mode', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_layoutMode = $layoutMode;
		return true;
	}

	/**
	 * Check whether compression is available for the PDF document.
	 *
	 * @return bool `true` if compression is available, `false` otherwise.
	 */
	public static function canCompress(): bool {
		return is_callable("gzcompress", false);
	}

	/**
	 * Check whether the document will be compressed.
	 *
	 * @return bool `true` if the document will use compression, `false` otherwise.
	 */
	public function willCompress(): bool {
		return $this->m_compress && self::canCompress();
	}

	/**
	 * Set whether or not the document will use compression.
	 *
	 * @param $compress `boolean` Whether or not the document should use
	 * compression.
	 *
	 * @return bool `true` if the compression flag was updated, `false` otherwise, including if compression is not
	 * available.
	 */
	public function setCompression(bool $compress): bool {
		if(!self::canCompress()) {
			AppLog::warning("compression is not available", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_compress = $compress;
		return true;
	}

	/**
	 * Set the document title.
	 *
	 * @param $title `string` is the title for the document.
	 *
	 * @note Because pages are created in real time, the title must
	 * be set before any pages are output in order for it to appear
	 * correctly in any headers or footers that are set. It may be
	 * the case that this will be rigorously enforced in this method
	 * in future.
	 *
	 * @return bool `true` if the title was set, `false` otherwise.
	 */
	public function setTitle(string $title): bool {
		/*if($this->m_pageNumber) */
		/*	return false; */

		$this->m_title = $title;
		return true;
	}

	/**
	 * Sets the document subject.
	 *
	 * @param $subject string The subject for the document.
	 */
	public function setSubject(string $subject): void {
		$this->m_subject = $subject;
	}

	/**
	 * Sets the document author.
	 *
	 * @param $author string The author for the document.
	 */
	public function setAuthor(string $author): void {
		$this->m_author = $author;
	}

	/**
	 * Sets the document keywords.
	 *
	 * Multiple keywords must be separated by a comma.
	 *
	 * @param $keywords `string` The keywords for the document.
	 */
	public function setKeywords(string $keywords): void {
		$this->m_keywords = $keywords;
	}

	/**
	 * Add a document keyword.
	 *
	 * @param $keyword `string` The keyword to add to the document.
	 */
	public function addKeyword(string $keyword): void {
		$this->m_keywords .= " $keyword";
	}

	/**
	 * Sets the document creator.
	 *
	 * @param $creator string The creator for the document.
	 */
	public function setCreator($creator): void {
		$this->m_creator = $creator;
	}

	/**
	 * Define an the placeholder to use for the number of pages in the
	 * document.
	 *
	 * @param $placeholder `string` is the placeholder to use.
	 *
	 * This method allows applications to set a text placeholder that will be
	 * replaced with the number of pages anywhere it occurs in text in the
	 * document. The default is {nb} but applications can change it if they
	 * would like the literal text {nb} to occur in text in the document.
	 *
	 * The placeholder must be a string. If it is an empty string, automatic
	 * insertion of the number of pages will be disabled.
	 *
	 * The replacement is not carried out until the document is closed, for
	 * obvious reasons. This means that changing the placeholder after text has
	 * been added to the document will replace the new placeholder in the
	 * existing text with the number of pages and will not replace the former
	 * placeholder in the existing text with the number of pages. That is, if
	 * the placeholder is changed from \c {nb} to \c {totalpages}, occurences of
	 * \c {nb} in text in the document that was added before the placeholder was
	 * changed will not be replaced with the number of pages, and instances of
	 * \c {totalpages} in text that was added to the document before the change
	 * of placeholder will nonetheless be replaced with the number of pages. Put
	 * simply, the only placeholder that matters is that which is in effect at
	 * the time the document is closed.
	 */
	public function setPageCountPlaceholder(string $placeholder): void {
		$this->m_pageCountPlaceholder = trim($placeholder);
	}

	/**
	 * Open the document for manipulation.
	 *
	 * @return bool `true` if the document was opened, `false` otherwise.
	 */
	protected function open(): bool {
		/* set document as ready for manipulation */
		$this->m_state = 1;
		return true;
	}

	/**
	 * Close the document to further manipulation.
	 *
	 * Once a document is closed, it cannot later be opened. Closing the document
	 * flushes the PDF data stream and writes the end of document signature.
	 *
	 * @return bool `true` if the document was closed, `false` otherwise.
	 */
	protected function close(): bool {
		/* set document as finished manipulation */
		if(3 == $this->m_state) {
			return true;
		}

		if(0 == $this->m_pageNumber) {    /* make sure it has at least 1 page */
			$this->addPage();
		}

		$this->_endPage();
		$this->_endDoc();
		$this->cleanTempFiles();

		return true;
	}

	/**
	 * Add a new page to the document.
	 *
	 * @param $orientation int|null _optional_ The page orientation.
	 *
	 * The `$orientation` must be one of the orientation constants, or `null` indicating that the orientation should be
	 * the same as the last page, or the default orientation if it's the first page.
	 *
	 * @return bool `true` if the page was added, `false` otherwise.
	 */
	public function addPage(?int $orientation = null): bool {
		/*Start a new page */
		if(0 == $this->m_state && !$this->open()) {
			AppLog::error("the page could not be added because the document could not be opened", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		/* cache rendering parameters while footer is output */
		$family = $this->m_currentFontFamily;
		$style  = $this->m_currentFontStyle . ($this->m_underlineFlag ? "U" : "");
		$size   = $this->m_currentFontSizePt;
		$lw     = $this->m_lineWidth;
		$dc     = $this->m_drawColour;
		$fc     = $this->m_fillColour;
		$tc     = $this->m_textColour;
		$cf     = $this->m_colourFlag;

		/* finish off the old page */
		if($this->m_pageNumber > 0) {
			/* render the page footer */
			$this->_endPage();
		}

		/* start new page */
		/* _beginpage() will validate the orientation and take appropriate action but will NEVER bail */
		$this->_beginPage($orientation);
		$this->outputToBuffer("2 J");        /* square line cap */
		$this->m_lineWidth  = $lw;
		$this->m_drawColour = $dc;
		$this->m_fillColour = $fc;
		$this->m_textColour = $tc;
		$this->m_colourFlag = $cf;

		$this->outputToBuffer(sprintf("%s w", self::postscriptNumber(self::mmToPt($lw))));

		/* restore cached state for new page */
		if(!empty($family)) {
			$this->setFont($family, $style, $size);
		}

		if("0 G" != $dc) {
			$this->outputToBuffer($dc);
		}

		if("0 g" != $fc) {
			$this->outputToBuffer($fc);
		}

		/* finish restoring cached state for new page */
		if($this->m_lineWidth != $lw) {
			$this->m_lineWidth = $lw;
			$this->outputToBuffer(sprintf("%s w", self::postscriptNumber(self::mmToPt($lw))));
		}

		if($this->m_drawColour != $dc) {
			$this->m_drawColour = $dc;
			$this->outputToBuffer($dc);
		}

		if($this->m_fillColour != $fc) {
			$this->m_fillColour = $fc;
			$this->outputToBuffer($fc);
		}

		$this->m_textColour = $tc;
		$this->m_colourFlag = $cf;
		return true;
	}

	/**
	 * Sets the document to double- or single-sided.
	 *
	 * The default is true, meaning a call to this method without
	 * parameters will make the document duplex.
	 *
	 * @note This method sets the entire document to double- or single-sided, and can only be called before any pages
	 * have be created.
	 *
	 * @param $duplex bool _optional_ Whether the document is double-sided.
	 *
	 * @return bool `true` if the duplex setting was accepted, `false`
	 * otherwise.
	 */
	public function setDuplex(bool $duplex = true): bool {
		if($this->m_pageNumber > 0) {
			AppLog::warning("the duplex setting cannot be altered after pages have been added", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_duplex = $duplex;
		return true;
	}

	/**
	 * Get the current page number.
	 *
	 * @return int the page number.
	 */
	public function pageNumber(): int {
		return $this->m_pageNumber;
	}

	/**
	 * Generates a postscript colour specification from RGB components.
	 *
	 * @param $r int|Colour the red component, or the grayscale level or a Colour object.
	 * @param $g int the green component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 * @param $b int the blue component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 *
	 * @note This is a private helper function and should <strong>only</strong> be used by instances of PdfWriter
	 * **not** subclasses.
	 *
	 * @return string The colour specification for the colour.
	 */
	private static function postscriptColour($r, int $g = -1, int $b = -1): string {
		if($r instanceof Colour) {
			$g = $r->green();
			$b = $r->blue();
			$r = $r->red();
		}

		$r = min(max(0, $r), 255);
		$g = min(max(-1, $g), 255);
		$b = min(max(-1, $b), 255);

		if(($r == $g && $r == $b) || -1 == $g || -1 == $b) {
			return sprintf('%s G', self::postscriptNumber($r / 255));
		}
		else {
			return sprintf('%s %s %s RG', self::postscriptNumber($r / 255, 3), self::postscriptNumber($g / 255, 3), self::postscriptNumber($b / 255, 3));
		}
	}

	/**
	 * Gets a Colour object from any of the ways of setting a colour.
	 *
	 * @param $r int or Colour is the red component, or the greyscale level or a Colour object.
	 * @param $g int is the green component, or -1 if the red component is actually a greyscale level. It is ignored if
	 * the red component is actually a Colour object.
	 * @param $b int is the blue component, or -1 if the red component is actually a greyscale level. It is ignored if
	 * the red component is actually a Colour object.
	 *
	 * @return Colour The colour.
	 */
	private static function colourObject($r, $g = -1, $b = -1): Colour {
		if($r instanceof Colour) {
			return $r;
		}

		/* grey level? */
		if($g == -1 || $b == -1) {
			$b = $g = $r;
		}

		$arr = [&$r, &$g, &$b];

		foreach($arr as &$param) {
			if(!is_numeric($param)) {
				$param = 0;
			}
			else {
				$param = min(max(0, $param), 255);
			}
		}

		return new Colour($r, $g, $b);
	}

	/**
	 * Gets the drawing colour.
	 *
	 * @note there is a remote possibility that the draw colour returned is not the actual draw colour used in the
	 * document.
	 *
	 * @return Colour|null The drawing colour, or `null` on error.
	 */
	public function drawColour(): ?Colour {
		return $this->m_drawColourObj;
	}

	/**
	 * Set the draw colour.
	 *
	 * @param $r int|Colour is the red component, greyscale level or full colour specification.
	 * @param $g int The green component, or -1 if the red component is actually a greyscale level.
	 * @param $b int the blue component, or -1 if the red component is actually a greyscale level.
	 *
	 * `$g` and `$b` are ignored if the red component is actually a Colour object.
	 *
	 * The draw colour is used for all stroking operations in the PDF document. All components must be in the range
	 * 0-255 inclusive, except in the circumstances outlined above.
	 */
	public function setDrawColour($r, int $g = -1, int $b = -1): void {
		/*Set color for all stroking operations */
		$this->m_drawColour    = self::postscriptColour($r, $g, $b);
		$this->m_drawColourObj = self::colourObject($r, $g, $b);

		if($this->m_pageNumber > 0) {
			$this->outputToBuffer($this->m_drawColour);
		}
	}

	/**
	 * Gets the fill colour.
	 *
	 * @note there is a remote possibility that the colour returned is not the actual fill colour used in the document.
	 *
	 * @return Colour The fill colour or `null` on error.
	 */
	public function fillColour(): ?Colour {
		return $this->m_fillColourObj;
	}

	/**
	 * Set the fill colour.
	 *
	 * @param $r Colour|int the red component, or the grayscale level or a Colour object.
	 * @param $g int the green component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 * @param $b int the blue component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 *
	 * The fill colour is used for all filling operations in the PDF document. All components must be in the range 0-255
	 * inclusive, except in the circumstances outlined above.
	 *
	 * @note At present, the alpha component for Colour objects is ignored.
	 */
	public function setFillColour($r, int $g = -1, int $b = -1): void {
		/* set color for all filling operations */
		/* need strtolower() because "rg" is command for fill colours whereas
		 * self::postscriptColour() always produces "RG" command */
		$this->m_fillColour    = strtolower(self::postscriptColour($r, $g, $b));
		$this->m_fillColourObj = self::colourObject($r, $g, $b);
		$this->m_colourFlag    = ($this->m_fillColour != $this->m_textColour);

		if($this->m_pageNumber > 0) {
			$this->outputToBuffer($this->m_fillColour);
		}
	}

	/**
	 * Gets the text colour.
	 *
	 * @note there is a remote possibility that the colour returned is not the actual text colour used in the document.
	 *
	 * @return Colour The text colour, or `null` on error.
	 */
	public function textColour(): ?Colour {
		return $this->m_textColourObj;
	}

	/**
	 * Set the text colour.
	 *
	 * The text colour is used for all text rendering operations in the PDF document. All components must be in the
	 * range 0-255 inclusive, except in the circumstances outlined below.
	 *
	 * @param $r int|Colour the red component, or the grayscale level or a Colour object.
	 * @param $g int the green component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 * @param $b int the blue component, or -1 if the red component is actually a grayscale level. It is ignored if the
	 * red component is actually a Colour object.
	 *
	 * @note At present, the alpha component for Colour objects is ignored.
	 */
	public function setTextColour($r, int $g = -1, int $b = -1): void {
		/*Set color for text */
		/* need strtolower() because "rg" is command for fill colours whereas
		 * self::postscriptColour() always produces "RG" command */
		$this->m_textColour    = strtolower(self::postscriptColour($r, $g, $b));
		$this->m_textColourObj = self::colourObject($r, $g, $b);
		$this->m_colourFlag    = ($this->m_fillColour != $this->m_textColour);
	}

	/**
	 * Get the render width of a string.
	 *
	 * @param $s string The string to measure.
	 *
	 * The string is measured using the current font at the current
	 * size.
	 *
	 * @return int|null The width of the string in user units, or `null` on error.
	 */
	public function stringWidth(string $s): ?int {
		/*Get width of a string in the current font */
		$codepoints = self::utf8ToCodepoints($s);

		if(empty($this->m_currentFontInfo) || empty($this->m_currentFontInfo["cw"])) {
			AppLog::warning("no current font", __FILE__, __LINE__, __FUNCTION__);
			return 0;
		}

		$cw = &$this->m_currentFontInfo["cw"];
		$w  = 0;

		foreach($codepoints as $cp) {
			$w += $cw[$cp];
		}

		return $w * $this->m_currentFontSize / 1000;
	}

	/**
	 * Set the line width.
	 *
	 * @param $width float the line width.
	 *
	 * The line width is the thickness of lines drawn using the geometric primitive drawing methods.
	 */
	public function setLineWidth(float $width): void {
		/*Set line width */
		$this->m_lineWidth = $width;
		$this->outputToBuffer(sprintf("%s w", self::postscriptNumber(self::mmToPt($width))));
	}

	/**
	 * Draw a line in the current page.
	 *
	 * The line will be drawn with the current line colour, style and thickness.
	 *
	 * @param $x1 float the x-coordinate of one end of the line. It is measured in user units from the left edge of the
	 * page.
	 * @param $y1 float the y-coordinate of one end of the line. It is measured in user units from the top edge of the
	 * page.
	 * @param $x2 float the x-coordinate of the other end of the line. It is measured in user units from the left edge
	 * of the page.
	 * @param $y2 float the y-coordinate of the other end of the line. It is measured in user units from the top edge
	 * of the page.
	 */
	public function line(float $x1, float $y1, float $x2, float $y2): void {
		$this->outputToBuffer(sprintf(
			'%s %s m %s %s l S',
			self::postscriptNumber(self::mmToPt($x1)),
			self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y1)),
			self::postscriptNumber(self::mmToPt($x2)), 
			self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y2))
		));
	}

	/**
	 * Draw a rectangle in the current page.
	 *
	 * The rectangle ine will be drawn with the current line colour, style and thickness. If the style indicates it is
	 * to be filled, it will be filled with the current fill colour.
	 *
	 * @param $x float the x-coordinate of the top-left corner of the rectangle. It is measured in user units from the
	 * left edge of the page.
	 * @param $y float the y-coordinate of the top left corner of the rectangle. It is measured in user units from
	 * the top edge of the page.
	 * @param $w float the width of the rectangle. It is measured in user units.
	 * @param $h float the height of the rectangle. It is measured in user units.
	 * @param $style string the style of the rectangle. It must be a string. `F` means filled, `D` means with a dashed
	 * outline, anything else means a solid outline with no fill. `F` and `D` can be combined.
	 */
	public function rectangle(float $x, float $y, float $w, float $h, string $style = ""): void {
		/*Draw a rectangle */
		if($style == 'F') {
			$op = 'f';
		}
		else if($style == 'FD' || $style == 'DF') {
			$op = 'B';
		}
		else {
			$op = 'S';
		}

		$this->outputToBuffer(sprintf('%s %s %s %s re %s', self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)), self::postscriptNumber(self::mmToPt($w)), self::postscriptNumber(self::mmToPt(-$h)), $op));
	}

	/**
	 * Draw a rectangle in the current page with rounded corners.
	 *
	 * The rectangle will be drawn with the current line colour, style and thickness. If the style indicates it is to be
	 * filled, it will be filled with the current fill colour.
	 *
	 * @param $x float the x-coordinate of the top-left corner of the rectangle. It is measured in user units from the
	 * left edge of the page.
	 * @param $y float the y-coordinate of the top left corner of the rectangle. It is measured in user units from
	 * the top edge of the page.
	 * @param $w float the width of the rectangle. It is measured in user units.
	 * @param $h float the height of the rectangle. It is measured in user units.
	 * @param $r float the radius of the rounded corners. It is measured in user units.
	 * @param $style string the style of the rectangle. It must be a string. `F` means filled, `D` means with a dashed
	 * outline, anything else means a solid outline with no fill. `F` and `D` can be combined.
	 */
	public function roundedRectangle(float $x, float $y, float $w, float $h, float $r, string $style = ''): void {
		if("F" == $style) {
			$op = "f";
		}
		else if("FD" == $style || "DF" == $style) {
			$op = "B";
		}
		else {
			$op = "S";
		}

		/* 4 / 3 * (sqrt(2) - 1) */
		$myArc = 0.552284749831;

		$this->outputToBuffer(sprintf("%s %s m", self::postscriptNumber(self::mmToPt($x + $r)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y))));

		$xc = $x + $w - $r;
		$yc = $y + $r;

		$this->outputToBuffer(sprintf("%s %s l", self::postscriptNumber(self::mmToPt($xc)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y))));

		$this->_arc($xc + $r * $myArc, $yc - $r, $xc + $r, $yc - $r * $myArc, $xc + $r, $yc);
		$xc = $x + $w - $r;
		$yc = $y + $h - $r;

		$this->outputToBuffer(sprintf("%s %s l", self::postscriptNumber(self::mmToPt($x + $w)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $yc))));

		$this->_arc($xc + $r, $yc + $r * $myArc, $xc + $r * $myArc, $yc + $r, $xc, $yc + $r);
		$xc = $x + $r;
		$yc = $y + $h - $r;

		$this->outputToBuffer(sprintf("%s %s l", self::postscriptNumber(self::mmToPt($xc)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h)))));

		$this->_arc($xc - $r * $myArc, $yc + $r, $xc - $r, $yc + $r * $myArc, $xc - $r, $yc);
		$xc = $x + $r;
		$yc = $y + $r;

		$this->outputToBuffer(sprintf("%s %s l", self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $yc))));

		$this->_arc($xc - $r, $yc - $r * $myArc, $xc - $r * $myArc, $yc - $r, $xc, $yc - $r);
		$this->outputToBuffer($op);
	}

	/**
	 * Draw an arc.
	 *
	 * This is a protected function used as a helper by roundedRectangle(). It is assumed that the parameters have been
	 * validated.
	 *
	 * @note This method was sourced from the scripts section of the FPDF website (http://www.fpdf.org/). It was
	 * provided by Maxime Delorme on 19/11/2002.
	 *
	 * @param float $x1 First point on the arc, x-coordinate.
	 * @param float $y1 First point on the arc, y-coordinate.
	 * @param float $x2 Second point on the arc, x-coordinate.
	 * @param float $y2 Second point on the arc, y-coordinate.
	 * @param float $x3 Third point on the arc, x-coordinate.
	 * @param float $y3 Third point on the arc, y-coordinate.
	 */
	protected function _arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void {
		$this->outputToBuffer(sprintf('%s %s %s %s %s %s c ', self::postscriptNumber(self::mmToPt($x1)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y1)), self::postscriptNumber(self::mmToPt($x2)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y2)), self::postscriptNumber(self::mmToPt($x3)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y3))));
	}

	/**
	 * \c Add a font to the PDF file.
	 *
	 * @param string $family is the family name of the font to add.
	 * @param int $style is the style of the font to add: B, I, BI or nothing. Default is `PlainStyle`.
	 * @param string|null $file is the name of the font definition file to use for this family and style. If it is
	 * missing or an empty string, the definition file to use is automatically generated using the family name and style
	 * (e.g. tahomab.php for tahoma bold).
	 *
	 * \todo Implement * option for $style to load all available styles. Return the number of fonts added (i.e. 0 if all
	 * styles failed). Client can use hasFont to interrogate which are available.
	 *
	 * @return bool `true` if the font was added or was already available in the document, `false` otherwise. If this
	 * method returns `false` the font is not available for use in the document.
	 */
	public function addFont(string $family, int $style = self::PlainStyle, ?string $file = null): bool {
		/*Add a TrueType or Type1 font */
		if(!is_string($family) || '' == trim($family)) {
			AppLog::error('invalid font family name', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!is_null($file) && !is_string($file)) {
			AppLog::error('invalid font file', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!is_int($style)) {
			$style = self::PlainStyle;
		}

		if(self::isCoreFont($family, $style)) {
			AppLog::warning("passed family name for core font '$family'.", __FILE__, __LINE__, __FUNCTION__);
			return true;
		}

		$myStyle = '';

		if($style & self::BoldStyle) {
			$myStyle .= 'B';
		}

		if($style & self::ItalicStyle) {
			$myStyle .= 'I';
		}

		$family  = strtolower($family);
		$fontkey = $family . $myStyle;

		if(array_key_exists($fontkey, $this->m_fonts)) {
			AppLog::warning('font "' . $family . '" already added to document.', __FILE__, __LINE__, __FUNCTION__);
			return true;
		}

		if(empty($file)) {
			$file = str_replace(' ', '', $family) . strtolower($myStyle) . '.php';
		}

		$path = self::fontPath() . $file;

		if(!file_exists($path) || !is_readable($path)) {
			AppLog::error('the font definition file \'' . $path . '\' does not exist or is not readable', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		/** @noinspection PhpIncludeInspection */
		@include($path);

		if(!isset($name)) {
			AppLog::error('the file \'' . $file . '\' is not a valid font definition file.', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		// all these unrecognised variables are set in the included file $path
		$i = count($this->m_fonts) + 1;
		/** @noinspection PhpUndefinedVariableInspection */
		$this->m_fonts[$fontkey] = ['i' => $i, 'type' => $type, 'name' => $name, 'desc' => $desc, 'up' => $up, 'ut' => $ut, 'cw' => $cw, 'file' => $file, 'ctg' => $ctg];

		if($file) {
			if('TrueTypeUnicode' == $type) {
				/** @noinspection PhpUndefinedVariableInspection */
				$this->m_fontFiles[$file] = ['length1' => $originalsize];
			}
			else {
				/** @noinspection PhpUndefinedVariableInspection */
				$this->m_fontFiles[$file] = ['length1' => $size1, 'length2' => $size2];
			}
		}

		return true;
	}

	/**
	 * Sets the current font being used to render text.
	 *
	 * @param $family string The font family to use. This must either be one of the PDF core fonts or have been
	 * previously added (with the correct style) using addFont()
	 * @param $style int B, I, BI or an empty string indicating the font style to use. If missing or an empty string,
	 * the default behaviour of requesting the regular style is used.
	 * @param $size float the point size of the font to use. Default is `null` meaning keep the current point size;
	 *
	 * The font size will be trimmed to be at least 3pt.
	 *
	 * @see addFont()
	 *
	 * @return bool `true` if the font was changed as requested, `false` otherwise. If `false` is returned, the font
	 * remains the one that was in use before the call to this method.
	 */
	public function setFont(string $family, int $style = self::PlainStyle, ?float $size = null): bool {
		global $fpdf_charwidths;

		if(!is_string($family) || "" == trim($family)) {
			AppLog::error("invalid font family name", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!isset($size)) {
			$size = $this->m_currentFontSizePt;
		}

		if(!is_int($style)) {
			$style = self::PlainStyle;
		}

		if(!is_numeric($size)) {
			AppLog::error('invalid font size', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$myStyle = '';

		if($style & self::BoldStyle) {
			$myStyle .= 'B';
		}

		if($style & self::ItalicStyle) {
			$myStyle .= 'I';
		}

		if($style & self::UnderlinedStyle) {
			$this->m_underlineFlag = true;
		}
		else {
			$this->m_underlineFlag = false;
		}

		$family = strtolower($family);

		/* 3 is minimum point size */
		$size = max(3, $size);

		/*Test if font is already selected */
		if($this->m_currentFontFamily == $family && $this->m_currentFontStyle == $myStyle && $this->m_currentFontSizePt == $size) {
			return true;
		}

		/*Test if used for the first time */
		$fontkey = $family . $myStyle;

		if(!array_key_exists($fontkey, $this->m_fonts)) {
			/*Check if one of the standard fonts */
			if(array_key_exists($fontkey, self::$s_coreFonts)) {
				if(!array_key_exists($fontkey, $fpdf_charwidths)) {
					/*Load metric file */
					$file = $family;

					if("times" == $family || "helvetica" == $family) {
						$file .= strtolower($myStyle);
					}

					$metricsFilePath = self::fontPath() . "$file.php";

					if(!is_file($metricsFilePath) || !is_readable($metricsFilePath)) {
						AppLog::error("the font metrics file for the core font $family $myStyle ($file) does not exist.", __FILE__, __LINE__, __FUNCTION__);
						return false;
					}

					/** @noinspection PhpIncludeInspection */
					include($metricsFilePath);

					if(!array_key_exists($fontkey, $fpdf_charwidths)) {
						AppLog::error("the font metrics file for the core font $family $myStyle ($file) is not valid or is not readable.", __FILE__, __LINE__, __FUNCTION__);
						return false;
					}
				}

				$i                       = count($this->m_fonts) + 1;
				$this->m_fonts[$fontkey] = ["i" => $i, "type" => "core", "name" => self::$s_coreFonts[$fontkey], "up" => -100, "ut" => 50, "cw" => $fpdf_charwidths[$fontkey]];
			}
			else {
				AppLog::error("the font $family $myStyle is not available in the document. Did you first call addFont()?", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		/*Select it */
		$this->m_currentFontFamily = $family;
		$this->m_currentFontStyle  = $myStyle;
		$this->m_currentFontSizePt = $size;
		$this->m_currentFontSize   = self::ptToMm($size);
		$this->m_currentFontInfo   =& $this->m_fonts[$fontkey];

		if(0 < $this->m_pageNumber) {
			$this->outputToBuffer(sprintf("BT /F%d %s Tf ET", $this->m_currentFontInfo["i"], self::postscriptNumber($this->m_currentFontSizePt)));
		}

		return true;
	}

	/**
	 * Set the size of the current text rendering font.
	 *
	 * @param $size float the size in points.
	 *
	 * @return bool `true` if the font size was set, `false` otherwise.
	 */
	public function setFontSize(float $size): bool {
		if($size < 1) {
			AppLog::error("the size provided was invalid", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if($this->m_currentFontSizePt != $size) {
			$this->m_currentFontSizePt = $size;
			$this->m_currentFontSize   = self::ptToMm($size);

			if(0 < $this->m_pageNumber) {
				$this->outputToBuffer(sprintf("BT /F%d %s Tf ET", $this->m_currentFontInfo["i"], self::postscriptNumber($this->m_currentFontSizePt)));
			}
		}

		return true;
	}

	/**
	 * Get the size of the current text rendering font.
	 *
	 * The size is returned in points.
	 *
	 * @return float|null The size, or _null_ on error.
	 */
	public function fontSize(): ?float {
		return $this->m_currentFontSizePt;
	}

	/**
	 * Get the size of the current text rendering font.
	 *
	 * The size is returned in mm.
	 *
	 * @return float The size, or _null_ on error.
	 */
	public function fontSizeMm(): ?float {
		return $this->m_currentFontSize;
	}

	/**
	 * Check whether a font is already in the document.
	 *
	 * @param $family string The font family to check.
	 * @param $style int The font style to check. It must be an integer style constant.
	 *
	 * Default style is PlainStyle.
	 *
	 * @return bool `true` if the font is in the document, `false` otherwise.
	 */
	public function hasFont(string $family, int $style = self::PlainStyle): bool {
		/* isCoreFont() and isEmbedded() take care of validating style and family */
		return self::isCoreFont($family, $style) || $this->isEmbeddedFont($family, $style);
	}

	/**
	 * Check whether a font is a PDF core font.
	 *
	 * @param $family string The font family to check.
	 * @param $style int The font style to check. It must be an integer style constant.
	 *
	 * Default style is PlainStyle.
	 *
	 * @return bool `true` if the font a core PDF font, `false` otherwise.
	 */
	public static function isCoreFont(string $family, int $style = self::PlainStyle): bool {
		$fontKey = $family;

		if($style & self::BoldStyle) {
			$fontKey .= "B";
		}

		if($style & self::ItalicStyle) {
			$fontKey .= "I";
		}

		return array_key_exists($fontKey, self::$s_coreFonts);
	}

	/**
	 * Check whether a font is a document-embedded font.
	 *
	 * @param $family string The font family to check.
	 * @param $style int The font style to check. It must be an integer style constant.
	 *
	 * Default style is PlainStyle.
	 *
	 * @return bool `true` if the font a document-embedded font, `false` otherwise.
	 */
	public function isEmbeddedFont(string $family, int $style = self::PlainStyle): bool {
		$fontKey = $family;

		if($style & self::BoldStyle) {
			$fontKey .= "B";
		}

		if($style & self::ItalicStyle) {
			$fontKey .= "I";
		}

		return array_key_exists($fontKey, $this->m_fonts);
	}

	/**
	 * Gets the default page format for this document.
	 *
	 * @note Do not confuse this with the default page format the class uses when creating documents that have either an
	 * invalid or no page format specified.
	 *
	 * @return array the dimensions of the default page format for this document in user units, or _null_ on error.
	 */
	public function documentPageDimensions(): array {
		return [$this->m_formatWidth, $this->m_formatHeight];
	}

	/**
	 * Gets the current page format for this document.
	 *
	 * @return array the dimensions of the current page format for this document in user units, or `null` on error.
	 */
	public function currentPageDimensions(): array {
		return [$this->m_currentPageWidth, $this->m_currentPageHeight];
	}

	/**
	 * Gets the current page width in user units.
	 *
	 * @return float|null The page width, or _null_ on error.
	 */
	public function currentPageWidth(): ?float {
		return $this->m_currentPageWidth;
	}

	/**
	 * Gets the current page height in user units.
	 *
	 * @return float|null The page height, or _null_ on error.
	 */
	public function currentPageHeight(): ?float {
		return $this->m_currentPageHeight;
	}

	/**
	 * Gets the default page orientation for this document.
	 *
	 * @note Do not confuse this with the default orientation the class uses when creating documents that have either an
	 * invalid or no orientation specified.
	 *
	 * @return int|null One of the orientation constants, or _null_ on error.
	 */
	public function documentOrientation(): int {
		return $this->m_documentOrientation;
	}

	/**
	 * Gets the page orientation for the current page.
	 *
	 * @return int|null One of the page orientation constants, or _null_ on error.
	 */
	public function currentPageOrientation(): ?int {
		return $this->m_currentOrientation;
	}

	/**
	 * Output some text to the current page.
	 *
	 * @param $x float the x-coordinate of the left edge of the text. It is measured in user units from the left edge
	 * of the page.
	 * @param $y float the y-coordinate of the (baseline?) of the first line of the text. It is measured in user units
	 * from the top edge of the page.
	 * @param $txt string the text to render.
	 *
	 * @note I have yet to assess what assumptions are made about the encoding of the text.
	 */
	public function text(float $x, float $y, string $txt): void {

		$s = sprintf('BT %s %s Td (%s) Tj ET', self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)), self::escapeText($txt));

		if($this->m_underlineFlag && '' != $txt) {
			$s .= ' ' . $this->_doUnderline($x, $y, $txt);
		}

		if($this->m_colourFlag) {
			/* TODO should there be a space before this q command? there isn't
			 * in cell() either, and that works and gets used lots, so perhaps
			 * not */
			$s .= 'q ' . $this->m_textColour . ' ' . $s . ' Q';
		}

		$this->outputToBuffer($s);
	}

	/**
	 * Check whether the document will do automatic page breaks.
	 *
	 * @return bool `true` if automatic page breaks are switched on, `false`
	 * otherwise.
	 */
	public function autoPageBreak(): bool {
		return $this->m_autoPageBreak;
	}

	/**
	 * Output a cell to the current page.
	 *
	 * @param $w float the width of the cell. It must be numeric. It is measured
	 * in user units.
	 * @param $h float the height of the cell. It must be numeric. It is measured
	 * in user units. The default is 0.0.
	 * @param $txt string the text to write in the cell. It must be a string. Default
	 * is an empty string, indicating that the cell is to contain no text.
	 * @param $border int Bitmask of which borders to draw.
	 * @param $ln int if != 0 move cursor to next line; if 1 move to next line and to left margin
	 * @param $align int Text alignment flags.
	 * @param $fill bool Whether or not to fill the cell with colour.
	 *
	 * The cell is output at the current cursor position on the page, and the
	 * cursor position is moved to the next location after the cell.
	 *
	 * @todo Parameter validation.
	 */
	public function cell(float $w, float $h = 0.0, string $txt = "", int $border = 0, int $ln = 0, int $align = self::AlignLeft, bool $fill = false): void {
		if($this->m_y + $h > $this->m_pageBreakTrigger && $this->autoPageBreak()) {
			/*Automatic page break */
			$x  = $this->m_x;
			$ws = $this->m_wordSpacing;

			if($ws > 0) {
				$this->m_wordSpacing = 0;
				$this->outputToBuffer("0 Tw");
			}

			$this->addPage($this->m_currentOrientation);
			$this->m_x = $x;

			if($ws > 0) {
				$this->m_wordSpacing = $ws;
				$this->outputToBuffer(sprintf("%s Tw", self::postscriptNumber(self::mmToPt($ws), 3)));
			}
		}

		if(0 == $w) {
			$w = $this->m_currentPageWidth - $this->m_rMargin - $this->m_x;
		}

		$s = '';

		if($fill || $border == 1) {
			if($fill) {
				$op = (($border == 1) ? "B" : "f");
			}
			else {
				$op = 'S';
			}

			$s = sprintf("%s %s %s %s re %s ", self::postscriptNumber(self::mmToPt($this->m_x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $this->m_y)), self::postscriptNumber(self::mmToPt($w)), self::postscriptNumber(self::mmToPt(-$h)), $op);
		}

		$x = $this->m_x;
		$y = $this->m_y;

		if($border & self::LeftBorder) {
			$s .= sprintf("%s %s m %s %s l S ", self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)), self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h))));
		}

		if($border & self::TopBorder) {
			$s .= sprintf("%s %s m %s %s l S ", self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)), self::postscriptNumber(self::mmToPt($x + $w)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)));
		}

		if($border & self::RightBorder) {
			$s .= sprintf("%s %s m %s %s l S ", self::postscriptNumber(self::mmToPt($x + $w)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - $y)), self::postscriptNumber(self::mmToPt($x + $w)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h))));
		}

		if($border & self::BottomBorder) {
			$s .= sprintf("%s %s m %s %s l S ", self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h))), self::postscriptNumber(self::mmToPt($x + $w)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h))));
		}

		if(!empty($txt)) {
			switch($align) {
				case self::AlignRight:
					$dx = $w - $this->m_cMargin - $this->stringWidth($txt);
					break;

				case self::AlignCentre:
					$dx = ($w - $this->stringWidth($txt)) / 2;
					break;

				default:
					AppLog::warning("unrecognised alignment: defaulting to left", __FILE__, __LINE__, __FUNCTION__);
				case self::AlignLeft:
					$dx = $this->m_cMargin;
					break;
			}

			if($this->m_colourFlag) {
				$s .= "q {$this->m_textColour} ";
			}

			$txtString = self::escapeText($txt);

			$s .= sprintf("BT %s %s Td %s Tj ET", self::postscriptNumber(self::mmToPt($this->m_x + $dx)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($this->m_y + .5 * $h + .3 * $this->m_currentFontSize))), $txtString);

			if($this->m_underlineFlag) {
				$s .= " " . $this->_doUnderline($this->m_x + $dx, $this->m_y + .5 * $h + .3 * $this->m_currentFontSize, $txt);
			}

			if($this->m_colourFlag) {
				$s .= " Q";
			}
		}

		if(!empty($s)) {
			$this->outputToBuffer($s);
		}

		$this->m_lastCellHeight = $h;

		if(0 < $ln) {
			/*Go to next line */
			$this->m_y += $h;

			if(1 == $ln) {
				$this->m_x = $this->m_lMargin;
			}
		}
		else {
			$this->m_x += $w;
		}
	}

	/**
	 * Writes flowing text to the current page.
	 *
	 * @param $txt string the text to write.
	 * @param $align int the alignment to use.
	 * @param $lineSpacing float the line spacing to use while writing the text.
	 * @param $paragraphSpacing float specifies how much space to leave after each paragraph output.
	 * @param $hangingIndent float the amount of indentation the first line of a paragraph receives compared to the rest
	 * of the paragraph.
	 *
	 * The text may contain line breaks to indicate new paragraphs. Any carriage-return characters are skipped.
	 *
	 * If write() is used to continue a previous paragraph, or to write an incomplete paragraph, the result will be less
	 * than satisfactory when anything other than left alignment is used for all the paragraphs affected. The default is
	 * left-alignment.
	 *
	 * The line spacing is specified as a multiple of the font size. The valid range is 0.5 to 5; anything beyond this
	 * will be clipped. The default is 1 for single line spacing.
	 *
	 * The paragraph spacing is specified in lines (as line spacing). It defaults to the same as the line spacing, so
	 * that effectively each paragraph is spaced from the next by the height of one line in the paragraph.
	 *
	 * The hanging indent is always expressed in mm, and may be positive (to indent further to the right), or negative
	 * (to indent further to the left). It is possible for this indent to extend beyond the margin but not beyond the
	 * page boundary.
	 *
	 * \todo The hanging indent is not yet working correctly.
	 *
	 * @return bool `true` if the text was written to the document, `false` otherwise.
	 */
	public function write(string $txt, int $align = self::AlignLeft, float $lineSpacing = 1.2, float $paragraphSpacing = -1.0, float $hangingIndent = 0.0): bool {
		if(empty($this->m_currentFontInfo) || empty($this->m_currentFontInfo['cw'])) {
			AppLog::warning("no current font", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(0 > $paragraphSpacing) {
			$paragraphSpacing = $lineSpacing;
		}

		$lineSpacing      *= $this->m_currentFontSize;
		$paragraphSpacing *= $this->m_currentFontSize;

		/* trim the paragraph and line spacing - both are now in mm */
		$lineSpacing      = min(max($lineSpacing, 0.5 * $this->m_currentFontSize), 5 * $this->m_currentFontSize);
		$paragraphSpacing = min(max($paragraphSpacing, 0.5 * $this->m_currentFontSize), 5 * $this->m_currentFontSize);

		$cw   = $this->m_currentFontInfo["cw"];
		$w    = $this->m_currentPageWidth - $this->m_rMargin - $this->m_x;
		$wMax = ($w - 2 * $this->m_cMargin) * 1000 / $this->m_currentFontSize;
		$s    = str_replace("\r", "", $txt);
		$nb   = strlen($s);
		$sep  = -1;
		$i    = 0;
		$j    = 0;
		$l    = 0;
		$nl   = 1;

		while($i < $nb) {
			/*Get next character */
			$c = $s{$i};

			if(chr(10) == $c) {
				/* explicit line break is a new paragraph, so also do the paragraph spacing here */
				$this->cell($w, $lineSpacing, substr($s, $j, $i - $j), 0, 0 /*2*/, $align);
				$this->m_y += ($lineSpacing + $paragraphSpacing);

				/* account for hanging indent (first line of para) */
				$this->m_x = max($this->m_lMargin + $hangingIndent, 0);

				$i++;
				$sep = -1;
				$j   = $i;
				$l   = 0;

				if(1 == $nl) {
					$this->m_x = $this->m_lMargin;
					$w         = $this->m_currentPageWidth - $this->m_rMargin - $this->m_x;
					$wMax      = ($w - 2 * $this->m_cMargin) * 1000 / $this->m_currentFontSize;
				}

				++$nl;
				continue;
			}

			if(' ' == $c) {
				$sep = $i;
			}

			/* original code is just $l += $cw[$c];
			 *
			 * however, this does not work for embedded unicode fonts, at least the FreeSans and FreeSerif
			 * families. Because in these cases, $l always remains 0, this check seems to work for most
			 * cases, but it is NOT guaranteed to work all the time.
			 * 
			 * should the character width index be the unicode codepoint? */
			if(!array_key_exists($c, $cw) || 0 == $cw[$c]) {
				$l += $cw[ord($c)];
			}
			else {
				$l += $cw[$c];
			}

			if($l > $wMax) {
				/*Automatic line break */
				if($sep == -1) {
					if($this->m_x > $this->m_lMargin) {
						/*Move to next line */
						$this->m_x = $this->m_lMargin;
						$this->m_y += $lineSpacing;
						$w         = $this->m_currentPageWidth - $this->m_rMargin - $this->m_x;
						$wMax      = ($w - 2 * $this->m_cMargin) * 1000 / $this->m_currentFontSize;
						$i++;
						$nl++;
						continue;
					}

					if($i == $j) {
						$i++;
					}

					$this->cell($w, $lineSpacing, substr($s, $j, $i - $j), 0, 0 /*2*/, $align);
				}
				else {
					$this->cell($w, $lineSpacing, substr($s, $j, $sep - $j), 0, 0 /*2*/, $align);
					$i = $sep + 1;
				}

				$this->m_y += $lineSpacing;
				$this->m_x = $this->m_lMargin;
				$sep       = -1;
				$j         = $i;
				$l         = 0;

				if($nl == 1) {
					$this->m_x = $this->m_lMargin;
					$w         = $this->m_currentPageWidth - $this->m_rMargin - $this->m_x;
					$wMax      = ($w - 2 * $this->m_cMargin) * 1000 / $this->m_currentFontSize;
				}

				$nl++;
			}
			else {
				$i++;
			}
		}

		/* the Last chunk of text */
		if($i != $j) {
			$this->cell($w, $lineSpacing, substr($s, $j), 0, 0, $align);
		}

		return true;
	}


	/**
	 * Writes basic styled flowing text to the document.
	 *
	 * This is very much like the enhanced write() method above, except that it will accept basic HTML style tags in its
	 * text input and style the output accordingly. Note that this method is a great deal slower than the plain write()
	 * method. The extra latency is dependent on the number of tags found in the text, and on the number of recognised
	 * tags found in the text. Even if the text contains no tags at all, it's still slower. The point is, you should
	 * only use it if you're sure you need styled output (for example, you're rendering text from a database and * you
	 * want to be able to provide the facility for users to enter styled content). If the styling is fixed or
	 * predictable (e.g. all headings are in bold type), it's better to set the font between calls to write().
	 *
	 * At present, only the following tags are supported:
	 * - b ... /b
	 * - i ... /i
	 * - u ... /u
	 * - em ... /em
	 *
	 * Other tags that might be included in future:
	 * - tt ... /tt
	 * - sup ... /sup
	 * - sub ... /sub
	 *
	 * The em tag is output as bold; in future it might be more configurable or just change to something else.
	 *
	 * The HTML parser is VERY strict in recognising tags and WILL NOT validate the HTML before attempting to parse it.
	 * In addition, the tags used cannot have any parameters. They may be in upper or lower-case, with the proviso that
	 * all closing tags are in the same case as their corresponding opening tag. If your HTML tags are badly formed you
	 * will receive unpredictable results.
	 *
	 * The text passed should not be full HTML, it must only contain the styling tags: any other HTML tags will be
	 * output verbatim. (The input is not HTML, I am just borrowing HTML tags as a convenient way to provide basic
	 * styling in flowed PDF text.
	 *
	 * Finally, if you want the &lt; (less than), &gt; (greater than) or &amp; symbols to be output, you must use the
	 * HTML entities &amp;lt;, &amp;gt; and &amp;amp; respectively. In some cases they might work without specifying the
	 * entity code, but this cannot be guaranteed (e.g. if you want your PDF to show the text &amp;gt;).
	 *
	 * @param $txt string The text to write.
	 * @param $lineSpacing float The line spacing to write with.
	 * @param $paragraphSpacing float The paragraph spacing.
	 * @param $hangingIndent float The hanging indent.
	 */
	public function writeStyled(string $txt, float $lineSpacing = 1.2, float $paragraphSpacing = 0, float $hangingIndent = 0): void {
		static $htmlEntities = ['&lt;' => '<', '&gt;' => '>', '&amp;' => '&'];

		if(is_numeric($txt)) {
			$txt = '' . $txt;
		}

		/* support <em></em> and <strong></strong> by converting to <b></b> */
		$txt = str_replace("<em>", "<b>", str_replace("</em>", "</b>", $txt));
		$txt = str_replace("<strong>", "<b>", str_replace("</strong>", "</b>", $txt));

		/* hunt for a recognised tag */
		// TODO use regex?
		$pos = strpos($txt, "<");

		/* if no tags found, just output it */
		if($pos === false) {
			$this->write(strtr($txt, $htmlEntities), self::AlignLeft, $lineSpacing, $paragraphSpacing, $hangingIndent);
		}
		else {
			/* output the portion of the text prior to the tag */
			$this->write(strtr(substr($txt, 0, $pos), $htmlEntities), self::AlignLeft, $lineSpacing, $paragraphSpacing, $hangingIndent);

			/* modify the current style according to the tag */
			$oldStyle = self::stringToStyle($this->m_currentFontStyle);
			$tag      = substr($txt, $pos, 3);    /* only 1-letter tags are supported */

			/*AppLog::message('Found HTML tag ' . $tag); */

			switch($tag) {
				case "<b>":
				case "<i>":
				case "<u>":
				case "<B>":
				case "<I>":
				case "<U>":
					$tagL     = strlen($tag);
					$newStyle = self::stringToStyle($oldStyle) | self::stringToStyle($tag{1});
					$endTag   = "</{$tag[1]}>";

					/*AppLog::message($tag . ' - Changing style from ' . $oldStyle . ' to ' . $newStyle); */

					$this->setFont($this->m_currentFontFamily, $newStyle, $this->m_currentFontSizePt);

					if(false === ($endPos = strpos($txt, $endTag, $pos + 1))) {
						$endPos = strlen($txt) + $tagL;
					}

					/* to handle nested tags, we recursively call the styled function */
					$this->writeStyled(substr($txt, $pos + $tagL, $endPos - $pos - $tagL), $lineSpacing, $paragraphSpacing, $hangingIndent);

					/* undo the formatting, and set pos so that it starts the remainder of the text that has not been output */
					$this->setFont($this->m_currentFontFamily, $oldStyle, $this->m_currentFontSizePt);
					$pos = $endPos + strlen($endTag);
					break;

				/* unrecognised tag, so just skip it and continue to write the remainder of the text */
				default:
					$pos = strpos($txt, ">", $pos);
					if($pos !== false) {
						$pos++;
					}
			}

			/* $pos is now false (if the string is consumed) or the start of the text after the styled part, so call again using that portion */
			if($pos && $pos < strlen($txt)) {
				$this->writeStyled(substr($txt, $pos), $lineSpacing, $paragraphSpacing, $hangingIndent);
			}
		}
	}

	/* needs GD 2.x extension; pixel-wise operation, not very fast */
	/* from Valentin Schmidt, http://www.fpdf.org/ */
	/**
	 * Import a PNG image with an alpha channel into the PDF document.
	 *
	 * @param $file string The path to the file to include.
	 * @param $x int The x-coordinate in mm of the left edge of the image.
	 * @param $y int The y-coordinate in mm of the top edge of the image.
	 * @param $w int The image width in mm.
	 * @param $h int The image height in mm.
	 *
	 * @return int the index number of the image in the PDF file, or `false` on error.
	 */
	private function _imagePngWithAlpha(string $file, int $x, int $y, int $w = 0, int $h = 0) {
		$tmpDir             = sys_get_temp_dir();
		$tmpAlpha           = tempnam($tmpDir, "amsk");
		$this->m_tmpFiles[] = $tmpAlpha;
		$tmpPlain           = tempnam($tmpDir, "pmsk");
		$this->m_tmpFiles[] = $tmpPlain;

		list($wpx, $hpx) = getimagesize($file);
		$img      = imagecreatefrompng($file);
		$imgAlpha = imagecreate($wpx, $hpx);

		/* generate gray scale palette */
		for($i = 0; $i < 256; $i++) {
			imagecolorallocate($imgAlpha, $i, $i, $i);
		}

		/* extract alpha channel */
		$xpx = 0;

		while($xpx < $wpx) {
			$ypx = 0;

			while($ypx < $hpx) {
				$cIndex = imagecolorat($img, $xpx, $ypx);
				$col    = imagecolorsforindex($img, $cIndex);

				/* this pow() call fixes the different gamma calculation that GD
				 * appears to use */
				$greyLevel = intval(pow(((127 - $col['alpha']) * 2) / 255, 2.2) * 255);
				imagesetpixel($imgAlpha, $xpx, $ypx, $greyLevel);
				++$ypx;
			}

			++$xpx;
		}

		imagepng($imgAlpha, $tmpAlpha);
		imagedestroy($imgAlpha);
		unset($imgAlpha);

		/* alpha image has DeviceRGB colourspace, it needs to be DeviceGray */
		if(class_exists("Gmagick", false)) {
			try {
				$gImg = new GMagick($tmpAlpha);
				$gImg->setimagecolorspace(Gmagick::COLORSPACE_GRAY);
				$gImg->write($tmpAlpha);
			}
			catch(GmagickException $e) {
				AppLog::error("Exception in GMagick when creating alpha mask: " . $e->getMessage());
			}

			unset($gImg);
		}
		else if(class_exists('Imagick', false)) {
			/* causes a segfault on script exit (ubuntu 10.10, Imagemagick 6.6.2, IMagick extension 3.0.0RC1)
			 * using graphicsmagick with compatibility scripts installed is fine */
			try {
				$iImg = new IMagick($tmpAlpha);
				$iImg->setImageColorspace(Imagick::COLORSPACE_GRAY);
				$iImg->writeImage($tmpAlpha);
			}
			catch(Exception $e) {
				AppLog::error("Exception in IMagick when creating alpha mask: " . $e->getMessage());
			}

			unset($iImg);
		}
		else if('win' == strtolower(substr(PHP_OS, 0, 3))) {
			AppLog::error("running on windows, can't use \"convert\" command for colourspace conversion", __FILE__, __LINE__, __FUNCTION__);
		}
		else {
			/* look for "convert" from imagemagick/graphicsmagic-compatability
			 * or "gm" from graphicsmagick */
			$cmd  = trim(exec("which convert"));
			$args = "-colorspace Gray";

			if(empty($cmd)) {
				$cmd = trim(exec("which gm"));

				if(!empty($cmd)) {
					$args = "convert $args";
				}
			}

			if(empty($cmd)) {
				AppLog::error("could not find \"convert\" command for colourspace conversion", __FILE__, __LINE__, __FUNCTION__);
			}
			else {
				$cmd = escapeshellcmd($cmd) . " $args " . escapeshellarg($tmpAlpha) . " " . escapeshellarg($tmpAlpha);
				AppLog::warning("using fallback \"convert\" command (\"$cmd\") to ensure gray colourspace", __FILE__, __LINE__, __FUNCTION__);
				exec($cmd, $ignored, $res);

				if(0 != $res) {
					AppLog::error("failed to convert mask image to DeviceGray colourspace - alpha channel is unlikely to be supported for image in PDF document", __FILE__, __LINE__, __FUNCTION__);
				}
			}
		}

		/* extract image without alpha channel */
		$imgPlain = imagecreatetruecolor($wpx, $hpx);
		imagecopy($imgPlain, $img, 0, 0, 0, 0, $wpx, $hpx);
		imagepng($imgPlain, $tmpPlain);
		imagedestroy($imgPlain);
		unset($imgPlain);

		/* first embed mask image (w, h, x, will be ignored) */
		$maskImg = $this->image($tmpAlpha, 0, 0, 0, 0, "PNG", "", true);

// AppLog::message('Mask image is ' . $maskImg, __FILE__, __LINE__, __FUNCTION__);
		/* embed image, masked with previously embedded mask */
		$realImage = $this->image($tmpPlain, $x, $y, $w, $h, "PNG", false, $maskImg);
// AppLog::message('Real image is ' . $realImage, __FILE__, __LINE__, __FUNCTION__);

		/* remove the temporary images */
		@unlink($tmpAlpha);
		@unlink($tmpPlain);

		return $realImage;
	}

	/**
	 * Insert an image into the PDF document.
	 *
	 * The width and height are optional. If they are not provided, they will be calculated based on the dimensions and
	 * pixel density of the image. If only one of them is zero, the size on the page of that axis will be calculated
	 * such that the aspect ratio of the image remains intact. For example, if the image is 20px x 10px and is added to
	 * the page using a width of 100mm and a height of 0 the image will be 100mm wide on the page and 50mm tall.
	 *
	 * If `$type` is not specified it is inferred from the file name extension. If it is not specified and the file does
	 * not have an extension, the image is not inserted.
	 *
	 * `$isMask` and `$maskImg` are for internal use only.
	 *
	 * The image is inserted on the current page.
	 *
	 * @param $file string The image file name.
	 * @param $x float The x-coordinate at which the image is to be inserted.
	 * @param $y float Tthe y-coordinate at which the image is to be inserted.
	 * @param $w float _optional_ The width of the image on the page.
	 * @param $h float _optional_ The height of the image on the page.
	 * @param $type string _optional_ The image type. This is **not** a MIME type but rather a file extension typical of
	 * the file type.
	 * @param $isMask bool _optional_ Whether the image is a mask image.
	 * @param $maskImg int _optional_ The index of the mask image to use if the image has transparency and is not a mask
	 * image itself.
	 *
	 * @return int|bool the index number of the embedded image in the PDF file, or `false` on error.
	 */
	public function image(string $file, float $x, float $y, float $w = 0.0, float $h = 0.0, string $type = "", bool $isMask = false, int $maskImg = 0) {
		if(!is_string($file)) {
			AppLog::error("the image file provided was not a string", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		/* put an image on the page */
		if(!array_key_exists($file, $this->m_images)) {
			/*First use of image, get info */
			if("" == $type) {
				if(false === ($pos = strrpos($file, '.'))) {
					/* peek at file to guess file type */
					$f = fopen($file, "rb");

					if($f) {
						if("\x89PNG\x0d\x0a\x1a\x0a" == fread($f, 8)) {
							$type = "png";
						}
						else if(0 == fseek($f, 0) && "\xFF\xD8\xFF" == fread($f, 3) && 0 == fseek($f, -2, SEEK_END) && "\xFF\xD9" == fread($f, 2)) {
							$type = "jpeg";
						}

						fclose($f);
					}

					if("" == $type) {
						AppLog::error("image file has no extension and no type was specified: $file", __FILE__, __LINE__, __FUNCTION__);
						return false;
					}
				}

				$type = substr($file, $pos + 1);
			}

			$type = strtolower($type);

			if("jpg" == $type || "jpeg" == $type) {
				$info = $this->parseJpeg($file);
			}
			else if("png" == $type) {
				$info = $this->parsePng($file);

				if("alpha" == $info) {
					return $this->_imagePngWithAlpha($file, $x, $y, $w, $h);
				}
			}
			else {
				$myMethod = "_parse$type";

				if(!is_callable([$this, $myMethod], false)) {
					AppLog::error("Unsupported image type: $type", __FILE__, __LINE__, __FUNCTION__);
					return false;
				}

				/* methods must return null if the image cannot be parsed successfully */
				$info = $this->$myMethod($file);
			}

			if(0 < $maskImg) {
				$info["mask"] = $maskImg;
			}

			$info["i"]             = count($this->m_images) + 1;
			$this->m_images[$file] = $info;
		}
		else {
			$info = $this->m_images[$file];
		}

		/* Automatic width and height calculation if needed */
		/* get some division by zero warnings here - not sure if in unit converter
		 * or here (see http://mecon.nomadit.co.uk/mecon.php5?app=conference&object=Delegate&action=certificate&DelegateID=5968)
		 * error log indicates $info['h'] and $info['w'] do not exist */
		if(0 == $w && 0 == $h) {
			/*Put image at 72 dpi */
			$w = self::ptToMm($info["w"]);
			$h = self::ptToMm($info["h"]);
		}
		else if(0 == $w) {
			$w = $h * $info["w"] / $info["h"];
		}
		else if(0 == $h) {
			$h = $w * $info["h"] / $info["w"];
		}

		if(!$isMask) {
			$this->outputToBuffer(sprintf("q %s 0 0 %s %s %s cm /I%d Do Q", self::postscriptNumber(self::mmToPt($w)), self::postscriptNumber(self::mmToPt($h)), self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y + $h))), $info["i"]));
		}

		return $info['i'];
	}

	/**
	 * Get the current x-coordinate of the text cursor.
	 *
	 * The x-coordinate is provided in user units.
	 *
	 * @return float|null the x-coordinate, or _null_ on error.
	 */
	public function x(): ?float {
		return $this->m_x;
	}

	/**
	 * Set the current x-coordinate of the text cursor.
	 *
	 * @param $x float the x-coordinate. It is measured in user units.
	 *
	 * If the coordinate is negative, it is used as an offset from the right
	 * edge of the page; if it is positive, it is used as an offset from the
	 * left edge of the page.
	 *
	 * @note the value is not trimmed to ensure it is within the bounds of
	 * the page.
	 */
	public function setX(float $x) {
		if(0 <= $x) {
			$this->m_x = $x;
		}
		else {
			$this->m_x = $this->m_currentPageWidth + $x;
		}
	}

	/**
	 * Get the current y-coordinate of the text cursor.
	 *
	 * The y-coordinate is provided in user units.
	 *
	 * @return float|null y-coordinate, or _null_ on error.
	 */
	public function y(): ?float {
		return $this->m_y;
	}

	/**
	 * Set the current y-coordinate of the text cursor.
	 *
	 * @param $y float the y-coordinate. It is measured in user units.
	 *
	 * If the coordinate is negative, it is used as an offset from the bottom edge of the page; if it is positive, it is
	 * used as an offset from the top edge of the page.
	 *
	 * @note
	 * - The value is not trimmed to ensure it is within the bounds of the page.
	 * - A successful call will reset the x-coordinate to the left margin.
	 */
	public function setY(float $y) {
		$this->m_x = $this->m_lMargin;

		if(0 <= $y) {
			$this->m_y = $y;
		}
		else {
			$this->m_y = $this->m_currentPageHeight + $y;
		}
	}

	/**
	 * Set the current text cursor location.
	 *
	 * @param $x float the x-coordinate. It is measured in user units.
	 * @param $y float the y-coordinate. It is measured in user units.
	 *
	 * If the x-coordinate is negative, it is used as an offset from the right
	 * edge of the page; if it is positive, it is used as an offset from the
	 * left edge of the page.
	 *
	 * If the y-coordinate is negative, it is used as an offset from the bottom
	 * edge of the page; if it is positive, it is used as an offset from the
	 * top edge of the page.
	 *
	 * @note The value is not trimmed to ensure it is within the bounds of
	 * the page.
	 */
	public function setCursorLocation(float $x, float $y): void {
		$this->setY($y);
		$this->setX($x);
	}

	/**
	 * Fetch the current alpha value.
	 *
	 * The alpha value varies between 0.0 and 1.0, 0.0 being completely transparent and 1.0 being completely opaque.
	 *
	 * @return double The current alpha value.
	 */
	public function alpha(): float {
		return $this->m_currentAlpha;
	}

	/**
	 * Set the current alpha value.
	 *
	 * @param $alpha `double` The alpha value.
	 *
	 * The alpha value varies between 0.0 and 1.0, 0.0 being completely
	 * transparent and 1.0 being completely opaque.
	 */
	public function setAlpha(float $alpha): void {
		$alpha = min(1.0, max(0.0, $alpha));

		/* set alpha for stroking (CA) and non-stroking (ca) operations */
		$gs = $this->_addExtGState(['ca' => $alpha, 'CA' => $alpha, 'BM' => '/Normal']);
		$this->_setExtGState($gs);
	}

	/* TODO document. */
	protected function _addExtGState(array $parms) {
		$i                               = count($this->m_extGStates) + 1;
		$this->m_extGStates[$i]["parms"] = $parms;
		return $i;
	}

	/* TODO document. */
	protected function _setExtGState(int $gs) {
		$this->outputToBuffer(sprintf("/GS%d gs", $gs));
	}

	/**
	 * Save the PDF document to a file.
	 *
	 * @param $fileName string the file to which to save the data.
	 *
	 * The file is opened and written without checking whether it already
	 * exists. If it does, it will be overwritten if the document is
	 * successfully saved and, in certain circumstances, even if it is
	 * not successfully saved.
	 *
	 * @return bool `true` if the PDF document was saved, `false` otherwise.
	 */
	public function save(string $fileName): bool {
		if(empty($fileName)) {
			return false;
		}

		$f = fopen($fileName, "wb+");

		if(!$f) {
			AppLog::error("failed to open file for writing: \"$fileName\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		/* check for some data */
		if('' == ($data = $this->pdfData())) {
			AppLog::error("failed to get PDF data to write: \"$fileName\"", __FILE__, __LINE__, __FUNCTION__);
			fclose($f);
			return false;
		}

		$totalWritten  = 0;
		$contentLength = strlen($data);
		$errorCount    = 0;
		$errorRetries  = 3;

		while($totalWritten < $contentLength && $errorCount < $errorRetries) {
			$bytes = fwrite($f, $data);

			if(false === $bytes) {
				++$errorCount;
			}
			else {
				$totalWritten += $bytes;
				$errorCount   = 0;
			}
		}

		fclose($f);

		if(0 < $errorCount || $totalWritten != $contentLength) {
			AppLog::error("errors occurred while writing PDF data to \"$fileName\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return true;
	}

	/**
	 * Get the data representing the PDF document.
	 *
	 * @return string|null The data encoded as bytes in a string or _null_ on error.
	 */
	public function pdfData(): ?string {
		/* Finish document if necessary */
		if($this->m_state < 3) {
			$this->close();
		}

		return $this->m_buffer;
	}

	/**
	 * Dump the document pages to the output stream.
	 */
	protected function _emitPages(): void {
		$nb = $this->m_pageNumber;

		if("" != $this->m_pageCountPlaceholder) {
			/*Replace number of pages */
			for($i = 1; $i <= $nb; ++$i) {
				$this->m_pages[$i] = str_replace($this->m_pageCountPlaceholder, $nb, $this->m_pages[$i]);
			}
		}

		if($this->m_documentOrientation == self::PortraitOrientation) {
			$wPt = $this->m_formatWidthPt;
			$hPt = $this->m_formatHeightPt;
		}
		else {
			$wPt = $this->m_formatHeightPt;
			$hPt = $this->m_formatWidthPt;
		}

		/* this is only used once, so no need for a variable? */
		$filter = (($this->m_compress) ? "/Filter /FlateDecode " : "");

		for($i = 1; $i <= $nb; ++$i) {
			/*LibEquit\Page */
			$this->_newObject();
			$this->outputToBuffer("<</Type /Page");
			$this->outputToBuffer("/Parent 1 0 R");

			if(array_key_exists($i, $this->m_pageOrientationChanges)) {
				$this->outputToBuffer(sprintf("/MediaBox [0 0 %s %s]", self::postscriptNumber($hPt), self::postscriptNumber($wPt)));
			}

			$this->outputToBuffer("/Resources 2 0 R");
			$this->outputToBuffer("/Contents " . ($this->m_objectNumber + 1) . " 0 R>>");
			$this->outputToBuffer("endobj");

			/* page content */
			$p = (($this->m_compress) ? gzcompress($this->m_pages[$i]) : $this->m_pages[$i]);
			$this->_newObject();
			$this->outputToBuffer("<<{$filter}/Length " . strlen($p) . ">>");
			$this->emitStream($p);
			$this->outputToBuffer("endobj");
		}

		/*Pages root */
		$this->m_offsets[1] = strlen($this->m_buffer);
		$this->outputToBuffer("1 0 obj");
		$this->outputToBuffer("<</Type /Pages");
		$kids = "/Kids [";

		for($i = 0; $i < $nb; ++$i) {
			$kids .= (3 + 2 * $i) . " 0 R ";
		}

		$this->outputToBuffer("$kids]");
		$this->outputToBuffer("/Count $nb");
		$this->outputToBuffer(sprintf("/MediaBox [0 0 %s %s]", self::postscriptNumber($wPt), self::postscriptNumber($hPt)));
		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");
	}

	/**
	 * Dump a Core font to the PDF stream.
	 *
	 * @param $font array The font data to dump.
	 */
	protected function _emitCoreFont(array $font): void {
		/*Standard font */
		$this->_newObject();
		$this->outputToBuffer("<</Type /Font");
		$this->outputToBuffer("/BaseFont /{$font["name"]}");
		$this->outputToBuffer("/Subtype /Type1");

		if("Symbol" != $font["name"] && "ZapfDingbats" != $font["name"]) {
			$this->outputToBuffer("/Encoding /WinAnsiEncoding");
		}

		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");
	}

	/**
	 * Dump a TrueType font to the PDF stream.
	 *
	 * @param $font array The truetype font to dump.
	 */
	protected function _emitTrueTypeFont(array &$font) {
		$this->_emitTrueTypeOrType1Font($font, "TrueType");
	}

	/**
	 * Dump a Type1 font to the PDF stream.
	 *
	 * @param $font array The Type1 font to dump.
	 */
	protected function _emitType1Font(array &$font) {
		$this->_emitTrueTypeOrType1Font($font, "Type1");
	}

	/**
	 * Dump a TrueType or Type1 font to the PDF stream.
	 *
	 * @param $font array The Type1 font to dump.
	 * @param $type string The font type.
	 */
	private function _emitTrueTypeOrType1Font(array $font, string $type) {
		$fontObjectNumber = $this->m_objectNumber;

		/*Additional Type1 or TrueType font */
		$this->_newObject();
		$this->outputToBuffer("<</Type /Font");
		$this->outputToBuffer("/BaseFont /{$font['name']}");
		$this->outputToBuffer("/Subtype /$type");
		$this->outputToBuffer("/FirstChar 32 /LastChar 255");
		$this->outputToBuffer("/Widths " . ($this->m_objectNumber + 1) . " 0 R");
		$this->outputToBuffer("/FontDescriptor " . ($this->m_objectNumber + 2) . " 0 R");

		if(array_key_exists("enc", $font) && $font["enc"]) {
			if(array_key_exists("diff", $font)) {
				$this->outputToBuffer("/Encoding " . ($fontObjectNumber + $font["diff"]) . " 0 R");
			}
			else {
				$this->outputToBuffer("/Encoding /WinAnsiEncoding");
			}
		}

		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");
		/*Widths */
		$this->_newObject();
		$cw = &$font["cw"];
		$s  = "[";

		for($i = 32; $i <= 255; ++$i) {
			$s .= $cw[chr($i)] . " ";
		}

		$this->outputToBuffer("$s]");
		$this->outputToBuffer("endobj");

		/*Descriptor */
		$this->_newObject();
		$s = "<</Type /FontDescriptor /FontName /{$font["name"]}";

		foreach($font["desc"] as $k => $v) {
			$s .= " /$k $v";
		}

		if(array_key_exists("file", $font) && $font["file"]) {
			$s .= " /FontFile" . ("Type1" == $type ? "" : "2") . " {$this->m_fontFiles[$font["file"]]["n"]} 0 R";
		}

		$this->outputToBuffer("$s>>");
		$this->outputToBuffer("endobj");
	}

	/**
	 * Dump a TrueTypeUnicode font to the PDF stream.
	 *
	 * @param $font array The font description to dump.
	 */
	protected function _emitTrueTypeUnicodeFont(&$font) {
		/* Type0 Font */
		$this->_newObject();
		$this->outputToBuffer("<</Type /Font");
		$this->outputToBuffer("/Subtype /Type0");
		$this->outputToBuffer("/BaseFont /" . $font["name"] . "-UCS");
		$this->outputToBuffer("/Encoding /Identity-H");
		$this->outputToBuffer("/DescendantFonts [" . ($this->m_objectNumber + 1) . " 0 R]");
		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");

		/* CIDFont */
		$this->_newObject();
		$this->outputToBuffer("<</Type /Font");
		$this->outputToBuffer("/Subtype /CIDFontType2");
		$this->outputToBuffer("/BaseFont /{$font['name']}");
		$this->outputToBuffer("/CIDSystemInfo <</Registry (Adobe) /Ordering (UCS) /Supplement 0>>");
		$this->outputToBuffer("/FontDescriptor " . ($this->m_objectNumber + 1) . " 0 R");
		$widths = $s = "";

		foreach($font["cw"] as $i => $w) {
			$widths .= "$i [$w] ";
		}

		$this->outputToBuffer("/W [$widths]");
		$this->outputToBuffer("/CIDToGIDMap " . ($this->m_objectNumber + 2) . " 0 R");
		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");

		/* font descriptor */
		$this->_newObject();
		$this->outputToBuffer("<</Type /FontDescriptor");
		$this->outputToBuffer("/FontName /{$font["name"]}");

		foreach($font["desc"] as $k => $v) {
			$s .= " /$k $v";
		}

		if($font["file"]) {
			$s .= " /FontFile2 {$this->m_fontFiles[$font["file"]]["n"]} 0 R";
		}

		$this->outputToBuffer($s);
		$this->outputToBuffer(">>");
		$this->outputToBuffer("endobj");

		/* embed CIDToGIDMap */
		$file = self::fontPath() . $font["ctg"];
		$size = filesize($file);

		if(!$size) {
			AppLog::error("font file \"$file\" missing, empty or unreadable", __FILE__, __LINE__, __FUNCTION__);
			/* emit an empty object so that the object references generated above
			 * are correct */
			$this->_newObject();
			$this->outputToBuffer("<</Length " . $size);
			$this->outputToBuffer(">>");
			$this->outputToBuffer("endobj");
			return;
		}

		$this->_newObject();
		$this->outputToBuffer("<</Length $size");

		if(substr($file, -2) == ".z") {
			$this->outputToBuffer("/Filter /FlateDecode");
		}

		$this->outputToBuffer(">>");
		$this->emitStream(file_get_contents($file));
		$this->outputToBuffer("endobj");
	}

	/**
	 * Dump the fonts into the PDF data buffer. */
	protected function _emitFonts(): void {
		foreach($this->m_diffs as $diff) {
			/*Encodings */
			$this->_newObject();
			$this->outputToBuffer("<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [" . $diff . ']>>');
			$this->outputToBuffer("endobj");
		}

		/* temporarily force magic quotes off */
		$php53OrLater = (0 <= version_compare(PHP_VERSION, "5.3.0"));

		if(!$php53OrLater) {
			$mqr = get_magic_quotes_runtime();
			/** @noinspection PhpDeprecationInspection */
			set_magic_quotes_runtime(0);
		}

		foreach($this->m_fontFiles as $file => $info) {
			/*Font file embedding */
			$this->_newObject();
			$this->m_fontFiles[$file]["n"] = $this->m_objectNumber;
			$font                          = file_get_contents(self::fontPath() . $file, true);
			$compressed                    = (substr($file, -2) == ".z");

			if(!$compressed && array_key_exists("length2", $info)) {
				$header = (128 == ord($font{0}));

				/* strip first binary header */
				if($header) {
					$font = substr($font, 6);
				}

				/*Strip second binary header */
				if($header && 128 == ord($font{$info["length1"]})) {
					$font = substr($font, 0, $info["length1"]) . substr($font, $info["length1"] + 6);
				}
			}

			$this->outputToBuffer("<</Length " . strlen($font));

			if($compressed) {
				$this->outputToBuffer("/Filter /FlateDecode");
			}

			$this->outputToBuffer("/Length1 ${info["length1"]}");

			if(array_key_exists("length2", $info)) {
				$this->outputToBuffer("/Length2 ${info['length2']} /Length3 0");
			}

			$this->outputToBuffer(">>");
			$this->emitStream($font);
			$this->outputToBuffer("endobj");
		}

		/* restore magic quotes setting */
		if(!$php53OrLater) {
			/** @noinspection PhpDeprecationInspection */
			/** @noinspection PhpUndefinedVariableInspection */
			set_magic_quotes_runtime($mqr);
		}

		foreach($this->m_fonts as $k => $font) {
			$this->m_fonts[$k]["n"] = $this->m_objectNumber + 1;
			/* additional font types can be supported by (subclassing and) implementing a protected _emit<type>Font method */
			$fontMethod = "_emit" . strtolower($font["type"]) . "Font";

			if(!is_callable([$this, $fontMethod], false)) {
				AppLog::error("unsupported font type: ${font['type']}", __FILE__, __LINE__, __FUNCTION__);
				continue;
			}

			$this->$fontMethod($font);
		}
	}

	/**
	 * Dump the images to the PDF output stream.
	 */
	protected function _emitImages(): void {
		$filter = ($this->m_compress ? "/Filter /FlateDecode " : "");
		reset($this->m_images);

		foreach($this->m_images as $file => $info) {
			$this->_newObject();
			$this->m_images[$file]["n"] = $this->m_objectNumber;
			$this->outputToBuffer("<</Type /XObject");
			$this->outputToBuffer("/Subtype /Image");
			$this->outputToBuffer("/Width {$info["w"]}");
			$this->outputToBuffer("/Height {$info["h"]}");

			/* NOTE this only works because the mask image is always inserted into
			 * the images array immediately before the actual image it masks */
			if(array_key_exists("mask", $info)) {
				$this->outputToBuffer("/SMask " . ($this->m_objectNumber - 1) . " 0 R");
			}

			if("Indexed" == $info['cs']) {
				$this->outputToBuffer("/ColorSpace [/Indexed /DeviceRGB " . (strlen($info['pal']) / 3 - 1) . " " . ($this->m_objectNumber + 1) . " 0 R]");
			}
			else {
				$this->outputToBuffer("/ColorSpace /{$info["cs"]}");

				if('DeviceCMYK' == $info["cs"]) {
					$this->outputToBuffer("/Decode [1 0 1 0 1 0 1 0]");
				}
			}

			$this->outputToBuffer("/BitsPerComponent {$info["bpc"]}");

			if(array_key_exists("f", $info)) {
				$this->outputToBuffer("/Filter /{$info["f"]}");
			}

			if(array_key_exists("parms", $info)) {
				$this->outputToBuffer($info["parms"]);
			}

			if(array_key_exists("trns", $info) && is_array($info["trns"])) {
				$trns = "";

				for($i = 0; $i < count($info["trns"]); ++$i) {
					$trns .= "{$info["trns"][$i]} {$info["trns"][$i]} ";
				}

				$this->outputToBuffer("/Mask [$trns]");
			}

			$this->outputToBuffer("/Length " . strlen($info["data"]) . ">>");
			$this->emitStream($info["data"]);
			unset($this->m_images[$file]["data"]);
			$this->outputToBuffer("endobj");

			/*Palette */
			if("Indexed" == $info["cs"]) {
				$this->_newObject();
				$pal = (($this->m_compress) ? gzcompress($info["pal"]) : $info["pal"]);
				$this->outputToBuffer("<<$filter/Length " . strlen($pal) . ">>");
				$this->emitStream($pal);
				$this->outputToBuffer("endobj");
			}
		}
	}

	/**
	 * Dump the XObject dictionary to the PDF output stream.
	 */
	protected function _emitXObjectDict(): void {
		foreach($this->m_images as $image) {
			$this->outputToBuffer("/I{$image["i"]} {$image["n"]} 0 R");
		}
	}

	/**
	 * Dump the resource dictionary to the PDF output stream.
	 */
	protected function _emitResourceDict(): void {
		$this->outputToBuffer('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
		$this->outputToBuffer('/Font <<');

		foreach($this->m_fonts as $font) {
			$this->outputToBuffer('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
		}

		$this->outputToBuffer('>>');
		$this->outputToBuffer('/XObject <<');
		$this->_emitXObjectDict();
		$this->outputToBuffer('>>');

		$this->outputToBuffer('/ExtGState <<');

		foreach($this->m_extGStates as $key => $extGState) {
			$this->outputToBuffer('/GS' . $key . ' ' . $extGState['n'] . ' 0 R');
		}

		$this->outputToBuffer('>>');
	}

	/**
	 * Dump the recources to the PDF output stream.
	 */
	protected function _emitResources(): void {
		$this->_emitExtGStates();
		$this->_emitFonts();
		$this->_emitImages();

		/*Resource dictionary */
		$this->m_offsets[2] = strlen($this->m_buffer);
		$this->outputToBuffer('2 0 obj');
		$this->outputToBuffer('<<');
		$this->_emitResourceDict();
		$this->outputToBuffer('>>');
		$this->outputToBuffer('endobj');
	}

	/**
	 * Dump the document information to the PDF output stream.
	 */
	protected function _emitInfo(): void {
		$this->outputToBuffer('/Producer ' . self::_textString('PdfWriter v' . self::ClassVersion));
		if(!empty($this->m_title)) {
			$this->outputToBuffer('/Title ' . self::_textString($this->m_title));
		}

		if(!empty($this->m_subject)) {
			$this->outputToBuffer('/Subject ' . self::_textString($this->m_subject));
		}

		if(!empty($this->m_author)) {
			$this->outputToBuffer('/Author ' . self::_textString($this->m_author));
		}

		if(!empty($this->m_keywords)) {
			$this->outputToBuffer('/Keywords ' . self::_textString($this->m_keywords));
		}

		if(!empty($this->m_creator)) {
			$this->outputToBuffer('/Creator ' . self::_textString($this->m_creator));
		}

		$this->outputToBuffer('/CreationDate ' . self::_textString('D:' . $this->m_creationDate->format('YmdHis')));
	}

	/**
	 * Dump the catalogue to the PDF output stream.
	 */
	protected function _emitCatalog(): void {
		$this->outputToBuffer('/Type /Catalog');
		$this->outputToBuffer('/Pages 1 0 R');

		switch($this->m_zoomMode) {
			default:
				$this->outputToBuffer('/OpenAction [3 0 R /XYZ null null ' . ($this->m_zoomMode / 100) . ']');
				break;

			case self::ViewerDefaultZoom:
				/* this means application default, so no setting */
				break;

			case self::FullPageZoom:
				$this->outputToBuffer('/OpenAction [3 0 R /Fit]');
				break;

			case self::FullWidthZoom:
				$this->outputToBuffer('/OpenAction [3 0 R /FitH null]');
				break;

			case self::RealZoom:
				$this->outputToBuffer('/OpenAction [3 0 R /XYZ null null 1]');
				break;
		}

		switch($this->m_layoutMode) {
			default:
				AppLog::error('invalid layout mode - using default', __FILE__, __LINE__, __FUNCTION__);
			case self::SingleLayout:
				$this->outputToBuffer('/PageLayout /SinglePage');
				break;

			case self::ContinuousLayout:
				$this->outputToBuffer('/PageLayout /OneColumn');
				break;

			case self::TwoColumnLayout:
				$this->outputToBuffer('/PageLayout /TwoColumnLeft');
				break;
		}
	}

	/**
	 * Dump the PDF file header to the PDF output stream. */
	protected function _emitHeader(): void {
		$this->outputToBuffer('%PDF-' . self::PdfVersion);
	}

	/**
	 * Dump the PDF file footer to the PDF output stream. */
	protected function _emitTail(): void {
		$this->outputToBuffer('/Size ' . ($this->m_objectNumber + 1));
		$this->outputToBuffer('/Root ' . $this->m_objectNumber . ' 0 R');
		$this->outputToBuffer('/Info ' . ($this->m_objectNumber - 1) . ' 0 R');
	}

	/**
	 * Close the document for future edits.
	 *
	 * This method emits the document content to the internal PDF data stream
	 * and puts the object into a state where it can no longer be modified.
	 */
	protected function _endDoc(): void {
		$this->_emitHeader();
		$this->_emitPages();
		$this->_emitResources();
		/*Info */
		$this->_newObject();
		$this->outputToBuffer('<<');
		$this->_emitInfo();
		$this->outputToBuffer('>>');
		$this->outputToBuffer('endobj');
		/*Catalog */
		$this->_newObject();
		$this->outputToBuffer('<<');
		$this->_emitCatalog();
		$this->outputToBuffer('>>');
		$this->outputToBuffer('endobj');
		/*Cross-ref */
		$o = strlen($this->m_buffer);
		$this->outputToBuffer('xref');
		$this->outputToBuffer('0 ' . ($this->m_objectNumber + 1));
		$this->outputToBuffer('0000000000 65535 f ');

		for($i = 1; $i <= $this->m_objectNumber; ++$i) {
			$this->outputToBuffer(sprintf('%010d 00000 n ', $this->m_offsets[$i]));
		}

		/*Trailer */
		$this->outputToBuffer('trailer');
		$this->outputToBuffer('<<');
		$this->_emitTail();
		$this->outputToBuffer('>>');
		$this->outputToBuffer('startxref');
		$this->outputToBuffer($o);
		$this->outputToBuffer('%%EOF');
		$this->m_state = 3;
	}

	/**
	 * Start a new page.
	 *
	 * @param $orientation int The orientation of the page.
	 *
	 * If no orientation is provided, the default page orientation for the
	 * document is used.
	 */
	protected function _beginPage(?int $orientation = null): void {
		$this->m_pageNumber++;
		$this->m_pages[$this->m_pageNumber] = '';
		$this->m_state                      = 2;
		$this->m_x                          = $this->m_lMargin;
		$this->m_y                          = $this->m_tMargin;
		$this->m_currentFontFamily          = '';

		/*LibEquit\Page orientation */
		self::_validateOrientation($orientation, $valid);

		if(!$valid) {
			$orientation = $this->m_documentOrientation;
		}
		else if($orientation != $this->m_documentOrientation) {
			$this->m_pageOrientationChanges[$this->m_pageNumber] = true;
		}

		if($orientation != $this->m_currentOrientation) {
			/*Change orientation */
			if($orientation == self::PortraitOrientation) {
				$this->m_currentPageWidthPt  = $this->m_formatWidthPt;
				$this->m_currentPageHeightPt = $this->m_formatHeightPt;
				$this->m_currentPageWidth    = $this->m_formatWidth;
				$this->m_currentPageHeight   = $this->m_formatHeight;
			}
			else {
				$this->m_currentPageWidthPt  = $this->m_formatHeightPt;
				$this->m_currentPageHeightPt = $this->m_formatWidthPt;
				$this->m_currentPageWidth    = $this->m_formatHeight;
				$this->m_currentPageHeight   = $this->m_formatWidth;
			}

			$this->m_pageBreakTrigger   = $this->m_currentPageHeight - $this->m_bMargin;
			$this->m_currentOrientation = $orientation;
		}
	}

	/**
	 * Validates an orientation string.
	 *
	 * This function is guaranteed to return a valid orientation. If the
	 * provided orientation is not valid, the default orientation is
	 * returned.
	 *
	 * If given, the variable used to supply $bool will receive true
	 * if the given orientation was valid, false otherwise.
	 *
	 * @param $orientation int The page orientation to validate.
	 * @param $valid bool Variable to receive the outcome.
	 *
	 * @return int The orientation.
	 */
	protected static function _validateOrientation(int $orientation, &$valid = true): int {
		if($orientation != self::LandscapeOrientation && $orientation != self::PortraitOrientation) {
			$valid       = false;
			$orientation = self::DefaultOrientation;
		}
		else {
			$valid = true;
		}

		return $orientation;
	}

	/**
	 * Validates a page format, either as a 2-element array of point dimensions or
	 * a fixed-format string.
	 *
	 * This function is guaranteed to return a valid 2-element format array
	 * containing the point dimensions of the page. If the
	 * provided format is not valid, the default orientation is
	 * returned.
	 *
	 * If given, the variable used to supply $valid will receive true
	 * if the given format was valid, false otherwise.
	 *
	 * @param $format string|array The format to validate.
	 * @param bool $valid Whether or not the format was valid.
	 *
	 * @return array The dimensions of the validated format, or the default page format dimensions on error.
	 */
	protected static function _validatePageFormat($format, &$valid = true): array {
		if(is_array($format)) {
			/* must be array of at least 2 elements, both of which are numbers > 0 */
			if(2 <= count($format) && is_numeric($format[0]) && is_numeric($format[1]) && 0 < $format[0] && 0 < $format[1]) {
				$valid = true;
				return $format;
			}
		}
		else {
			$format = strtolower(trim($format));

			if(array_key_exists($format, self::$s_availablePageFormats)) {
				$valid = true;
				return self::$s_availablePageFormats[$format];
			}
		}

		$valid = false;
		return self::$s_availablePageFormats[self::DefaultPageFormat];
	}

	protected function _endPage(): void {
		/* End of page contents */
		$this->m_state = 1;
	}

	protected function _newObject(): void {
		/* Begin a new object */
		$this->m_objectNumber++;
		$this->m_offsets[$this->m_objectNumber] = strlen($this->m_buffer);
		$this->outputToBuffer($this->m_objectNumber . ' 0 obj');
	}

	/**
	 * Render the underline for some text.
	 *
	 * @param $x `int` The x-coordinate where the text starts.
	 * @param $y `int` The y-coordinate where the text starts.
	 * @param $txt `string` The text to underline.
	 *
	 * The text is provided only so that the length of the underline can be calculated.
	 *
	 * @return string The postscript commands to draw the underline to the page.
	 */
	protected function _doUnderline($x, $y, $txt): string {
		/* Underline text */
		$up = $this->m_currentFontInfo['up'];
		$ut = $this->m_currentFontInfo['ut'];
		$w  = $this->stringWidth($txt) + $this->m_wordSpacing * substr_count($txt, ' ');

		return sprintf("%s %s %s %s re f", self::postscriptNumber(self::mmToPt($x)), self::postscriptNumber(self::mmToPt($this->m_currentPageHeight - ($y - $up / 1000 * $this->m_currentFontSize))), self::postscriptNumber(self::mmToPt($w)), self::postscriptNumber(self::mmToPt(-$ut / 1000)));
	}

	/**
	 * Parses a JPEG file for use in a PDF document.
	 *
	 * The returned info array contains the following members:
	 * - `w` the width in pixels of the image.
	 * - `h` the height in pixels of the image.
	 * - `cs` the colour space for the image data.
	 * - `bpc` the number of bits per colour channel in the image data.
	 * - `f` the name of a generic decoding function to use for the image data. (This is an indicator to the PDF viewing
	 *   application not to the PdfWriter object.)
	 * - `data` the actual image data.
	 *
	 * @param $file string The path of the file to parse.
	 *
	 * @return array|null image meta info and data, or `null` on error.
	 */
	protected function parseJpeg(string $file): ?array {
		/* extract info from a JPEG file */
		// TODO don't use getimagesize() for this - use FileInfo instead?
		$a = getimagesize($file);

		if(!$a) {
			AppLog::error("Missing or incorrect image file: " . $file, __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(count($a) < 3 || 2 != $a[2]) {
			AppLog::error("\"$file\" is not a jpeg file", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(!isset($a["channels"]) || 3 == $a["channels"]) {
			$colourSpace = "DeviceRGB";
		}
		else if(4 == $a["channels"]) {
			$colourSpace = "DeviceCMYK";
		}
		else {
			$colourSpace = "DeviceGray";
		}

		$bpc = (isset($a["bits"]) ? $a["bits"] : 8);

		// Read whole file
		$data = file_get_contents($file, false);
		return ["w" => $a[0], "h" => $a[1], "cs" => $colourSpace, "bpc" => $bpc, "f" => "DCTDecode", "data" => $data];
	}

	/**
	 * Parses a PNG file for use in a PDF document.
	 *
	 * The returned info array contains the following members:
	 * - `w` the width in pixels of the image.
	 * - `h` the height in pixels of the image.
	 * - `cs` the colour space for the image data.
	 * - `bpc` the number of bits per colour channel in the image data.
	 * - `f` the name of a generic decoding function to use for the image data. (This is an indicator to the PDF viewing 
	 *   application not to the PdfWriter object.)
	 * - `parms` image parameters
	 * - `pal` colour palette information
	 * - `trns` transparencey information
	 * - `data` the actual image data.
	 *
	 * @param $file string The path of the file to parse.
	 *
	 * @return array|string|null Image meta info and data, or `null` on error.
	 */
	protected function parsePng(string $file) {
		/* extract info from a PNG file */
		$f = fopen($file, 'rb');

		if(!$f) {
			AppLog::error("can't open image file \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		// Check signature 
		if("\x89PNG\x0d\x0a\x1a\x0a" != fread($f, 8)) {
			AppLog::error("\"$file\" is not a PNG file", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		// Read header chunk
		fread($f, 4);

		if(fread($f, 4) != "IHDR") {
			AppLog::error("malformed PNG file (missing IHDR): \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$w   = self::fReadInt($f);
		$h   = self::fReadInt($f);
		$bpc = ord(fread($f, 1));

		if($bpc > 8) {
			AppLog::error("only up to 8 bits per channel supported in PNG files (found $bpc): \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$ct = ord(fread($f, 1));

		switch($ct) {
			case 0:
				$colspace = "DeviceGray";
				break;
				
			case 2:
				$colspace = "DeviceRGB";
				break;
				
			case 4:
				$colspace = "Indexed";
				break;
				
			default:
				fclose($f);
				return "alpha";
		}

		if(0 != ord(fread($f, 1))) {
			AppLog::error("unknown PNG compression method: \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(0 != ord(fread($f, 1))) {
			AppLog::error("unknown PNG filter method: \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(0 != ord(fread($f, 1))) {
			AppLog::error("PNG interlacing not supported: \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		fread($f, 4);
		$parms = "/DecodeParms <</Predictor 15 /Colors " . (2 == $ct ? 3 : 1) . " /BitsPerComponent $bpc /Columns $w>>";

		/* Scan chunks looking for palette, transparency and image data */
		$pal  = "";
		$trns = "";
		$data = "";

		do {
			$n    = self::fReadInt($f);
			$type = fread($f, 4);

			if("PLTE" == $type) {
				/* palette chunk */
				$pal = fread($f, $n);
				fread($f, 4);
			}
			else if("tRNS" == $type) {
				/* transparency chunk */
				$t = fread($f, $n);

				if(0 == $ct) {
					$trns = [ord(substr($t, 1, 1))];
				}
				else if(2 == $ct) {
					$trns = [ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1))];
				}
				else {
					$pos = strpos($t, chr(0));

					if(false !== $pos) {
						$trns = [$pos];
					}
				}

				fread($f, 4);
			}
			else if("IDAT" == $type) {
				/* image data chunk */
				$data .= fread($f, $n);
				fread($f, 4);
			}
			else if("IEND" == $type) {
				/* end indicator chunk, so exit parse */
				break;
			}
			else {
				/* just read past any chunk we don't recognise */
				fread($f, $n + 4);
			}
		} while($n);

		if("Indexed" == $colspace && empty($pal)) {
			AppLog::error("indexed PNG with missing PLTE (palette information) chunk: \"$file\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		fclose($f);
		return ["w" => $w, "h" => $h, "cs" => $colspace, "bpc" => $bpc, "f" => "FlateDecode", "parms" => $parms, "pal" => $pal, "trns" => $trns, "data" => $data];
	}

	/**
	 * Read a 32-bit integer from an open stream.
	 *
	 * @param $fh resource The open stream to read.
	 *
	 * @return int The 32-bit integer.
	 */
	protected static function fReadInt($fh): int {
		$a = unpack("Ni", fread($fh, 4));
		return $a["i"];
	}

	/**
	 * Generate the postscript commands to stroke some text on the page.
	 *
	 * @param $s `string` The text to be written.
	 *
	 * The content of the string is escaped and enclosed within ( and ) for use as a literal string in the postscript
	 * program. The text is assumed to be UTF-8 encoded and is converted to UTF-16 encoding (with a byte order mark).
	 *
	 * @return string The postscript command to stroke the text.
	 */
	protected static function _textString(string $s): string {
		return "(" . self::escapeContent(self::utf8ToUtf16be($s)) . ")";
	}

	/**
	 * Escape some content for inclusion in the postscript program.
	 *
	 * @param $s string The content to escape.
	 *
	 * Any instances of \b (, \b ) and \b \\ are escaped with a \\ .
	 *
	 * @return string The escaped content.
	 */
	protected static function escapeContent(string $s): string {
		// Add \ before \, ( and )
		return strtr($s, [")" => "\\)", "(" => "\\(", "\\" => "\\\\"]);
	}


	/**
	 * Generate the postscript to embed a text string in the document.
	 *
	 * @param $s `string` The text to be embedded.
	 *
	 * The content of the string is escaped and enclosed within ( and ) for use
	 * as a literal string in the postscript program. The text is assumed to be
	 * UTF-8 encoded and is converted to UTF-16 encoding (without a byte order
	 * mark).
	 *
	 * @return string The postscript command to embed the text.
	 */
	protected static function escapeText($s) {
		return "(" . self::escapeContent(self::utf8ToUtf16be($s, false)) . ")";
	}

	/**
	 * Dump a stream of bytes to the PDF buffer.
	 *
	 * @param $s `string` The bytes.
	 */
	protected function emitStream(string $s): void {
		$this->outputToBuffer("stream");
		$this->outputToBuffer($s);
		$this->outputToBuffer("endstream");
	}

	/* TODO document. */
	function _emitExtGStates() {
		for($i = 1; $i <= count($this->m_extGStates); $i++) {
			$this->_newObject();
			$this->m_extGStates[$i]["n"] = $this->m_objectNumber;
			$this->outputToBuffer("<</Type /ExtGState");

			foreach($this->m_extGStates[$i]["parms"] as $key => $value) {
				$this->outputToBuffer("/$key $value");
			}

			$this->outputToBuffer(">>");
			$this->outputToBuffer("endobj");
		}
	}

	/**
	 * Emit a line to the current document.
	 *
	 * @param $s `string` is the line to emit.
	 */
	protected function outputToBuffer($s) {
		/*Add a line to the document */
		if($this->m_debugOutFlag) {
			AppLog::message("state is {$this->m_state}", __FILE__, __LINE__, __FUNCTION__);
		}

		if($this->m_state == 2) {
			$this->m_pages[$this->m_pageNumber] .= "$s\n";

			if($this->m_debugOutFlag) {
				AppLog::message("PDF Page is now:\n{$this->m_pages[$this->m_pageNumber]}\n", __FILE__, __LINE__, __FUNCTION__);
			}
		}
		else {
			$this->m_buffer .= "$s\n";

			if($this->m_debugOutFlag) {
				AppLog::message("Buffer is now:\n{$this->m_pages[$this->m_pageNumber]}\n", __FILE__, __LINE__, __FUNCTION__);
			}
		}
	}
}
