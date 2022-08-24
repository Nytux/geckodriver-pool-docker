#!/bin/bash

PID=`pgrep -f "geckodriver -p $1"`
echo "Killing PID=$PID"

if [ ! -z $PID ]
then
    pkill -9 -P $PID
    kill -9 $PID
fi