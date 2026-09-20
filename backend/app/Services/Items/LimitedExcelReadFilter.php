<?php

namespace App\Services\Items;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Skip styled-but-empty cells outside a bounded row/column window.
 * Heavily-formatted Arabic price sheets often declare millions of phantom cells.
 */
final class LimitedExcelReadFilter implements IReadFilter
{
    public function __construct(
        private int $maxRow = 25000,
        private int $maxCol = 25,
    ) {
    }

    public function readCell($column, $row, $worksheetName = ''): bool
    {
        if ($row < 1 || $row > $this->maxRow) {
            return false;
        }

        $colIndex = Coordinate::columnIndexFromString((string) $column);

        return $colIndex >= 1 && $colIndex <= $this->maxCol;
    }
}
