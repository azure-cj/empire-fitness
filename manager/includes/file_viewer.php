<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// Get the file name from query parameter
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if (!$filename) {
    http_response_code(400);
    if ($debug) {
        echo "Error: No file specified";
    }
    die('No file specified');
}

if ($debug) {
    echo "<!-- Searching for file: " . htmlspecialchars($filename) . " -->\n";
}

// Define possible file locations to search
$uploadDirs = [
    'C:\xampp\htdocs\pbl_project\uploads\payment_proofs',
    'C:\xampp\htdocs\empirefitness\uploads\payment_proofs',
];

$filePath = null;
$searchLog = [];

// First try exact filename match
foreach ($uploadDirs as $dir) {
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    $searchLog[] = "Checking exact: $path - " . (file_exists($path) ? "FOUND" : "not found");
    
    if (file_exists($path)) {
        $filePath = $path;
        break;
    }
}

// If not found, try searching for files with partial name match
if (!$filePath) {
    // Extract parts from filename
    $fileBaseName = pathinfo($filename, PATHINFO_FILENAME);
    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
    
    // Try to extract timestamp from database filename (format: proof_TIMESTAMP_HASH or similar)
    // Looking for patterns like "proof_1764399985_" 
    $timestamp = null;
    if (preg_match('/proof_(\d{10})_?/i', $fileBaseName, $matches)) {
        $timestamp = $matches[1];
        $searchLog[] = "Extracted timestamp from database name: $timestamp";
    }
    
    $searchLog[] = "Partial search - basename: $fileBaseName, ext: $fileExt";
    
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            $searchLog[] = "Directory not accessible: $dir";
            continue;
        }
        
        $files = @scandir($dir);
        if ($files === false) {
            $searchLog[] = "Cannot scan directory: $dir";
            continue;
        }
        
        $searchLog[] = "Scanning $dir - found " . (count($files) - 2) . " files";
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $currentExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $currentName = strtolower(pathinfo($file, PATHINFO_FILENAME));
            
            // Match by extension and filename pattern
            if ($currentExt === strtolower($fileExt)) {
                // Check various matching strategies
                $matches = false;
                
                // Strategy 1: Exact match
                if ($currentName === strtolower($fileBaseName)) {
                    $matches = true;
                    $searchLog[] = "Match (exact): $file";
                }
                // Strategy 2: Database name contains current name
                else if (strpos(strtolower($fileBaseName), $currentName) !== false) {
                    $matches = true;
                    $searchLog[] = "Match (contains): $file";
                }
                // Strategy 3: Current name contains database name
                else if (strpos($currentName, strtolower($fileBaseName)) !== false) {
                    $matches = true;
                    $searchLog[] = "Match (contained): $file";
                }
                // Strategy 4: Both contain common timestamp pattern with different formats
                else if (preg_match('/proof_\d+/', strtolower($fileBaseName)) && 
                         preg_match('/payment_\d+_\d+/', $currentName)) {
                    $matches = true;
                    $searchLog[] = "Match (timestamp pattern): $file";
                }
                // Strategy 5: Use extracted timestamp to find matching file
                // This is the KEY strategy for matching proof_TIMESTAMP_HASH to payment_2000_TIMESTAMP
                else if ($timestamp && preg_match('/_' . $timestamp . '/', $currentName)) {
                    $matches = true;
                    $searchLog[] = "Match (timestamp match): $file - found timestamp $timestamp";
                }
                
                if ($matches) {
                    $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                    $searchLog[] = "Using file: " . $filePath;
                    break;
                }
            }
        }
        
        if ($filePath) break;
    }
}

if ($debug) {
    echo "<!-- Debug Log:\n";
    foreach ($searchLog as $log) {
        echo $log . "\n";
    }
    echo "-->\n";
}

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    
    // Log detailed error for debugging
    error_log("File not found: {$filename}");
    error_log("Search log: " . json_encode($searchLog));
    
    if ($debug) {
        echo "<h3>File Not Found</h3>";
        echo "<p>Requested: " . htmlspecialchars($filename) . "</p>";
        echo "<h4>Search Log:</h4>";
        echo "<pre>";
        foreach ($searchLog as $log) {
            echo htmlspecialchars($log) . "\n";
        }
        echo "</pre>";
        die();
    }
    
    die('File not found: ' . htmlspecialchars($filename));
}

// Security check: ensure file is in allowed directory
$realPath = realpath($filePath);
$allowedDirs = [];
foreach ($uploadDirs as $dir) {
    $realDir = realpath($dir);
    if ($realDir) {
        $allowedDirs[] = $realDir;
    }
}

$isAllowed = false;
foreach ($allowedDirs as $dir) {
    if (strpos($realPath, $dir) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    die('Access denied');
}

// Determine MIME type
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Set headers to display or download
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=3600');

// For PDFs and images, display inline
if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
}

readfile($filePath);
exit;
?>
