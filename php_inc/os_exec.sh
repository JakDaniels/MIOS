#!/bin/sh

export MONO_THREADS_PER_CPU=4096
#export MONO_GC_PARAMS="nursery-size=4m,soft-heap-limit=1024m,evacuation-threshold=90,save-target-ratio=0.1,default-allowance-ratio=1.0"
export MONO_GC_PARAMS="nursery-size=128m"

cd ~/opensim/bin
#exec mono --gc=sgen OpenSim.exe -inimaster="$1" -inifile="$2"
exec mono --desktop OpenSim.exe -inimaster="$1" -inifile="$2"
