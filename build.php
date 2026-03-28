<?php
/**
 * Build script: packages the project into a standalone .exe
 * using static-php-cli micro SAPI.
 *
 * Usage: php build.php
 *
 * Creates a self-extracting PHP script with all project files
 * embedded as base64, then combines with micro.sfx to produce
 * a standalone Windows executable.
 */

$projectDir = __DIR__;
$stubFile   = __DIR__ . '/build/stub.php';
$microSfx   = 'C:/Users/Lander/AppData/Local/Temp/php-micro/micro.sfx';
$outputExe  = __DIR__ . '/build/multiDimensionalIndexTemplateCreation.exe';

@mkdir(__DIR__ . '/build', 0755, true);
if (file_exists($outputExe)) unlink($outputExe);

echo "=== Building multiDimensionalIndexTemplateCreation.exe ===\n\n";

// --- Step 1: Collect all project files ---
echo "[1/3] Collecting project files...\n";

$files = [];

// PHP source files
$phpFiles = [
    'runTask.php',
    'libs/polygon.php',
    'libs/vertex.php',
    'libs/intersector.php',
    'libs/matrix-utils.php',
    'libs/Templates.php',
    'libs/Task.php',
    'libs/Redis.lib.php',
    'libs/polygon-draw.php',
    'libs/database.php',
    'tasksList/tasksToDo.php',
];

foreach ($phpFiles as $file) {
    $fullPath = $projectDir . '/' . $file;
    if (file_exists($fullPath)) {
        $files[$file] = file_get_contents($fullPath);
        echo "  + $file\n";
    } else {
        echo "  ! MISSING: $file\n";
    }
}

// Lua scripts
foreach (glob($projectDir . '/luaRedis/*.lua') as $luaFile) {
    $relative = 'luaRedis/' . basename($luaFile);
    $files[$relative] = file_get_contents($luaFile);
    echo "  + $relative\n";
}

// Vendor directory
$vendorDir = $projectDir . '/vendor';
if (is_dir($vendorDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $count = 0;
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relative = 'vendor/' . str_replace('\\', '/', $iterator->getSubPathName());
            $files[$relative] = file_get_contents($item->getPathname());
            $count++;
        }
    }
    echo "  + vendor/ ($count files)\n";
} else {
    echo "  ! WARNING: vendor/ not found. Run 'composer install' first.\n";
}

$totalFiles = count($files);
$totalSize = array_sum(array_map('strlen', $files));
echo "\n  Total: $totalFiles files (" . round($totalSize / 1024 / 1024, 2) . " MB)\n\n";

// --- Step 2: Create self-extracting PHP script ---
echo "[2/3] Creating self-extracting script...\n";

// Compress and encode the file archive
$archive = serialize($files);
$compressed = gzdeflate($archive, 9);
$encoded = base64_encode($compressed);

$stub = '<?php' . "\n";
$stub .= <<<'HEADER'
/**
 * multiDimensionalIndexTemplateCreation — Standalone executable
 * Self-extracting archive: extracts project files and runs runTask.php
 *
 * Author: Orlando Jose Luque Moraira
 */

echo "=============================================================================\n";
echo " multiDimensionalIndexTemplateCreation\n";
echo " Polygons vs grids intersection calculator batch process\n";
echo " Author: Orlando Jose Luque Moraira\n";
echo "=============================================================================\n\n";

$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mdic_' . md5(__FILE__ . filemtime(PHP_BINARY));

if (!is_file($extractDir . DIRECTORY_SEPARATOR . 'runTask.php')) {
    echo "Extracting project files...\n";

    $encoded = '
HEADER;

$stub .= $encoded;
$stub .= "';\n";

$stub .= <<<'FOOTER'
    $archive = unserialize(gzinflate(base64_decode($encoded)));

    foreach ($archive as $path => $content) {
        $fullPath = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
    }

    echo "  " . count($archive) . " files extracted.\n\n";
}

// Look for .env: current working directory first, then next to the .exe
$cwd = getcwd();
$exeDir = dirname(PHP_BINARY);
$envDest = $extractDir . DIRECTORY_SEPARATOR . '.env';

if (is_file($cwd . DIRECTORY_SEPARATOR . '.env')) {
    copy($cwd . DIRECTORY_SEPARATOR . '.env', $envDest);
} elseif (is_file($exeDir . DIRECTORY_SEPARATOR . '.env')) {
    copy($exeDir . DIRECTORY_SEPARATOR . '.env', $envDest);
} elseif (!is_file($envDest)) {
    echo "WARNING: No .env file found.\n";
    echo "         Place a .env file next to the .exe with REDIS_HOST and REDIS_PORT.\n\n";
}

// Copy config.json if present (next to .exe or in CWD)
$configDest = $extractDir . DIRECTORY_SEPARATOR . 'config.json';
if (is_file($cwd . DIRECTORY_SEPARATOR . 'config.json')) {
    copy($cwd . DIRECTORY_SEPARATOR . 'config.json', $configDest);
} elseif (is_file($exeDir . DIRECTORY_SEPARATOR . 'config.json')) {
    copy($exeDir . DIRECTORY_SEPARATOR . 'config.json', $configDest);
}

// Save original working directory for output files (GIFs, etc.)
putenv('MDIC_OUTPUT_DIR=' . $cwd);

chdir($extractDir);

// Create output directory next to the .exe, not in the temp dir
@mkdir($cwd . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generated', 0755, true);

require $extractDir . DIRECTORY_SEPARATOR . 'runTask.php';
FOOTER;

file_put_contents($stubFile, $stub);
$stubSize = round(filesize($stubFile) / 1024 / 1024, 2);
echo "[OK] Self-extracting script: {$stubSize} MB\n\n";

// --- Step 3: Combine micro.sfx + stub = .exe ---
echo "[3/3] Combining into .exe...\n";

if (!file_exists($microSfx)) {
    echo "[ERROR] micro.sfx not found at $microSfx\n";
    exit(1);
}

$sfxContent = file_get_contents($microSfx);
$stubContent = file_get_contents($stubFile);
file_put_contents($outputExe, $sfxContent . $stubContent);

// Cleanup intermediate file
unlink($stubFile);

$exeSize = round(filesize($outputExe) / 1024 / 1024, 2);
echo "\n=== BUILD COMPLETE ===\n";
echo "Output: $outputExe ({$exeSize} MB)\n";
echo "\nUsage:\n";
echo "  1. Place a .env file next to the .exe with:\n";
echo "     REDIS_HOST=127.0.0.1\n";
echo "     REDIS_PORT=6379\n";
echo "  2. Run: multiDimensionalIndexTemplateCreation.exe\n";
