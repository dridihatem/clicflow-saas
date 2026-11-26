<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a TTS vendor returns HTTP 429 / quota exhausted.
 * Nova switches to the next provider in the chain and skips this one briefly.
 */
class TtsRateLimitedException extends RuntimeException
{
}
