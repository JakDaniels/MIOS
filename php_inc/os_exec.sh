#!/bin/sh

export MONO_THREADS_PER_CPU=4096
export MONO_GC_PARAMS=nursery-size=64m

cd ~/opensim/bin
exec mono --server OpenSim.exe -inimaster="$1" -inifile="$2"
renice -n 10 -p $!
