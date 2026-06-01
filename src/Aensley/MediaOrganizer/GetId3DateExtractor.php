<?php

namespace Aensley\MediaOrganizer;

/**
 * Extracts a date from getID3 metadata for a given file.
 *
 * Supports QuickTime/MP4/MOV, Matroska/MKV/WebM, and tagged formats
 * (ID3v2, Vorbis, RIFF, APE, etc.).
 */
class GetId3DateExtractor
{
    public static function isAvailable(): bool
    {
        return class_exists('\getID3');
    }


    /**
     * @param string $file The absolute path to the file to check.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    public function getDate(string $file): string
    {
        $getId3 = new \getID3();
        $info   = $getId3->analyze($file);

        return $this->getQuickTimeDate($info)
            ?: $this->getMatroskaDate($info)
            ?: $this->getTaggedCommentDate($info)
            ?: $this->getYearTagDate($info);
    }


    /**
     * @param array $info getID3 analysis result.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getQuickTimeDate(array $info): string
    {
        if (empty($info['quicktime']['timestamps_unix']['create'])) {
            return '';
        }

        $timestamps = $info['quicktime']['timestamps_unix']['create'];
        // moov.mvhd is the movie-level atom; fall back to first available
        $ts = $timestamps['moov.mvhd'] ?? reset($timestamps);
        return $ts > 0 ? date('Y-m-d', $ts) : '';
    }


    /**
     * @param array $info getID3 analysis result.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getMatroskaDate(array $info): string
    {
        if (empty($info['matroska']['info'])) {
            return '';
        }

        foreach ($info['matroska']['info'] as $segment) {
            if (!empty($segment['DateUTC_unix']) && $segment['DateUTC_unix'] > 0) {
                return date('Y-m-d', $segment['DateUTC_unix']);
            }
        }

        return '';
    }


    /**
     * @param array $info getID3 analysis result.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getTaggedCommentDate(array $info): string
    {
        foreach (['recording_time', 'date', 'creationdate', 'digitizationdate'] as $tagKey) {
            if (!empty($info['comments'][$tagKey][0])) {
                $ts = strtotime($info['comments'][$tagKey][0]);
                if ($ts !== false && $ts > 0) {
                    return date('Y-m-d', $ts);
                }
            }
        }

        return '';
    }


    /**
     * @param array $info getID3 analysis result.
     *
     * @return string The date in YYYY-01-01 format if found. Empty string if not.
     */
    private function getYearTagDate(array $info): string
    {
        if (empty($info['comments']['year'][0])) {
            return '';
        }

        $year = trim($info['comments']['year'][0]);
        return preg_match('/^\d{4}$/', $year) ? $year . '-01-01' : '';
    }
}
