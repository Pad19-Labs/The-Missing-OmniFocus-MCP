<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * Monolog "tap" applied to every channel in config/logging.php. Registering it
 * per channel — rather than hoping callers remember — is what makes the
 * metadata-only policy enforced rather than aspirational.
 */
class ScrubRequestData
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new MetadataOnlyProcessor);
        }
    }
}
