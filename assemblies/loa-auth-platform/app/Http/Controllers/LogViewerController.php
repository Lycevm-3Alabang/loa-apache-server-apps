<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;

class LogViewerController extends Controller
{
    private const DEFAULT_LINES = 500;
    private const MAX_LINES = 5000;

    public function index(): View
    {
        $lines = $this->getLines();
        $logPath = storage_path('logs/laravel.log');
        $fileExists = file_exists($logPath);
        $fileSize = $fileExists ? filesize($logPath) : 0;
        $totalLines = $fileExists ? (int) shell_exec("wc -l < \"$logPath\"") : 0;

        return view('admin.logs.index', [
            'logs' => $fileExists ? $this->readTail($logPath, $lines) : 'Log file not found.',
            'lines' => $lines,
            'totalLines' => $totalLines,
            'fileSize' => $this->formatBytes($fileSize),
            'fileExists' => $fileExists,
        ]);
    }

    public function download(): Response
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            abort(404, 'Log file not found.');
        }

        return response()->download($logPath, 'laravel-'.now()->format('Ymd-His').'.log');
    }

    private function getLines(): int
    {
        $lines = (int) request()->query('lines', self::DEFAULT_LINES);

        return max(1, min($lines, self::MAX_LINES));
    }

    private function readTail(string $path, int $lines): string
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return 'Unable to open log file.';
        }

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        $readSize = min($fileSize, $lines * 2048);
        fseek($handle, $fileSize - $readSize);
        $chunk = fread($handle, $readSize);
        fclose($handle);

        $allLines = explode("\n", $chunk);
        $tail = array_slice($allLines, -$lines);

        return implode("\n", $tail);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
