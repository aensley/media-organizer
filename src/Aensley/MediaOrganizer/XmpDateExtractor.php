<?php

namespace Aensley\MediaOrganizer;

use Wikimedia\XMPReader\Reader;

/**
 * Extracts a date from XMP metadata embedded in a file.
 *
 * Supports any file format with an embedded XMP packet (JPEG, PNG, TIFF, PDF, etc.),
 * including files edited in Adobe Lightroom or Photoshop where EXIF may be absent.
 */
class XmpDateExtractor
{
    private const XMP_READ_LIMIT = 1048576;


    /**
     * @param string $file The absolute path to the file to check.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    public function getDate(string $file): string
    {
        $xmp = $this->extractXmp($file);
        if (!$xmp) {
            return '';
        }

        $reader = new Reader();
        if (!$reader->parse($xmp)) {
            return '';
        }

        return $this->getDateFromResults($reader->getResults());
    }


    /**
     * Reads up to XMP_READ_LIMIT bytes from $file and extracts the XMP packet.
     *
     * @param string $file The absolute path to the file to read.
     *
     * @return string The raw XMP packet XML string, or empty string if not found.
     */
    private function extractXmp(string $file): string
    {
        $handle  = fopen($file, 'rb');
        $content = $handle !== false ? fread($handle, self::XMP_READ_LIMIT) : false;
        if ($handle !== false) {
            fclose($handle);
        }

        if (!$content) {
            return '';
        }

        $start = strpos($content, '<?xpacket begin=');
        $end   = $start !== false ? strpos($content, '<?xpacket end=', $start) : false;
        $end   = $end !== false ? strpos($content, '?>', $end) : false;

        return $end !== false ? substr($content, $start, $end + 2 - $start) : '';
    }


    /**
     * Scans XMP result groups for date fields in priority order.
     *
     * Priority: exif:DateTimeOriginal > photoshop:DateCreated > exif:DateTimeDigitized > xmp:CreateDate
     *
     * @param array $results The array returned by Reader::getResults().
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getDateFromResults(array $results): string
    {
        $candidates = [
            $results['xmp-exif']['DateTimeOriginal'] ?? '',
            $results['xmp-deprecated']['DateTimeOriginal'] ?? '',
            $results['xmp-exif']['DateTimeDigitized'] ?? '',
            $results['xmp-general']['DateTimeDigitized'] ?? '',
        ];

        foreach ($candidates as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            // XMP reader returns dates with EXIF-style colon separators (e.g., "2016:07:05 09:54:55")
            $normalized = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $raw);
            $ts = strtotime($normalized);
            if ($ts !== false && $ts > 0) {
                return date('Y-m-d', $ts);
            }
        }

        return '';
    }
}
