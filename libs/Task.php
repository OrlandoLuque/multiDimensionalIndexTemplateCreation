<?php
/*
 * Remember that the ID of each template is stored using the template hash as the key.
 */

require_once('libs/polygon.php');

class Task
{
    /**
     * @var Redis
     */
    public Redis $redis;

    /** polygon list to process
     * @var array
     */
    public array $polygons;

    /** polygon scales list to process
     * @var array
     */
    public array $polygonScales;

    /** grids dimensions list to process
     * @var array
     */
    public array $gridsDimensions;

    /** angles list to process
     * @var array
     */
    public array $angles;

    /** Redis key to mark the task as working (set with timeout) or finished (permanent value)
     * @var string
     */
    public string $taskKey;

    /** Redis key to store the list of templates
     * @var string
     */
    public string $templateListKey;

    /** Redis key to store the list of generations and the id of the template
     * @var string
     */
    public string $generationSetKey;

    /** Redis key to store the count of created templates. Used to generate new IDs
     * @var string
     */
    public string $templateCountKey;

    /** Redis key to store the last template generated. Example drop-s128-x16,y16-a60-dx15,dy9
     * @var string
     */
    public string $lastTemplateKey;


    /** Filepath for the script used to store new templates (and annotate reused ones
     * @var string
     */
    public const storeTemplateLUAScriptSourceFile = 'luaRedis/storeTemplate.lua';

    /** Cache of the template process script
     * @var ?string
     */
    public ?string $storeTemplateLUAScript = null;

    /** Filepath for the script used to try to lock a task to the current process.
     * @var string
     */
    public const checkAndLockTastScriptSourceFile = 'luaRedis/checkAndLockTask.lua';

    /** Cache of the task lock script
     * @var ?string
     */
    public ?string $checkAndLockTastScript = null;

    /** Filepath for the script used to keep your current task locked
     * @var string
     */
    public const keepTaskLockedScriptSourceFile = 'luaRedis/keepTaskLocked.lua';

    /** Cache of the keep task locked script
     * @var ?string
     */
    public ?string $keepTaskLockedScript = null;

    /** Filepath for the script used to keep your current task locked
     * @var string
     */
    public const lockTaskAsCompletedScriptSourceFile = 'luaRedis/lockTaskAsCompleted.lua';

    /** Cache of the keep task locked script
     * @var ?string
     */
    public ?string $lockTaskAsCompletedScript = null;

    public function loadScripts(): self
    {
        $this->storeTemplateLUAScript = file_get_contents(self::storeTemplateLUAScriptSourceFile);
        $this->checkAndLockTastScript = file_get_contents(self::checkAndLockTastScriptSourceFile);
        $this->keepTaskLockedScript = file_get_contents(self::keepTaskLockedScriptSourceFile);
        $this->lockTaskAsCompletedScript = file_get_contents(self::lockTaskAsCompletedScriptSourceFile);

        return $this;
    }

    /**
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * @param Redis|null $redis
     * @return Task
     */
    public function setRedis(?Redis $redis): Task
    {
        $this->redis = $redis;
        return $this;
    }

    /**
     * @return array
     */
    public function getPolygons(): array
    {
        return $this->polygons;
    }

    /**
     * @param array $polygons
     * @return Task
     */
    public function setPolygons(array $polygons): Task
    {
        $this->polygons = $polygons;
        return $this;
    }

    /**
     * @return array
     */
    public function getPolygonScales(): array
    {
        return $this->polygonScales;
    }

    /**
     * @param array $polygonScales
     * @return Task
     */
    public function setPolygonScales(array $polygonScales): Task
    {
        $this->polygonScales = $polygonScales;
        return $this;
    }

    /**
     * @return array
     */
    public function getGridsDimensions(): array
    {
        return $this->gridsDimensions;
    }

    /**
     * @param array $gridsDimensions
     * @return Task
     */
    public function setGridsDimensions(array $gridsDimensions): Task
    {
        $this->gridsDimensions = $gridsDimensions;
        return $this;
    }

    /**
     * @return array
     */
    public function getAngles(): array
    {
        return $this->angles;
    }

    /**
     * @param array $angles
     * @return Task
     */
    public function setAngles(array $angles): Task
    {
        $this->angles = $angles;
        return $this;
    }

    /**
     * @return string
     */
    public function getTaskKey(): string
    {
        return $this->taskKey;
    }

    /**
     * @param string $taskKey
     * @return Task
     */
    public function setTaskKey(string $taskKey): Task
    {
        $this->taskKey = $taskKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getTemplateListKey(): string
    {
        return $this->templateListKey;
    }

    /**
     * @param string $templateListKey
     * @return Task
     */
    public function setTemplateListKey(string $templateListKey): Task
    {
        $this->templateListKey = $templateListKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getGenerationSetKey(): string
    {
        return $this->generationSetKey;
    }

    /**
     * @param string $generationSetKey
     * @return Task
     */
    public function setGenerationSetKey(string $generationSetKey): Task
    {
        $this->generationSetKey = $generationSetKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getTemplateCountKey(): string
    {
        return $this->templateCountKey;
    }

    /**
     * @param string $templateCountKey
     * @return Task
     */
    public function setTemplateCountKey(string $templateCountKey): Task
    {
        $this->templateCountKey = $templateCountKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastTemplateKey(): string
    {
        return $this->lastTemplateKey;
    }

    /**
     * @param string $lastTemplateKey
     * @return Task
     */
    public function setLastTemplateKey(string $lastTemplateKey): Task
    {
        $this->lastTemplateKey = $lastTemplateKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreTemplateLUAScript(): string
    {
        if (null === $this->storeTemplateLUAScript) {
            $this->storeTemplateLUAScript = file_get_contents(self::storeTemplateLUAScriptSourceFile);
        }
        return $this->storeTemplateLUAScript;
    }

    /**
     * @param string $storeTemplateLUAScript
     * @return Task
     */
    public function setStoreTemplateLUAScript(string $storeTemplateLUAScript): Task
    {
        $this->storeTemplateLUAScript = $storeTemplateLUAScript;
        return $this;
    }

    /**
     * @return string
     */
    public function getCheckAndLockTastScript(): string
    {
        if (null === $this->checkAndLockTastScript) {
            $this->checkAndLockTastScript = file_get_contents(self::checkAndLockTastScriptSourceFile);
        }
        return $this->checkAndLockTastScript;
    }

    /**
     * @param string $checkAndLockTastScript
     * @return Task
     */
    public function setCheckAndLockTastScript(string $checkAndLockTastScript): Task
    {
        $this->checkAndLockTastScript = $checkAndLockTastScript;
        return $this;
    }

    /**
     * @return string
     */
    public function getKeepTaskLockedScript(): string
    {
        if (null === $this->keepTaskLockedScript) {
            $this->keepTaskLockedScript = file_get_contents(self::keepTaskLockedScriptSourceFile);
        }
        return $this->keepTaskLockedScript;
    }

    /**
     * @param string $keepTaskLockedScript
     * @return Task
     */
    public function setKeepTaskLockedScript(string $keepTaskLockedScript): Task
    {
        $this->keepTaskLockedScript = $keepTaskLockedScript;
        return $this;
    }

    /**
     * @return string
     */
    public function getLockTaskAsCompletedScript(): string
    {
        if (null === $this->lockTaskAsCompletedScript) {
            $this->lockTaskAsCompletedScript
                = file_get_contents(self::lockTaskAsCompletedScriptSourceFile);
        }
        return $this->lockTaskAsCompletedScript;
    }

    /**
     * @param string $lockTaskAsCompletedScript
     * @return Task
     */
    public function setLockTaskAsCompletedScript(string $lockTaskAsCompletedScript): Task
    {
        $this->lockTaskAsCompletedScript = $lockTaskAsCompletedScript;
        return $this;
    }

    /**
     * @param array $polys
     * @param array $polygonScales
     * @param array $gridSupportedSizes
     * @param array $angles
     * @param Redis $redis
     * @param string $taskRedisKey
     * @param string $templateListRedisKey
     * @param string $generationSetRedisKey
     * @param string $templateCountRedisKey
     * @param string $lastTemplateRedisKey
     * @return Task[]
     */
    public static function createTaskSubtasks(
        array  $polys,
        array $polygonScales,
        array  $gridSupportedSizes,
        array $angles,
        Redis  $redis,
        string $taskRedisKey,
        string $templateListRedisKey,
        string $generationSetRedisKey,
        string $templateCountRedisKey,
        string $lastTemplateRedisKey
    ): array {
        /** @var Task[] $tasks */
        $tasks = [];
        $taskCount = 1;

        foreach ($polys as $title => $polygon) {
            foreach ($polygonScales as $polygonScale) {
                foreach ($gridSupportedSizes as $gridPixelDensity) {
                    $taskPrefix = "T$taskCount-";
                    $gridDimensions = Templates::getGridsFromSupportedSizes([$gridPixelDensity]);
                    $task = new Task();
                    $task = $task
                        ->setRedis($redis)
                        ->setPolygons([$polygon])
                        ->setPolygonScales([$polygonScale])
                        ->setGridsDimensions($gridDimensions)
                        ->setAngles($angles)
                        ->setTaskKey($taskPrefix . $taskRedisKey)
                        ->setTemplateListKey($taskPrefix . $templateListRedisKey)
                        ->setGenerationSetKey($taskPrefix . $generationSetRedisKey)
                        ->setTemplateCountKey($templateCountRedisKey)
                        ->setLastTemplateKey($taskPrefix . $lastTemplateRedisKey)
                        ->loadScripts();
                    $tasks[] = $task;
                    $taskCount++;
                }
            }
        }
        return $tasks;
    }
}
