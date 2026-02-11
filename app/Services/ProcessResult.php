<?php

namespace App\Services;

/**
 * Result object for notification processing operations.
 */
class ProcessResult
{
    public function __construct(
        public readonly bool $shouldContinue,
        public readonly ?int $releaseSeconds = null,
        public readonly string $reason = '',
    ) {
    }

    public static function success(string $reason = ''): self
    {
        return new self(true, null, $reason);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, null, $reason);
    }

    public static function continue(): self
    {
        return new self(true);
    }

    public static function release(int $seconds, string $reason = ''): self
    {
        return new self(false, $seconds, $reason);
    }

    public function shouldRelease(): bool
    {
        return $this->releaseSeconds !== null;
    }
}
