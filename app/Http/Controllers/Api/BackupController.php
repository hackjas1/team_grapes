<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    use ApiResponse;

    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0775, true, true);
        }
    }

    /**
     * List all database backup files created in storage/app/backups/.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can manage database backups.', [], 403);
        }

        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0775, true, true);
        }

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) === 'sql') {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize()),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        // Sort latest backups first
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $this->successResponse($backups, 'Database backups retrieved successfully.');
    }

    /**
     * Create a full MySQL database SQL backup file.
     */
    public function create(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can create database backups.', [], 403);
        }

        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0775, true, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "backup_tpc_attendance_{$timestamp}.sql";
        $filePath = "{$this->backupDir}/{$filename}";

        try {
            $this->generateDatabaseDump($filePath);

            if (!File::exists($filePath) || File::size($filePath) === 0) {
                throw new \RuntimeException("Backup file was not created or is empty.");
            }

            $fileSize = File::size($filePath);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'backup_created',
                'description' => "Administrator {$admin->full_name} created database backup file '{$filename}' ({$this->formatBytes($fileSize)}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'filename' => $filename,
                    'size_bytes' => $fileSize,
                ],
            ]);

            return $this->successResponse([
                'filename' => $filename,
                'size_bytes' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'created_at' => date('Y-m-d H:i:s'),
                'download_url' => "/api/backups/" . urlencode($filename) . "/download",
            ], 'Database backup created successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Database backup creation failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Download authorized database backup file.
     */
    public function download(Request $request, string $filename)
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Only administrators can download backups.'], 403);
        }

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filePath = "{$this->backupDir}/{$filename}";

        if (!File::exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'Backup file not found.'], 404);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'backup_downloaded',
            'description' => "Administrator {$admin->full_name} downloaded database backup file '{$filename}'.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * Restore database from backup SQL file on server.
     */
    public function restore(Request $request, string $filename): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can restore database backups.', [], 403);
        }

        $filename = basename($filename);
        $filePath = "{$this->backupDir}/{$filename}";

        if (!File::exists($filePath)) {
            return $this->errorResponse('Backup SQL file not found.', [], 404);
        }

        try {
            $sql = File::get($filePath);
            DB::unprepared($sql);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'backup_restored',
                'description' => "Administrator {$admin->full_name} restored database from backup file '{$filename}'.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([], "Database successfully restored from backup '{$filename}'.");
        } catch (\Exception $e) {
            return $this->errorResponse('Database restore failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Upload and restore database from local computer SQL file.
     */
    public function uploadAndRestore(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can restore database backups.', [], 403);
        }

        $request->validate([
            'backup_file' => 'required|file|max:102400', // max 100MB
        ]);

        $file = $request->file('backup_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'sql') {
            return $this->errorResponse('Only .sql database dump files can be restored.', [], 422);
        }

        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0775, true, true);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $filename = "uploaded_{$cleanName}_" . date('Y_m_d_His') . ".sql";
        $savedPath = "{$this->backupDir}/{$filename}";

        $file->move($this->backupDir, $filename);

        try {
            $sql = File::get($savedPath);
            DB::unprepared($sql);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'backup_restored',
                'description' => "Administrator {$admin->full_name} uploaded and restored database from local file '{$file->getClientOriginalName()}'.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'filename' => $filename,
            ], "Database successfully restored from uploaded file '{$file->getClientOriginalName()}'.");
        } catch (\Exception $e) {
            return $this->errorResponse('Database restore failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Dump MySQL database tables and data into SQL script file stream.
     */
    protected function generateDatabaseDump(string $filePath): void
    {
        @ini_set('max_execution_time', 300);
        @ini_set('memory_limit', '512M');

        $handle = fopen($filePath, 'w');
        if (!$handle) {
            throw new \RuntimeException("Unable to open backup file for writing at '{$filePath}'. Check directory permissions.");
        }

        $dbName = config('database.connections.mysql.database', 'tpc_attendance');

        fwrite($handle, "-- BSIS Event Attendance System SQL Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: {$dbName}\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET time_zone = \"+08:00\";\n\n");

        $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        foreach ($tables as $tableObj) {
            $tableArray = (array) $tableObj;
            $tableName = reset($tableArray);
            if (empty($tableName) || !is_string($tableName)) {
                continue;
            }

            // Create Table SQL
            $createTableStmt = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (empty($createTableStmt)) {
                continue;
            }
            $createSql = $createTableStmt[0]->{'Create Table'} ?? '';

            fwrite($handle, "--\n-- Table structure for table `{$tableName}`\n--\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
            fwrite($handle, $createSql . ";\n\n");

            // Stream Table Data SQL in memory-friendly chunks
            fwrite($handle, "--\n-- Dumping data for table `{$tableName}`\n--\n\n");

            DB::table($tableName)->orderBy(DB::raw('1'))->chunk(250, function ($rows) use ($handle, $tableName) {
                if ($rows->isEmpty()) return;

                $firstRow = (array) $rows->first();
                $cols = array_keys($firstRow);
                $escapedCols = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

                $valueSets = [];
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $escapedVals = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        return DB::connection()->getPdo()->quote($val);
                    }, array_values($rowArray));

                    $valueSets[] = "(" . implode(', ', $escapedVals) . ")";
                }

                if (!empty($valueSets)) {
                    fwrite($handle, "INSERT INTO `{$tableName}` ({$escapedCols}) VALUES\n" . implode(",\n", $valueSets) . ";\n");
                }
            });

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
