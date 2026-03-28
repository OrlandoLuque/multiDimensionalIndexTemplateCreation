<h1>Polygons vs grids intersection calculator batch process</h1>

**Author:** Orlando Jose Luque Moraira

<h2>Introduction</h2>
It grabs lists of:
* as many polygons as you want, defined in a [-1 ... 1] range for simplicity.
* scales to apply to polygons
* angles to apply to those scaled polygons
* list of cells dimensions to work with

The process will
* use several nested loops to generate every case of polygon, scale, angle, and starting position inside of the starting cell
* intersect every [polygon-scale-angle] with each cell dimensions configuration
  * it calculates how many vertical and horizontal cells are needed
  * it determines which cells are inside, which cells are outside and which cells are partially inside the [polygon-scale-angle]
  * the resulting "template" is stored in a Redis database

<h3>Why</h3>
I am developing an evolved version of the quadtrees and octrees algorithm. You can use the resultset of this process to speed up the culling process of any one of those algorithms.

<h3>Extra features</h3>
* it divides the workload into several smaller tasks
* can be executed several times in parallel. Each instance will do a different set of those smaller tasks
* it detects repeated results, and even if the current result, rotated or flipped, has been generated before
* if interrupted, you can restart it. It will continue where it was

<h2>Requirements</h2>

* PHP 8.1+
* A Redis service where we will keep the resultset
* Composer dependencies installed (`composer install`)

<h2>Quick start</h2>

1. Install dependencies: `composer install`
2. Copy `.env.example` to `.env` and set your Redis connection (or create one with `REDIS_HOST` and `REDIS_PORT`)
3. Start Redis (see Redis configuration below)
4. Run: `php runTask.php`

If using the standalone `.exe` build, place `.env` (and optionally `config.json`) in the same directory as the executable.


<h2>Configuration</h2>

All processing parameters can be configured via a `config.json` file. If no `config.json` is found, built-in defaults are used.

The file is searched in this order:
1. Current working directory
2. Project root (next to `runTask.php`)

Copy `config.json.example` to `config.json` and edit as needed.

<h3>config.json reference</h3>

```json
{
    "polygons": {
        "drop":   { "type": "drop",   "width": 0.2, "height": 0.8 },
        "box":    { "type": "box",    "side": 1 },
        "circle": { "type": "circle", "radius": 1 }
    },
    "polygonScales": [128, 64, 1024, 512, 256, 128, 16],
    "gridSupportedSizes": [16, 32, 64, 128, 256, 512],
    "gridVariants": {
        "horizontal": true,
        "vertical": true
    },
    "angleStep": 0.5,
    "redisKeys": {
        "lock":          "lock",
        "templateList":  "templateList",
        "generatedSet":  "generatedSet",
        "templateCount": "templateCount",
        "lastTemplate":  "lastTemplate"
    }
}
```

<h4>polygons</h4>

Each entry defines a polygon to process. The key is the polygon name (used in output and Redis keys).

| Type     | Parameters          | Description                                                |
|----------|---------------------|------------------------------------------------------------|
| `drop`   | `width`, `height`   | Teardrop shape with an arc top. Values in the [-1, 1] range. |
| `box`    | `side`              | Square centered at origin. `side` is the full side length. |
| `circle` | `radius`            | Circle centered at origin.                                 |
| `custom` | `vertices`          | Arbitrary polygon defined by an array of vertices.         |

You can add or remove polygons. Only the listed ones will be processed.

<h5>Custom polygons</h5>

The `custom` type lets you define arbitrary shapes. Each vertex has `x` and `y` coordinates. To create an arc segment instead of a straight line, add an `arc` object with the arc center (`cx`, `cy`) and direction (`d`): `-1` for clockwise, `1` for counter-clockwise.

Example — a triangle:
```json
{
    "type": "custom",
    "vertices": [
        { "x": 0, "y": 1 },
        { "x": -0.866, "y": -0.5 },
        { "x": 0.866, "y": -0.5 }
    ]
}
```

Example — a shape with an arc (the drop polygon defined manually):
```json
{
    "type": "custom",
    "vertices": [
        { "x": -0.2, "y": 0.8, "arc": { "cx": 0, "cy": 0.8, "d": -1 } },
        { "x": 0.2, "y": 0.8 },
        { "x": 0, "y": 0 }
    ]
}
```

The `arc` on a vertex means: *the segment arriving at this vertex from the previous one is an arc*, with center at (`cx`, `cy`) and direction `d`. Vertices without `arc` are connected by straight lines.

<h4>polygonScales</h4>

Array of integer scale factors applied to each polygon. Each polygon is scaled by each value in this list before intersection tests. Larger scales produce higher resolution templates.

<h4>gridSupportedSizes</h4>

Array of cell dimensions (in pixels). For each size `N`, a grid of `N×N` cells is generated.

<h4>gridVariants</h4>

Controls whether rectangular (non-square) grids are also generated:
* `"horizontal": true` — also generates grids of `2N×N` (double width)
* `"vertical": true` — also generates grids of `N×2N` (double height)

Set both to `false` to only process square grids (faster, fewer combinations).

<h4>angleStep</h4>

Step in degrees for rotation angles. The process tests every angle from 0 to 359.x in increments of this value.

* `0.5` (default) → 720 angles (thorough, slow)
* `1.0` → 360 angles (faster)
* `5.0` → 72 angles (quick test)

<h4>fillCheckPolicy</h4>

Controls what happens when the polygon fill validation detects an anomaly (a potential rasterization artifact in `isInside()`). This check runs for each polygon/scale/grid/angle combination before generating templates.

| Value      | Behavior                                                        |
|------------|-----------------------------------------------------------------|
| `"stop"`   | **(default)** Abort the process. Use during development/debugging to investigate the anomaly. |
| `"skip"`   | Log a warning and skip that angle. The templates for that angle are not generated or stored. |
| `"ignore"` | Log a notice and continue processing normally. The templates are generated and stored despite the anomaly. |

<h4>redisKeys</h4>

Prefix/names for Redis keys. Only change these if you need to run multiple independent datasets in the same Redis instance. Each task automatically gets a `T1-`, `T2-`, ... prefix on top of these.

<h3>Partial configuration</h3>

You don't need to specify every key. Any key missing from `config.json` falls back to its default value. For example, to only change the angle step:

```json
{
    "angleStep": 1.0
}
```

<h3>Quick test configuration</h3>

To run a fast test before committing to a full computation:

```json
{
    "polygons": {
        "circle": { "type": "circle", "radius": 1 }
    },
    "polygonScales": [64, 128],
    "gridSupportedSizes": [16, 32],
    "gridVariants": { "horizontal": false, "vertical": false },
    "angleStep": 5.0
}
```

This reduces combinations from millions to a few thousand and completes in seconds.


<h2>Redis configuration</h2>

<h3>Connection</h3>

Set your Redis host and port in the `.env` file:

```
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

<h3>Persistence and data safety</h3>

By default Redis stores everything in memory. If Redis stops, you lose all computed templates. Since this process can run for hours or days, proper persistence configuration is critical.

Redis offers two persistence mechanisms. **Use both together** for maximum safety.

<h4>1. RDB snapshots (point-in-time backups)</h4>

RDB creates compact binary snapshots at intervals. Fast to load on restart, but you lose changes since the last snapshot.

Add to your `redis.conf` (or pass as command-line arguments):

```
save 900 1
save 300 10
save 60 10000
```

This means: snapshot if at least 1 key changed in 900s, or 10 keys in 300s, or 10000 keys in 60s.

For this workload (thousands of writes per minute), a good setting is:

```
save 60 100
```

Snapshot every 60 seconds if at least 100 keys changed.

<h4>2. AOF (Append Only File)</h4>

AOF logs every write operation. Much more durable than RDB alone.

```
appendonly yes
appendfilename "appendonly.aof"
```

The critical setting is `appendfsync`, which controls how often the log is flushed to disk:

| Value        | Durability                | Performance | Data loss on crash          |
|--------------|--------------------------|-------------|-----------------------------|
| `always`     | Every write flushed      | Slowest     | Zero (or near-zero)         |
| `everysec`   | Flushed once per second  | Good        | Up to ~1 second of writes   |
| `no`         | OS decides when to flush | Fastest     | Up to ~30 seconds of writes |

**Recommended for this project:**

```
appendfsync everysec
```

`always` is the safest but significantly slows down writes. Since this process can resume from its last position (via the `lastTemplate` key), losing 1 second of work on a crash is acceptable and easily recovered by re-running.

<h4>Recommended redis.conf for this project</h4>

```
# Persistence: both RDB + AOF
save 60 100
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec

# Memory (adjust to your available RAM)
maxmemory 2gb
maxmemory-policy noeviction

# Performance
tcp-keepalive 300
timeout 0
```

Or start Redis directly with these options:

```
redis-server --save "60 100" --appendonly yes --appendfsync everysec
```

<h3>What happens on failure</h3>

| Scenario                     | With `appendfsync everysec`       | With `appendfsync always`     | Without AOF (RDB only)            |
|------------------------------|-----------------------------------|-------------------------------|-----------------------------------|
| Redis process killed (SIGKILL) | Lose ~1 second of writes        | Lose nothing                  | Lose data since last snapshot     |
| OS crash / power loss        | Lose ~1 second of writes          | Lose nothing*                 | Lose data since last snapshot     |
| Disk full                    | Redis stops accepting writes      | Same                          | Same                              |
| Machine reboot               | Recovers from AOF on restart      | Same                          | Recovers from last RDB snapshot   |

*With `always`, there is a theoretical risk of losing the last write if the OS crashes between Redis calling `fsync()` and the disk controller actually flushing, but this is extremely unlikely.

<h3>Recovery after a crash</h3>

1. Restart Redis — it will automatically load from AOF (preferred) or RDB
2. Re-run the process — it reads the `lastTemplate` key and resumes from where it stopped
3. Each task has its own lock key with a 50-second TTL. If a process dies, its lock expires and a new instance can pick up that task

No manual intervention is needed. Just restart Redis and re-run.

<h3>Backups</h3>

While the process is running, you can safely copy the RDB file (`dump.rdb`) for backup — Redis writes it atomically. For AOF, use `redis-cli BGREWRITEAOF` first to compact it, then copy.

For long-running computations, consider a periodic backup:

```bash
# Copy the RDB snapshot to a backup location
cp /var/lib/redis/dump.rdb /backup/redis-dump-$(date +%Y%m%d-%H%M%S).rdb
```


<h2>Parallel execution</h2>

The process divides work into tasks (one per polygon × scale × grid combination). You can run multiple instances of `runTask.php` (or the `.exe`) simultaneously:

```bash
# Terminal 1
php runTask.php

# Terminal 2
php runTask.php

# Terminal 3
php runTask.php
```

Each instance tries to lock each task via Redis. If a task is already locked by another instance, it skips it and moves to the next. There is no central coordinator — Redis locks handle all coordination.
