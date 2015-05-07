#!/bin/sh

export MONO_THREADS_PER_CPU=2048
export MONO_GC_PARAMS=nursery-size=64m

cd ~/opensim/bin
exec mono OpenSim.exe -inimaster="$1" -inifile="$2"
