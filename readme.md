<h1>Polygons vs grids intersection calculator batch process</h1>

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
I am developing an evolved version of the quadtrees and octrees algorithm. The algorithm can use the resultset of this process to speed up the culling process.

<h3>Extra features</h3>
* it divides the workload into several smaller tasks
* can be executed several times in parallel. Each instance will do a different set of those smaller tasks
* it detects repeated results, and even if the current result, rotated or flipped, have been generated before
* if interrupted, you can just restart it and it will continue where it was

<h2>Requirements</h2>
A Redis service where we will keep the resultset.

<h3>Recommended Redis settings</h3>
appendonly yes
appendfilename data/dump.rdb.appendonly.aof
appendfsync always

