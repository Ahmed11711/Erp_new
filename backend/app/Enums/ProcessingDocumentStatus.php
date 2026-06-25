<?php

namespace App\Enums;

enum ProcessingDocumentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
