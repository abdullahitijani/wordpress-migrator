<?php
// Serve last 100 lines of migration.log for web UI log viewer

$logFile = __DIR__ . '/../logs/migration.log';

if (!file_exists($logFile)) {
    http_response_code(404);
    echo "Log file not found.";
    exit;
}

$linesToRead = 100;
$lines = [];
$handle = fopen($logFile, "r");
if ($handle) {
    $pos = -2;
    $currentLine = '';
    fseek($handle, 0, SEEK_END);
    $fileSize = ftell($handle);

    while (count($lines) < $linesToRead && abs($pos) < $fileSize) {
        fseek($handle, $pos, SEEK_END);
        $char = fgetc($handle);
        if ($char === "\n") {
            if ($currentLine !== '') {
                array_unshift($lines, strrev($currentLine));
                $currentLine = '';
            }
        } else {
            $currentLine .= $char;
        }
        $pos--;
    }
    if ($currentLine !== '') {
        array_unshift($lines, strrev($currentLine));
    }
    fclose($handle);
    echo implode("\n", $lines);
} else {
    http_response_code(500);
    echo "Failed to open log file.";
}
?>
