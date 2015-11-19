#!/bin/sh

export MONO_THREADS_PER_CPU=65536
export MONO_GC_PARAMS=nursery-size=128m

cd ~/opensim/bin
exec mono OpenSim.exe -inimaster="$1" -inifile="$2"
