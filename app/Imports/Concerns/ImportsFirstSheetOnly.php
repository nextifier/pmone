<?php

namespace App\Imports\Concerns;

/**
 * Ensures only the first sheet of the workbook is imported.
 *
 * Without this, Maatwebsite\Excel imports ALL sheets using the same
 * import class, which causes duplicate/invalid data when the workbook
 * contains multiple sheets.
 */
trait ImportsFirstSheetOnly
{
    public function sheets(): array
    {
        return [0 => $this];
    }
}
