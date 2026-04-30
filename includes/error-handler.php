<?php
declare(strict_types=1);

set_exception_handler(function (\Throwable $e): void {
    _renderError(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Only handle fatal-equivalent errors; let warnings/notices pass through
    $fatal = E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
    if ($errno & $fatal) {
        _renderError("PHP Error ($errno)", $errstr, $errfile, $errline, '');
    }
    return false; // let PHP log non-fatal errors normally
});

register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _renderError('Fatal Error', $e['message'], $e['file'], $e['line'], '');
    }
});

function _renderError(string $type, string $message, string $file, int $line, string $trace): void
{
    $debug = true; // TODO: change to: defined('APP_DEBUG') && APP_DEBUG;

    // Strip the server path prefix so we don't leak absolute paths in production
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    $shortFile = $basePath ? str_replace($basePath, '', $file) : $file;

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // Log every error regardless of display setting
    if (defined('LOG_PATH')) {
        $logLine = sprintf(
            "[%s] [ERROR] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'), $type, $message, $file, $line
        );
        $logDir = LOG_PATH;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logDir . '/app_' . date('Y-m-d') . '.log', $logLine, FILE_APPEND | LOCK_EX);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<title>Application Error</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
    echo '</head><body class="bg-light"><div class="container py-5">';
    echo '<div class="card border-danger shadow-sm">';
    echo '<div class="card-header bg-danger text-white fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Application Error</div>';
    echo '<div class="card-body">';

    if ($debug) {
        echo '<p class="text-danger fw-semibold">' . htmlspecialchars($type) . '</p>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<p class="text-muted small">File: ' . htmlspecialchars($shortFile) . ' &mdash; line ' . $line . '</p>';
        if ($trace) {
            echo '<pre class="bg-dark text-light p-3 rounded small" style="overflow:auto;max-height:400px">';
            echo htmlspecialchars($trace);
            echo '</pre>';
        }
    } else {
        echo '<p class="mb-1">Something went wrong. Please try again or contact support.</p>';
        echo '<p class="text-muted small mb-0">The error has been logged. If this continues, please contact <a href="mailto:' . htmlspecialchars(defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@example.com') . '">' . htmlspecialchars(defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support') . '</a>.</p>';
    }

    echo '</div></div></div></body></html>';
    exit;
}
