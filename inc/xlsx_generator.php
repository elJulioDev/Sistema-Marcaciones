<?php
/**
 * excel_exporter.php  —  Exportador Excel para PHP 5.6+
 *
 * SIEMPRE genera .xlsx genuino sin advertencias de Excel.
 * No depende de ZipArchive: tiene su propio escritor ZIP en PHP puro.
 *
 * Jerarquía de motores ZIP (se elige automáticamente):
 *   1. ZipArchive (extensión nativa de PHP)   → ideal, más rápido
 *   2. PurePhpZip (solo pack() + crc32())     → sin extensiones, mismo resultado
 *   3. CsvWriter  (último recurso absoluto)   → CSV limpio sin advertencias
 *
 * USO:
 *   $exp = ExcelExporter::create();
 *   $exp->setColWidth(0, 32);
 *   $exp->addRow([ ['value' => 'Texto', 'style' => ExcelExporter::STYLE_FALTA] ]);
 *   header('Content-Type: '        . $exp->getContentType());
 *   header('Content-Disposition: attachment; filename="reporte' . $exp->getExtension() . '"');
 *   echo $exp->generate();
 *
 * ESTILOS DISPONIBLES:
 *   STYLE_DEFAULT     → Blanco, texto normal
 *   STYLE_HEADER      → Azul oscuro #1E3A8A, blanco negrita, centrado
 *   STYLE_INFO        → Gris claro #F1F5F9, negrita
 *   STYLE_OK          → Verde claro #D1FAE5, texto verde oscuro #065F46, centrado
 *   STYLE_FALTA       → Rojo claro #FEE2E2, texto rojo oscuro #991B1B, negrita, centrado
 *   STYLE_FUTURO      → Gris muy claro #F9FAFB, texto gris #9CA3AF, centrado
 *   STYLE_TOTALES     → Ámbar claro #FEF3C7, texto ámbar oscuro #92400E, negrita, centrado
 *   STYLE_DATE_HEADER → Azul claro #DBEAFE, texto azul oscuro #1E40AF, negrita, centrado
 */


// ═════════════════════════════════════════════════════════════════════════════
// ESCRITOR ZIP EN PHP PURO  (no requiere ninguna extensión)
// Implementa el formato ZIP usando pack() y crc32(), ambas funciones del núcleo
// de PHP disponibles en cualquier instalación desde PHP 4.
// ═════════════════════════════════════════════════════════════════════════════
class PurePhpZip
{
    private $localData  = '';   // Encabezados locales + datos de cada archivo
    private $centralDir = '';   // Directorio central
    private $fileCount  = 0;    // Cantidad de archivos agregados
    private $localOffset = 0;   // Offset acumulado para el directorio central

    /**
     * Agrega un archivo al ZIP.
     * @param string $name    Nombre/ruta dentro del ZIP (e.g. 'xl/workbook.xml')
     * @param string $content Contenido binario o texto del archivo
     */
    public function addFile($name, $content)
    {
        $nameBytes  = $name;             // UTF-8
        $nameLen    = strlen($nameBytes);
        $dataLen    = strlen($content);
        $crc        = $this->crc32Unsigned($content);

        // ── Encabezado local (Local File Header) ─────────────────────────────
        // Firma  PK\x03\x04 = 0x04034b50
        $localHeader = pack('V', 0x04034b50)  // Firma
                     . pack('v', 20)           // Versión mínima requerida (2.0)
                     . pack('v', 0)            // Flags
                     . pack('v', 0)            // Método compresión: 0 = STORED
                     . pack('v', 0)            // Hora modificación
                     . pack('v', 0)            // Fecha modificación
                     . pack('V', $crc)         // CRC-32
                     . pack('V', $dataLen)     // Tamaño comprimido
                     . pack('V', $dataLen)     // Tamaño descomprimido
                     . pack('v', $nameLen)     // Longitud nombre
                     . pack('v', 0);           // Longitud campo extra

        $this->localData   .= $localHeader . $nameBytes . $content;

        // ── Entrada en el directorio central (Central Directory) ──────────────
        // Firma PK\x01\x02 = 0x02014b50
        $this->centralDir  .= pack('V', 0x02014b50)  // Firma
                           .  pack('v', 20)           // Versión que lo creó
                           .  pack('v', 20)           // Versión mínima
                           .  pack('v', 0)            // Flags
                           .  pack('v', 0)            // Método compresión
                           .  pack('v', 0)            // Hora
                           .  pack('v', 0)            // Fecha
                           .  pack('V', $crc)         // CRC-32
                           .  pack('V', $dataLen)     // Tamaño comprimido
                           .  pack('V', $dataLen)     // Tamaño descomprimido
                           .  pack('v', $nameLen)     // Longitud nombre
                           .  pack('v', 0)            // Longitud extra
                           .  pack('v', 0)            // Longitud comentario
                           .  pack('v', 0)            // Disco inicio
                           .  pack('v', 0)            // Atributos internos
                           .  pack('V', 0)            // Atributos externos
                           .  pack('V', $this->localOffset) // Offset encabezado local
                           .  $nameBytes;

        $this->localOffset += 30 + $nameLen + $dataLen;
        $this->fileCount++;
    }

    /**
     * Genera el binario completo del archivo ZIP.
     * @return string
     */
    public function build()
    {
        $cdSize   = strlen($this->centralDir);
        $cdOffset = $this->localOffset;

        // ── Fin del directorio central (End of Central Directory) ─────────────
        // Firma PK\x05\x06 = 0x06054b50
        $endRecord = pack('V', 0x06054b50)       // Firma
                   . pack('v', 0)                 // Número de disco
                   . pack('v', 0)                 // Disco con inicio del dir central
                   . pack('v', $this->fileCount)  // Entradas en este disco
                   . pack('v', $this->fileCount)  // Total de entradas
                   . pack('V', $cdSize)           // Tamaño del directorio central
                   . pack('V', $cdOffset)         // Offset del directorio central
                   . pack('v', 0);                // Longitud del comentario

        return $this->localData . $this->centralDir . $endRecord;
    }

    /**
     * CRC-32 sin signo, compatible con PHP 32-bit y 64-bit.
     * En sistemas 32-bit, crc32() puede devolver negativo; corregimos eso.
     */
    private function crc32Unsigned($data)
    {
        $crc = crc32($data);
        // En PHP 32-bit los enteros son de 32 bits con signo.
        // pack('V',...) usa los 32 bits bajos → funciona en 64-bit.
        // En 32-bit hay que asegurarse de que el valor sea no-negativo.
        if (PHP_INT_SIZE === 4 && $crc < 0) {
            // Sumar 2^32 para convertir de complemento a dos a valor positivo
            $crc = $crc + 4294967296;
        }
        return $crc;
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// FÁBRICA
// ═════════════════════════════════════════════════════════════════════════════
class ExcelExporter
{
    const STYLE_DEFAULT     = 0;
    const STYLE_HEADER      = 1;
    const STYLE_INFO        = 2;
    const STYLE_OK          = 3;
    const STYLE_FALTA       = 4;
    const STYLE_FUTURO      = 5;
    const STYLE_TOTALES     = 6;
    const STYLE_DATE_HEADER = 7;

    /**
     * Siempre devuelve un XlsxWriter que usa el mejor motor ZIP disponible.
     * Solo cae a CsvWriter si el servidor es extremadamente restrictivo
     * (sin pack() ni crc32(), lo que prácticamente no ocurre).
     *
     * @return XlsxWriter|CsvWriter
     */
    public static function create()
    {
        // pack() y crc32() son funciones del núcleo de PHP — siempre disponibles.
        // La única razón para caer a CSV sería un PHP compilado con --disable-hash
        // y --disable-pack, algo que nunca ocurre en producción.
        if (function_exists('pack') && function_exists('crc32')) {
            return new XlsxWriter();
        }
        // Caso imposible en la práctica, pero por robustez:
        return new CsvWriter();
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// XLSX WRITER — genera .xlsx genuino usando ZipArchive o PurePhpZip
// ═════════════════════════════════════════════════════════════════════════════
class XlsxWriter
{
    const STYLE_DEFAULT     = 0;
    const STYLE_HEADER      = 1;
    const STYLE_INFO        = 2;
    const STYLE_OK          = 3;
    const STYLE_FALTA       = 4;
    const STYLE_FUTURO      = 5;
    const STYLE_TOTALES     = 6;
    const STYLE_DATE_HEADER = 7;

    private $rows             = array();
    private $colWidths        = array();
    private $rowHeights       = array();
    private $sharedStrings    = array();
    private $sharedStringsMap = array();
    private $mergeCells       = array(); // Rangos de celdas combinadas (A1:Z1, etc.)

    public function addRow(array $cells)
    {
        $rowIndex = count($this->rows) + 1;
        $this->rows[] = $cells;

        // Detectar colspan y registrar el rango de merge para buildSheet()
        $colIndex = 0;
        foreach ($cells as $cell) {
            if (isset($cell['colspan']) && (int)$cell['colspan'] > 1) {
                $colspan  = (int)$cell['colspan'];
                $startRef = self::getColLetter($colIndex) . $rowIndex;
                $endRef   = self::getColLetter($colIndex + $colspan - 1) . $rowIndex;
                $this->mergeCells[] = $startRef . ':' . $endRef;
            }
            $colIndex++;
        }
    }

    public function setColWidth($col, $width) { $this->colWidths[$col] = $width; }
    public function getExtension()            { return '.xlsx'; }
    public function getContentType()
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * Calcula automaticamente el ancho de cada columna segun el contenido mas largo.
     * Las celdas con colspan > 1 se ignoran para no deformar la columna de origen.
     */
    public function autoFitColumns($minWidth = 10.0, $maxWidth = 55.0)
    {
        $boldStyles = array(
            self::STYLE_HEADER,
            self::STYLE_INFO,
            self::STYLE_FALTA,
            self::STYLE_TOTALES,
            self::STYLE_DATE_HEADER,
        );

        $maxChars = array();

        foreach ($this->rows as $row) {
            foreach ($row as $ci => $cell) {
                // Celda combinada: su texto se reparte en varias columnas -> ignorar
                if (isset($cell['colspan']) && (int)$cell['colspan'] > 1) {
                    continue;
                }

                $val   = isset($cell['value']) ? (string)$cell['value'] : '';
                $style = isset($cell['style']) ? (int)$cell['style']   : 0;
                $bold  = in_array($style, $boldStyles);

                $lineMax = 0;
                foreach (explode("\n", $val) as $line) {
                    $len = function_exists('mb_strlen')
                         ? mb_strlen($line, 'UTF-8')
                         : strlen($line);
                    if ($bold) { $len = $len * 1.1; }
                    if ($len > $lineMax) { $lineMax = $len; }
                }

                if (!isset($maxChars[$ci]) || $lineMax > $maxChars[$ci]) {
                    $maxChars[$ci] = $lineMax;
                }
            }
        }

        foreach ($maxChars as $ci => $chars) {
            $width = ($chars * 1.2) + 2.0;
            $this->colWidths[$ci] = round(
                max((float)$minWidth, min((float)$maxWidth, $width)),
                1
            );
        }
    }

    /**
     * Calcula automaticamente el alto de cada fila segun su contenido.
     * Las celdas con colspan se tratan como una sola linea para evitar
     * que el motor sobreestime el wrap sobre una columna estrecha.
     */
    public function autoFitRows($defaultHeight = 18.0)
    {
        $wrapStyles = array(self::STYLE_HEADER, self::STYLE_DATE_HEADER);
        $boldStyles = array(
            self::STYLE_HEADER,
            self::STYLE_INFO,
            self::STYLE_FALTA,
            self::STYLE_TOTALES,
            self::STYLE_DATE_HEADER,
        );

        $ptPerLine = 15.0;
        $padding   = 5.0;

        foreach ($this->rows as $ri => $row) {
            $maxLines = 1;

            foreach ($row as $ci => $cell) {
                $val   = isset($cell['value']) ? (string)$cell['value'] : '';
                $style = isset($cell['style']) ? (int)$cell['style']   : 0;

                if ($val === '') { continue; }

                $explicitLines = substr_count($val, "\n") + 1;
                $wrappedLines  = 1;

                if (in_array($style, $wrapStyles) && isset($this->colWidths[$ci])) {
                    // Celdas combinadas tienen mucho ancho real -> no calcular wrap
                    if (isset($cell['colspan']) && (int)$cell['colspan'] > 1) {
                        $wrappedLines = 1;
                    } else {
                        $colW        = $this->colWidths[$ci];
                        $isBold      = in_array($style, $boldStyles);
                        $charFactor  = $isBold ? 0.88 : 1.0;
                        $charsPerLine = max(1.0, ($colW - 2.0) * $charFactor);

                        $lineMax = 0;
                        foreach (explode("\n", $val) as $line) {
                            $l = function_exists('mb_strlen')
                               ? mb_strlen($line, 'UTF-8')
                               : strlen($line);
                            if ($l > $lineMax) { $lineMax = $l; }
                        }
                        $wrappedLines = (int)(($lineMax + $charsPerLine - 1) / $charsPerLine);
                        if ($wrappedLines < 1) { $wrappedLines = 1; }
                    }
                }

                $lines = max($explicitLines, $wrappedLines);
                if ($lines > $maxLines) { $maxLines = $lines; }
            }

            $this->rowHeights[$ri] = round(
                max((float)$defaultHeight, $maxLines * $ptPerLine + $padding),
                1
            );
        }
    }

    /**
     * Genera el binario .xlsx.
     * Usa ZipArchive si esta disponible; si no, usa PurePhpZip.
     */
    public function generate()
    {
        $sheetXml = $this->buildSheet();
        $ssXml    = $this->buildSharedStrings();

        $parts = array(
            '[Content_Types].xml'        => $this->buildContentTypes(),
            '_rels/.rels'                => $this->buildRootRels(),
            'xl/workbook.xml'            => $this->buildWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->buildWorkbookRels(),
            'xl/styles.xml'              => $this->buildStyles(),
            'xl/sharedStrings.xml'       => $ssXml,
            'xl/worksheets/sheet1.xml'   => $sheetXml,
        );

        if (class_exists('ZipArchive')) {
            return $this->zipWithZipArchive($parts);
        }
        return $this->zipWithPurePhp($parts);
    }

    // Motores ZIP

    private function zipWithZipArchive(array $parts)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($parts as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    private function zipWithPurePhp(array $parts)
    {
        $zip = new PurePhpZip();
        foreach ($parts as $name => $content) {
            $zip->addFile($name, $content);
        }
        return $zip->build();
    }

    // Helpers

    private function addSharedString($str)
    {
        $str = (string)$str;
        if (!isset($this->sharedStringsMap[$str])) {
            $this->sharedStringsMap[$str] = count($this->sharedStrings);
            $this->sharedStrings[]        = $str;
        }
        return $this->sharedStringsMap[$str];
    }

    /**
     * Convierte un indice de columna (0-based) a letras Excel: 0->A, 25->Z, 26->AA
     * public static para poder llamarla desde addRow() sin instancia previa.
     */
    public static function getColLetter($n)
    {
        $letter = '';
        $n++;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = (int)($n / 26);
        }
        return $letter;
    }

    private function xe($str)
    {
        return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // XML builders

    private function buildContentTypes()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
    }

    private function buildRootRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function buildWorkbook()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
    }

    private function buildWorkbookRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
</Relationships>';
    }

    private function buildStyles()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="8">
    <!-- 0: normal -->
    <font><sz val="10"/><name val="Arial"/></font>
    <!-- 1: blanco negrita (header principal) -->
    <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
    <!-- 2: negrita normal (info empleado) -->
    <font><b/><sz val="10"/><name val="Arial"/></font>
    <!-- 3: verde oscuro (OK) -->
    <font><sz val="10"/><color rgb="FF065F46"/><name val="Arial"/></font>
    <!-- 4: rojo oscuro negrita (FALTO) -->
    <font><b/><sz val="10"/><color rgb="FF991B1B"/><name val="Arial"/></font>
    <!-- 5: gris claro (futuro) -->
    <font><sz val="10"/><color rgb="FF9CA3AF"/><name val="Arial"/></font>
    <!-- 6: ambar oscuro negrita (totales) -->
    <font><b/><sz val="10"/><color rgb="FF92400E"/><name val="Arial"/></font>
    <!-- 7: azul oscuro negrita (cabecera fechas) -->
    <font><b/><sz val="10"/><color rgb="FF1E40AF"/><name val="Arial"/></font>
  </fonts>
  <fills count="10">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <!-- 2: azul oscuro header -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
    <!-- 3: gris claro info -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
    <!-- 4: verde claro OK -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
    <!-- 5: rojo claro FALTO -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
    <!-- 6: gris muy claro futuro -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF9FAFB"/></patternFill></fill>
    <!-- 7: ambar claro totales -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
    <!-- 8: azul claro fechas -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>
    <!-- 9: blanco default -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left   style="thin"><color rgb="FFD1D5DB"/></left>
      <right  style="thin"><color rgb="FFD1D5DB"/></right>
      <top    style="thin"><color rgb="FFD1D5DB"/></top>
      <bottom style="thin"><color rgb="FFD1D5DB"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="8">
    <!-- 0 DEFAULT -->
    <xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyFill="1" applyBorder="1">
      <alignment vertical="center"/>
    </xf>
    <!-- 1 HEADER -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center" wrapText="1"/>
    </xf>
    <!-- 2 INFO -->
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center"/>
    </xf>
    <!-- 3 OK -->
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 4 FALTO -->
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 5 FUTURO -->
    <xf numFmtId="0" fontId="5" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 6 TOTALES -->
    <xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <!-- 7 DATE_HEADER -->
    <xf numFmtId="0" fontId="7" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
  </cellXfs>
</styleSheet>';
    }

    private function buildSharedStrings()
    {
        $c   = count($this->sharedStrings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
             . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' count="' . $c . '" uniqueCount="' . $c . '">' . "\n";
        foreach ($this->sharedStrings as $s) {
            $xml .= '  <si><t xml:space="preserve">' . $this->xe($s) . '</t></si>' . "\n";
        }
        return $xml . '</sst>';
    }

    private function buildSheet()
    {
        // Primer pase: registrar todas las strings compartidas
        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
                $v = isset($cell['value']) ? (string)$cell['value'] : '';
                if ($v !== '') {
                    $this->addSharedString($v);
                }
            }
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
              . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";

        if (!empty($this->colWidths)) {
            $xml .= '  <cols>' . "\n";
            foreach ($this->colWidths as $ci => $w) {
                $cn = $ci + 1;
                $xml .= '    <col min="' . $cn . '" max="' . $cn . '" width="'
                      . number_format((float)$w, 2, '.', '') . '" customWidth="1"/>' . "\n";
            }
            $xml .= '  </cols>' . "\n";
        }

        $xml .= '  <sheetData>' . "\n";
        foreach ($this->rows as $ri => $row) {
            $rn  = $ri + 1;
            $ht  = isset($this->rowHeights[$ri])
                 ? number_format((float)$this->rowHeights[$ri], 1, '.', '')
                 : '18.0';
            $xml .= '    <row r="' . $rn . '" ht="' . $ht . '" customHeight="1">' . "\n";
            foreach ($row as $ci => $cell) {
                $ref = self::getColLetter($ci) . $rn;
                $s   = isset($cell['style']) ? (int)$cell['style'] : 0;
                $v   = isset($cell['value']) ? (string)$cell['value'] : '';

                if ($v === '') {
                    $xml .= '      <c r="' . $ref . '" s="' . $s . '"/>' . "\n";
                } elseif (is_numeric($v) && empty($cell['force_string'])) {
                    $xml .= '      <c r="' . $ref . '" t="n" s="' . $s . '"><v>' . $v . '</v></c>' . "\n";
                } else {
                    $idx = $this->sharedStringsMap[$v];
                    $xml .= '      <c r="' . $ref . '" t="s" s="' . $s . '"><v>' . $idx . '</v></c>' . "\n";
                }
            }
            $xml .= '    </row>' . "\n";
        }
        $xml .= "  </sheetData>\n";

        // mergeCells debe aparecer DESPUES de sheetData segun el estandar OOXML
        if (!empty($this->mergeCells)) {
            $xml .= '  <mergeCells count="' . count($this->mergeCells) . '">' . "\n";
            foreach ($this->mergeCells as $range) {
                $xml .= '    <mergeCell ref="' . $range . '"/>' . "\n";
            }
            $xml .= '  </mergeCells>' . "\n";
        }

        return $xml . "</worksheet>";
    }
}



// ═════════════════════════════════════════════════════════════════════════════
// CSV WRITER — último recurso absoluto (sin colores, pero sin advertencias)
// ═════════════════════════════════════════════════════════════════════════════
class CsvWriter
{
    const STYLE_DEFAULT     = 0;
    const STYLE_HEADER      = 1;
    const STYLE_INFO        = 2;
    const STYLE_OK          = 3;
    const STYLE_FALTA       = 4;
    const STYLE_FUTURO      = 5;
    const STYLE_TOTALES     = 6;
    const STYLE_DATE_HEADER = 7;

    private $rows = array();

    public function addRow(array $cells)      { $this->rows[] = $cells; }
    public function setColWidth($col, $width) { /* no-op */              }
    public function autoFitColumns($min = 10.0, $max = 55.0) { /* no-op en CSV */ }
    public function autoFitRows($defaultH = 18.0)           { /* no-op en CSV */ }
    public function getExtension()            { return '.csv';            }
    public function getContentType()          { return 'text/csv; charset=utf-8'; }

    public function generate()
    {
        $out = fopen('php://temp', 'r+');
        fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel
        foreach ($this->rows as $row) {
            $line = array();
            foreach ($row as $cell) {
                $line[] = isset($cell['value']) ? $cell['value'] : '';
            }
            fputcsv($out, $line, ';');
        }
        rewind($out);
        $data = stream_get_contents($out);
        fclose($out);
        return $data;
    }
}