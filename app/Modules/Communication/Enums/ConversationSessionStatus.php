<?php

namespace Modules\Communication\Enums;

enum ConversationSessionStatus: string
{
    case Collecting = 'collecting';
    case AwaitingVerification = 'awaiting_verification';
    case Open = 'open';
    case NeedsReview = 'needs_review';
    case Done = 'done';
}
