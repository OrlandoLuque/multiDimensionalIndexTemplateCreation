#!/bin/bash
echo "Compiling Java sources..."
mkdir -p out
javac -d out src/*.java
if [ $? -eq 0 ]; then
    echo "Build successful. Run with: java -cp out Main"
    echo "  Modes:"
    echo "    java -cp out Main              Standard mode (parallel, grid 16)"
    echo "    java -cp out Main --compare    Benchmark mode (parallel, grids 16+32)"
    echo "    java -cp out Main --verify     Single-threaded verification mode"
else
    echo "Build FAILED."
fi
