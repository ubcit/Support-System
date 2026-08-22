<?php

namespace Modules\Communication\Enums;

enum MessageStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Processing => 'Processing',
            self::Processed => 'Processed',
            self::Failed => 'Failed',
        };
    }
}
