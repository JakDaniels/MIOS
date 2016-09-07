#!/bin/sh

export MONO_THREADS_PER_CPU=65536
export MONO_GC_PARAMS=nursery-size=128m
export MONO_ENABLE_COOP=true

cd ~/opensim/bin
exec mono --gc=sgen OpenSim.exe -inimaster="$1" -inifile="$2"
