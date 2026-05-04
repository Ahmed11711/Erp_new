<?php

namespace App\Exceptions\Manufacturing;

use InvalidArgumentException;

/**
 * Thrown when a BOM/recipe cannot be changed because production has already
 * completed for this recipe (output is considered locked).
 */
final class ProductAlreadyCompletedException extends InvalidArgumentException
{
}
