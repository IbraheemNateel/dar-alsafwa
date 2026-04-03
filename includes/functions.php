<?php
/**
 * دار صفوة - الدوال المساعدة
 * Dar Safwa - Helper Functions
 */

define('AUTH_INCLUDED', true);

function getBaseUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestPath = $requestUri ? (parse_url($requestUri, PHP_URL_PATH) ?: '') : '';

    // Try to compute base URL using filesystem paths (most reliable)
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? ($_SERVER['CONTEXT_DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(__DIR__ . '/..');
    $docRootReal = $docRoot ? realpath($docRoot) : false;

    if ($projectRoot && $docRootReal) {
        $projectRootNorm = str_replace('\\', '/', $projectRoot);
        $docRootNorm = str_replace('\\', '/', $docRootReal);

        if (str_starts_with($projectRootNorm, $docRootNorm)) {
            $relative = substr($projectRootNorm, strlen($docRootNorm));
            $relative = '/' . trim($relative, '/');
            return rtrim($relative, '/') . '/';
        }
    }

    // Fallback: derive from the actual URL path (REQUEST_URI) when available.
    // In some server setups SCRIPT_NAME may not include the project folder.
    $pathForBase = $requestPath ?: $scriptName;

    // Derive from current path, and strip known subfolders
    $dir = str_replace('\\', '/', dirname($pathForBase));
    $dir = rtrim($dir, '/');
    $knownSubfolders = ['dashboard', 'includes', 'assets', 'config', 'cron', 'database', 'vendor'];

    // Strip known subfolders repeatedly (handles /dashboard/... and nested paths)
    while ($dir !== '' && $dir !== '.' && in_array(basename($dir), $knownSubfolders, true)) {
        $dir = str_replace('\\', '/', dirname($dir));
        $dir = rtrim($dir, '/');
    }

    if ($dir === '' || $dir === '.') {
        return '/';
    }

    return $dir . '/';
}

function getSelectedMonthYear(): array {
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);

    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    if ($month < 1 || $month > 12) {
        $month = $currentMonth;
    }
    if ($year < 2020 || $year > ($currentYear + 1)) {
        $year = $currentYear;
    }

    return [$month, $year];
}

function getMonthDateRange(int $month, int $year): array {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    return [$start, $end];
}
