<?php

namespace App\Service;

class CSVExportService {
    public function escapeCSV($cell) {
        return !empty($cell)
            ? ('"' . str_replace('"', '""', $cell) . '"')
            : '';
    }
}
