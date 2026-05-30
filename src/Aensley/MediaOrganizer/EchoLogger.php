<?php

namespace Aensley\MediaOrganizer;

use Psr\Log\AbstractLogger;

/**
 * Simple logger that echoes messages to stdout.
 *
 * @package Aensley/MediaOrganizer
 * @author  Andrew Ensley
 */
class EchoLogger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        echo strtoupper($level) . ': ' . $message . "\n";
    }
}
