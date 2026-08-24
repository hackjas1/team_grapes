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
            File::makeDirectory($this->backupDir, 0755, true);
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

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'sql') {
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

        $timestamp = date('Y_m_d_His');
        $filename = "backup_tpc_attendance_{$timestamp}.sql";
        $filePath = "{$this->backupDir}/{$filename}";

        try {
            $sqlContent = $this->generateDatabaseDump();
            File::put($filePath, $sqlContent);

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
     * Restore database from backup SQL file.
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
     * Dump MySQL database tables and data into SQL script string.
     */
    protected function generateDatabaseDump(): string
    {
        $tables = DB::select('SHOW TABLES');
        $dbNameKey = 'Tables_in_' . config('database.connections.mysql.database', 'tpc_attendance');

        $out = "-- BSIS Event Attendance System SQL Backup\n";
        $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $out .= "-- Database: " . config('database.connections.mysql.database') . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->$dbNameKey;

            // Create Table SQL
            $createTableStmt = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
            $createSql = $createTableStmt->{'Create Table'} ?? '';

            $out .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $out .= $createSql . ";\n\n";

            // Table Data SQL
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $cols = array_keys($rowArray);
                    $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
                    $vals = array_values($rowArray);

                    $escapedVals = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        return DB::connection()->getPdo()->quote($val);
                    }, $vals);

                    $out .= "INSERT INTO `{$tableName}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
                }
                $out .= "\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
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
