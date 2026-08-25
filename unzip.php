<?php
/**
 * Standalone Fast Unzipper Utility for InfinityFree / Shared Hosting
 * Created for TPC BSIS Attendance System Deployment
 * 
 * Instructions:
 * 1. Upload your project ZIP file (e.g. project.zip or team_grapes.zip) into your InfinityFree 'htdocs' directory.
 * 2. Upload this 'unzip.php' file into the same 'htdocs' directory.
 * 3. Visit http://your-domain.infinityfreeapp.com/unzip.php in your browser.
 * 4. Click 'Extract ZIP File Now'.
 * 5. Delete this unzip.php file after extraction for security.
 */

@ini_set('max_execution_time', 300);
@ini_set('memory_limit', '256M');
@set_time_limit(300);

$message = '';
$status = '';
$extractedFiles = [];

// Detect all zip files in the current folder
$zipFiles = glob("*.zip");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'extract') {
    $selectedZip = isset($_POST['zip_file']) ? trim($_POST['zip_file']) : '';
    $deleteZipAfter = isset($_POST['delete_zip']) && $_POST['delete_zip'] === '1';
    $deleteScriptAfter = isset($_POST['delete_script']) && $_POST['delete_script'] === '1';

    if (empty($selectedZip) || !file_exists($selectedZip)) {
        $message = "Error: Selected ZIP file does not exist in this folder.";
        $status = "danger";
    } elseif (!extension_loaded('zip')) {
        $message = "Error: The PHP ZipArchive extension is not enabled on this server.";
        $status = "danger";
    } else {
        $zip = new ZipArchive();
        $res = $zip->open($selectedZip);

        if ($res === TRUE) {
            $targetDir = __DIR__;
            $totalFiles = $zip->numFiles;
            
            // Extract files
            $zip->extractTo($targetDir);
            
            for ($i = 0; $i < min($totalFiles, 50); $i++) {
                $extractedFiles[] = $zip->getNameIndex($i);
            }
            
            $zip->close();

            $message = "Success! Successfully extracted {$totalFiles} files from '{$selectedZip}' into " . htmlspecialchars($targetDir);
            $status = "success";

            // Cleanup options
            if ($deleteZipAfter && file_exists($selectedZip)) {
                @unlink($selectedZip);
                $message .= "<br>• Deleted original ZIP file ('{$selectedZip}').";
            }

            if ($deleteScriptAfter) {
                @unlink(__FILE__);
                $message .= "<br>• Self-deleted 'unzip.php' for security.";
            }
        } else {
            $errorCodes = [
                ZipArchive::ER_EXISTS => 'File already exists',
                ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                ZipArchive::ER_INVAL => 'Invalid argument',
                ZipArchive::ER_MEMORY => 'Memory allocation failure',
                ZipArchive::ER_NOENT => 'No such file',
                ZipArchive::ER_NOZIP => 'Not a valid zip archive',
                ZipArchive::ER_OPEN => "Can't open file",
                ZipArchive::ER_READ => 'Read error',
                ZipArchive::ER_SEEK => 'Seek error',
            ];
            $errDetail = isset($errorCodes[$res]) ? $errorCodes[$res] : "Error code {$res}";
            $message = "Extraction Failed: {$errDetail}. Please verify the file is not corrupted.";
            $status = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InfinityFree Fast Unzipper | TPC BSIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #0F172A;
            color: #E2E8F0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .unzip-card {
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .unzip-header {
            background: linear-gradient(135deg, #0B2046 0%, #0070BA 100%);
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #334155;
        }
        .form-select, .form-control {
            background-color: #0F172A;
            border-color: #475569;
            color: #F8FAFC;
        }
        .form-select:focus, .form-control:focus {
            background-color: #0F172A;
            border-color: #38BDF8;
            color: #FFFFFF;
            box-shadow: 0 0 0 0.25rem rgba(56, 189, 248, 0.25);
        }
        .file-badge {
            background: #334155;
            color: #38BDF8;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .extracted-list {
            max-height: 180px;
            overflow-y: auto;
            background: #0F172A;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px;
            font-family: monospace;
            font-size: 0.8rem;
            color: #94A3B8;
        }
    </style>
</head>
<body>

<div class="unzip-card">
    <div class="unzip-header">
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-zip-fill text-warning me-2"></i>InfinityFree Server Unzipper</h4>
        <p class="small text-white-50 mb-0">Extract large project archives directly on your hosting server</p>
    </div>

    <div class="p-4">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $status ?> d-flex align-items-start gap-2 mb-4">
                <i class="bi <?= $status === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-5 flex-shrink-0"></i>
                <div>
                    <div><?= $message ?></div>
                    <?php if (!empty($extractedFiles)): ?>
                        <div class="mt-2 small fw-bold">Extracted files sample (First <?= count($extractedFiles) ?>):</div>
                        <div class="extracted-list mt-1">
                            <?php foreach ($extractedFiles as $f): ?>
                                <div><i class="bi bi-file-earmark me-1 text-info"></i><?= htmlspecialchars($f) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mb-3 p-3 rounded" style="background: #0F172A; border: 1px solid #334155;">
            <div class="small text-muted mb-1"><i class="bi bi-folder2-open text-primary me-1"></i>Current Server Path:</div>
            <div class="file-badge text-truncate"><?= htmlspecialchars(__DIR__) ?></div>
        </div>

        <?php if (empty($zipFiles)): ?>
            <div class="alert alert-warning py-3 text-center">
                <i class="bi bi-exclamation-circle fs-3 d-block mb-2 text-warning"></i>
                <strong>No .ZIP files found in this folder!</strong>
                <p class="small mb-0 mt-1 text-muted">Please upload your project ZIP file (e.g. via FileZilla FTP or InfinityFree Online File Manager) into this same directory, then refresh this page.</p>
            </div>
        <?php else: ?>
            <form method="POST" onsubmit="return confirm('Start extracting the ZIP file now? This will unpack files into this folder.');">
                <input type="hidden" name="action" value="extract">

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-light">Select ZIP Archive to Extract:</label>
                    <select name="zip_file" class="form-select" required>
                        <?php foreach ($zipFiles as $zipFile): ?>
                            <?php $sizeMB = round(filesize($zipFile) / 1048576, 2); ?>
                            <option value="<?= htmlspecialchars($zipFile) ?>" selected>
                                📦 <?= htmlspecialchars($zipFile) ?> (<?= $sizeMB ?> MB)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-light mb-2">Cleanup & Security Options:</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="delete_zip" value="1" id="deleteZip">
                        <label class="form-check-label small text-muted" for="deleteZip">
                            Delete original .ZIP file after successful extraction (Saves disk quota)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="delete_script" value="1" id="deleteScript" checked>
                        <label class="form-check-label small text-muted" for="deleteScript">
                            <strong class="text-warning">Recommended:</strong> Self-delete this <code>unzip.php</code> script after extraction (Prevents unauthorized re-running)
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" style="background: #0070BA; border: none;">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Extract ZIP File Now
                </button>
            </form>
        <?php endif; ?>

        <div class="border-top border-secondary border-opacity-25 mt-4 pt-3 text-center">
            <small class="text-muted" style="font-size: 0.75rem;">
                <i class="bi bi-shield-check text-success me-1"></i>Talibon Polytechnic College &bull; BSIS Deployment Utility
            </small>
        </div>
    </div>
</div>

</body>
</html>
