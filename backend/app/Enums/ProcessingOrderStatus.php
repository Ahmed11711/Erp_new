<?php

namespace App\Enums;

enum ProcessingOrderStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
