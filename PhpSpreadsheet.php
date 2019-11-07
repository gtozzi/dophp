<?php declare(strict_types=1);

/**
 * @file phpSpreadsheet.php
 * @brief Non-autoload version for PhpSpreadsheet 1.9.0 and utility classes
 */


namespace dophp;


// apt-get install php-psr-simple-cache
require_once 'Psr/SimpleCache/CacheException.php';
require_once 'Psr/SimpleCache/CacheInterface.php';
require_once 'Psr/SimpleCache/InvalidArgumentException.php';

require_once __DIR__ . '/phpspreadsheet/Exception.php';
require_once __DIR__ . '/phpspreadsheet/HashTable.php';
require_once __DIR__ . '/phpspreadsheet/IComparable.php';
require_once __DIR__ . '/phpspreadsheet/IOFactory.php';
require_once __DIR__ . '/phpspreadsheet/Comment.php';
require_once __DIR__ . '/phpspreadsheet/NamedRange.php';
require_once __DIR__ . '/phpspreadsheet/ReferenceHelper.php';
require_once __DIR__ . '/phpspreadsheet/Settings.php';
require_once __DIR__ . '/phpspreadsheet/Spreadsheet.php';

require_once __DIR__ . '/phpspreadsheet/Calculation/Calculation.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Category.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Database.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/DateTime.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Engineering.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Exception.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/ExceptionHandler.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Financial.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/FormulaParser.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/FormulaToken.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Functions.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Logical.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/LookupRef.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/MathTrig.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Statistical.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/TextData.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Engine/CyclicReferenceStack.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Engine/Logger.php';
require_once __DIR__ . '/phpspreadsheet/Calculation/Token/Stack.php';

require_once __DIR__ . '/phpspreadsheet/Cell/IValueBinder.php';
require_once __DIR__ . '/phpspreadsheet/Cell/DefaultValueBinder.php';
require_once __DIR__ . '/phpspreadsheet/Cell/AdvancedValueBinder.php';
require_once __DIR__ . '/phpspreadsheet/Cell/Cell.php';
require_once __DIR__ . '/phpspreadsheet/Cell/Coordinate.php';
require_once __DIR__ . '/phpspreadsheet/Cell/DataType.php';
require_once __DIR__ . '/phpspreadsheet/Cell/DataValidation.php';
require_once __DIR__ . '/phpspreadsheet/Cell/DataValidator.php';
require_once __DIR__ . '/phpspreadsheet/Cell/Hyperlink.php';
require_once __DIR__ . '/phpspreadsheet/Cell/StringValueBinder.php';

require_once __DIR__ . '/phpspreadsheet/Chart/Properties.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Axis.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Chart.php';
require_once __DIR__ . '/phpspreadsheet/Chart/DataSeries.php';
require_once __DIR__ . '/phpspreadsheet/Chart/DataSeriesValues.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Exception.php';
require_once __DIR__ . '/phpspreadsheet/Chart/GridLines.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Layout.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Legend.php';
require_once __DIR__ . '/phpspreadsheet/Chart/PlotArea.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Title.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Renderer/IRenderer.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Renderer/JpGraph.php';
require_once __DIR__ . '/phpspreadsheet/Chart/Renderer/Polyfill.php';

require_once __DIR__ . '/phpspreadsheet/Collection/Cells.php';
require_once __DIR__ . '/phpspreadsheet/Collection/CellsFactory.php';
require_once __DIR__ . '/phpspreadsheet/Collection/Memory.php';

require_once __DIR__ . '/phpspreadsheet/Document/Properties.php';
require_once __DIR__ . '/phpspreadsheet/Document/Security.php';

require_once __DIR__ . '/phpspreadsheet/Helper/Html.php';
require_once __DIR__ . '/phpspreadsheet/Helper/Migrator.php';
require_once __DIR__ . '/phpspreadsheet/Helper/Sample.php';

require_once __DIR__ . '/phpspreadsheet/Reader/IReadFilter.php';
require_once __DIR__ . '/phpspreadsheet/Reader/IReader.php';
require_once __DIR__ . '/phpspreadsheet/Reader/BaseReader.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Csv.php';
require_once __DIR__ . '/phpspreadsheet/Reader/DefaultReadFilter.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Exception.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Gnumeric.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Html.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Ods.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Slk.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xml.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Ods/Properties.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Security/XmlScanner.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Color.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/ErrorCode.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Escher.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/MD5.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/RC4.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Color/BIFF5.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Color/BIFF8.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Color/BuiltIn.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Style/Border.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xls/Style/FillPattern.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/AutoFilter.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/BaseParserClass.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/Chart.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/ColumnAndRowAttributes.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/ConditionalStyles.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/DataValidations.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/Hyperlinks.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/PageSetup.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/Properties.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/SheetViewOptions.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/SheetViews.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/Styles.php';
require_once __DIR__ . '/phpspreadsheet/Reader/Xlsx/Theme.php';

require_once __DIR__ . '/phpspreadsheet/RichText/ITextElement.php';
require_once __DIR__ . '/phpspreadsheet/RichText/TextElement.php';
require_once __DIR__ . '/phpspreadsheet/RichText/RichText.php';
require_once __DIR__ . '/phpspreadsheet/RichText/Run.php';

require_once __DIR__ . '/phpspreadsheet/Shared/CodePage.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Date.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Drawing.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher.php';
require_once __DIR__ . '/phpspreadsheet/Shared/File.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Font.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLE.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLERead.php';
require_once __DIR__ . '/phpspreadsheet/Shared/PasswordHasher.php';
require_once __DIR__ . '/phpspreadsheet/Shared/StringHelper.php';
require_once __DIR__ . '/phpspreadsheet/Shared/TimeZone.php';
require_once __DIR__ . '/phpspreadsheet/Shared/XMLWriter.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Xls.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DgContainer.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DggContainer.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DgContainer/SpgrContainer.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DgContainer/SpgrContainer/SpContainer.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DggContainer/BstoreContainer.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DggContainer/BstoreContainer/BSE.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Escher/DggContainer/BstoreContainer/BSE/Blip.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/CholeskyDecomposition.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/EigenvalueDecomposition.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/LUDecomposition.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/Matrix.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/QRDecomposition.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/SingularValueDecomposition.php';
require_once __DIR__ . '/phpspreadsheet/Shared/JAMA/utils/Maths.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLE/ChainedBlockStream.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLE/PPS.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLE/PPS/File.php';
require_once __DIR__ . '/phpspreadsheet/Shared/OLE/PPS/Root.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/BestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/ExponentialBestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/LinearBestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/LogarithmicBestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/PolynomialBestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/PowerBestFit.php';
require_once __DIR__ . '/phpspreadsheet/Shared/Trend/Trend.php';

require_once __DIR__ . '/phpspreadsheet/Style/Supervisor.php';
require_once __DIR__ . '/phpspreadsheet/Style/Alignment.php';
require_once __DIR__ . '/phpspreadsheet/Style/Border.php';
require_once __DIR__ . '/phpspreadsheet/Style/Borders.php';
require_once __DIR__ . '/phpspreadsheet/Style/Color.php';
require_once __DIR__ . '/phpspreadsheet/Style/Conditional.php';
require_once __DIR__ . '/phpspreadsheet/Style/Fill.php';
require_once __DIR__ . '/phpspreadsheet/Style/Font.php';
require_once __DIR__ . '/phpspreadsheet/Style/NumberFormat.php';
require_once __DIR__ . '/phpspreadsheet/Style/Protection.php';
require_once __DIR__ . '/phpspreadsheet/Style/Style.php';

require_once __DIR__ . '/phpspreadsheet/Worksheet/Dimension.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/AutoFilter.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/BaseDrawing.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/CellIterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Column.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/ColumnCellIterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/ColumnDimension.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/ColumnIterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Drawing.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/HeaderFooter.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/HeaderFooterDrawing.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Iterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/MemoryDrawing.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/PageMargins.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/PageSetup.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Protection.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Row.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/RowCellIterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/RowDimension.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/RowIterator.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/SheetView.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Worksheet.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/AutoFilter/Column.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/AutoFilter/Column/Rule.php';
require_once __DIR__ . '/phpspreadsheet/Worksheet/Drawing/Shadow.php';

require_once __DIR__ . '/phpspreadsheet/Writer/IWriter.php';
require_once __DIR__ . '/phpspreadsheet/Writer/BaseWriter.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Csv.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Exception.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Html.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Pdf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/WriterPart.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Content.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Meta.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/MetaInf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Mimetype.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Settings.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Styles.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Thumbnails.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Ods/Cell/Comment.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Pdf/Dompdf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Pdf/Mpdf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Pdf/Tcpdf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/BIFFwriter.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Escher.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Font.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Parser.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Workbook.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Worksheet.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xls/Xf.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/WriterPart.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Chart.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Comments.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/ContentTypes.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/DocProps.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Drawing.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Rels.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/RelsRibbon.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/RelsVBA.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/StringTable.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Style.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Theme.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Workbook.php';
require_once __DIR__ . '/phpspreadsheet/Writer/Xlsx/Worksheet.php';


/**
 * Utility class for Spreadsheet creation
 */
class Spreadsheet {

	const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

	/**
	 * Creates a spreadsheet from an array of data
	 *
	 * @param $data array: Bidimensional array [ row, [ cell ] ]
	 * @param $heads array: Optional headers rows, Bidimensional array [ row, [ cell ] ]
	 */
	public static function fromArray(array $data, array $heads=[]): \PhpOffice\PhpSpreadsheet\Spreadsheet {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		for($row = 1; $row < count($heads) + count($data); $row++) {
			if( $row <= count($heads) ) {
				$outrow = $heads[$row - 1];
				$header = true;
			} else { 
				$outrow = $data[$row - count($heads) - 1];
				$header = false;
			}

			$col = 0;

			foreach( $outrow as $datacell ) {
				$col++;
				$sheet->setCellValueByColumnAndRow($col, $row, $datacell);

				if( $row == 1 )
					$sheet-> getColumnDimensionByColumn($col)->setAutoSize(true);
			}

			$sheet->getRowDimension($row)->setRowHeight(20);
		}

		return $spreadsheet;
	}

	/**
	 * Returns a string with binary spreadsheet content
	 *
	 * @param $writer \PhpOffice\PhpSpreadsheet\Writer\IWriter
	 * @return string
	 */
	public static function writeToString(\PhpOffice\PhpSpreadsheet\Writer\IWriter $writer): string {

		$tmpfile = tempnam( sys_get_temp_dir(), 'dophp_spreadsheet_' );
		$writer->save($tmpfile);

		$output = file_get_contents($tmpfile);

		unlink($tmpfile);
		return $output;
	}
}
