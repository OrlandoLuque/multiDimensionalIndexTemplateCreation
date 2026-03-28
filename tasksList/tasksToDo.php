<?php

// --- Defaults -----------------------------------------------------------

$defaultConfig = [
    'polygons' => [
        'drop'   => ['type' => 'drop',   'width' => 1/5, 'height' => 4/5],
        'box'    => ['type' => 'box',    'side' => 1],
        'circle' => ['type' => 'circle', 'radius' => 1],
    ],
    'polygonScales'      => [128, 64, 1024, 512, 256, 128, 16],
    'gridSupportedSizes' => [16, 32, 64, 128, 256, 512],
    'gridVariants'       => ['horizontal' => true, 'vertical' => true],
    'angleStep'          => 0.5,
    'redisKeys'          => [
        'lock'          => 'lock',
        'templateList'  => 'templateList',
        'generatedSet'  => 'generatedSet',
        'templateCount' => 'templateCount',
        'lastTemplate'  => 'lastTemplate',
    ],
];

// --- Load config.json if present ----------------------------------------

$config = $defaultConfig;

$configPaths = [
    getcwd() . '/config.json',                      // CWD (or extracted dir in .exe mode)
    __DIR__ . '/../config.json',                     // project root (development)
];

foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        $json = file_get_contents($configPath);
        $userConfig = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "ERROR: config.json parse error: " . json_last_error_msg() . "\n";
            echo "       File: $configPath\n";
            die();
        }
        // Merge: user values override defaults, key by key
        foreach ($userConfig as $key => $value) {
            $config[$key] = $value;
        }
        echo "Config loaded from: $configPath\n";
        break;
    }
}

// --- Build polygons from config -----------------------------------------

$polys = [];
foreach ($config['polygons'] as $name => $def) {
    switch ($def['type']) {
        case 'drop':
            $polys[$name] = Templates::getDropPolygonWithDimensions($def['width'], $def['height']);
            break;
        case 'box':
            $polys[$name] = Templates::getSquarePolygonWithDimensions($def['side']);
            break;
        case 'circle':
            $polys[$name] = Templates::getCircleWithRadius($def['radius']);
            break;
        case 'custom':
            $poly = new polygon();
            foreach ($def['vertices'] as $v) {
                if (isset($v['arc'])) {
                    $poly->addv($v['x'], $v['y'], $v['arc']['cx'], $v['arc']['cy'], $v['arc']['d']);
                } else {
                    $poly->addv($v['x'], $v['y']);
                }
            }
            $polys[$name] = $poly;
            break;
        default:
            echo "WARNING: Unknown polygon type '{$def['type']}' for '$name', skipping.\n";
    }
}

// --- Build the rest -----------------------------------------------------

$polygonScales     = $config['polygonScales'];
$gridSupportedSizes = $config['gridSupportedSizes'];
$angles            = Templates::getAnglesToTest($config['angleStep']);

$grids = Templates::getGridsFromSupportedSizes(
    $gridSupportedSizes,
    $config['gridVariants']['horizontal'] ?? true,
    $config['gridVariants']['vertical']   ?? true
);

$rk = $config['redisKeys'];
$taskRedisKey          = $rk['lock'];
$templateListRedisKey  = $rk['templateList'];
$generationSetRedisKey = $rk['generatedSet'];
$templateCountRedisKey = $rk['templateCount'];
$lastTemplateRedisKey  = $rk['lastTemplate'];

// --- Return (same interface as before) ----------------------------------

return [$polys, $polygonScales, $gridSupportedSizes, $angles,
    $taskRedisKey, $templateListRedisKey, $generationSetRedisKey,
    $templateCountRedisKey, $lastTemplateRedisKey];
