<?php
namespace App\Services;

/** PDF A4 minimal tanpa dependensi eksternal. Font Helvetica/Helvetica-Bold standar PDF. */
class PdfCanvas
{
    private array $pages = [];
    private string $content = '';
    private int $page = 0;
    private array $jpegImages = []; // {name => [data, width, height]} JPEG dibenamkan dalam PDF

    public function __construct() { $this->newPage(); }
    public function newPage(): void
    {
        if ($this->page > 0) $this->pages[] = $this->content;
        $this->page++;
        $this->content = '';
    }
    public function rgb(float $r, float $g, float $b): void
    {
        $this->content .= sprintf('%.3f %.3f %.3f rg %.3f %.3f %.3f RG', $r, $g, $b, $r, $g, $b)."\n";
    }
    public function text(float $x, float $y, string $value, float $size = 10, bool $bold = false, string $align = 'left'): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($encoded === false) $encoded = preg_replace('/[^\x20-\x7e]/', '?', $value);
        $length = strlen($encoded);
        if ($align === 'right') $x -= $length * $size * ($bold ? .565 : .52);
        if ($align === 'center') $x -= $length * $size * ($bold ? .565 : .52) / 2;
        $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
        $font = $bold ? 'F2' : 'F1';
        $this->content .= sprintf('BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET', $font, $size, $x, $y, $encoded)."\n";
    }
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 1): void
    {
        $this->content .= sprintf('%.2f w %.2f %.2f m %.2f %.2f l S', $width, $x1, $y1, $x2, $y2)."\n";
    }
    public function rectangle(float $x, float $y, float $w, float $h, bool $fill = false): void
    {
        $this->content .= sprintf('%.2f %.2f %.2f %.2f re %s', $x, $y, $w, $h, $fill ? 'f' : 'S')."\n";
    }
    /** QR verifikasi dalam vektor PDF dengan quiet zone, tanpa API eksternal. */
    public function qr(string $url, float $x, float $y, float $width = 88): void
    {
        $matrix = QrCodeMatrix::make($url);
        $n = count($matrix);
        $module = $width / ($n + 8); // quiet zone 4 modul setiap sisi
        $this->rgb(1,1,1);
        $this->rectangle($x, $y, $width, $width, true);
        $this->rgb(.08,.16,.43);
        foreach ($matrix as $r => $row) {
            $start = null;
            for ($c=0; $c<=$n; $c++) {
                $dark = $c<$n && $row[$c];
                if ($dark && $start === null) $start = $c;
                if (!$dark && $start !== null) {
                    $this->rectangle($x+(4+$start)*$module, $y+(4+$n-1-$r)*$module,
                        ($c-$start)*$module, $module, true);
                    $start = null;
                }
            }
        }
    }

    /** Tambahkan logo JPEG tanpa ekstensi GD/Imagick/Dompdf pada server Laravel. */
    public function jpeg(string $path, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($path)) return;
        $metadata = @getimagesize($path);
        if (!$metadata || $metadata[2] !== IMAGETYPE_JPEG) return;
        $key = 'Im'.(array_search($path, array_column($this->jpegImages, 'path')) !== false
            ? (array_search($path, array_column($this->jpegImages, 'path')) + 1)
            : (count($this->jpegImages) + 1));
        if (!isset($this->jpegImages[$key])) {
            $this->jpegImages[$key] = ['path' => $path, 'width' => $metadata[0], 'height' => $metadata[1], 'data' => file_get_contents($path)];
        }
        $this->content .= sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q', $w, $h, $x, $y, $key)."\n";
    }

    public function output(): string
    {
        $pages = [...$this->pages, $this->content];
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $pageIds = [];
        $id = 5;
        $xobjects = [];
        foreach ($this->jpegImages as $name => $img) {
            $objectId = $id++;
            $xobjects[] = '/'.$name.' '.$objectId.' 0 R';
            $data = $img['data'];
            $objects[$objectId] = '<< /Type /XObject /Subtype /Image /Width '.$img['width']
                .' /Height '.$img['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8'
                .' /Filter /DCTDecode /Length '.strlen($data)." >>\nstream\n".$data."\nendstream";
        }
        $imageResources = $xobjects ? ' /XObject << '.implode(' ', $xobjects).' >>' : '';
        foreach ($pages as $content) {
            $pageId = $id++;
            $streamId = $id++;
            $pageIds[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$imageResources.' >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds)).'] /Count '.count($pageIds).' >>';
        ksort($objects);
        $out = "%PDF-1.4\n%"."\xE2\xE3\xCF\xD3"."\n";
        $offsets = [0 => 0];
        foreach ($objects as $n => $content) {
            $offsets[$n] = strlen($out);
            $out .= $n." 0 obj\n".$content."\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            $out .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }
        return $out.'trailer' . "\n" . '<< /Size '.(max(array_keys($objects)) + 1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
}
