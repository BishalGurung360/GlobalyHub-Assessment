<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Sent, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    public function canRetry(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
