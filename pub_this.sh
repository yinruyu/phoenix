#!/usr/bin/env bash
TAG=`cat src/version.txt`
echo $TAG ;
cd $HOME/devspace/alien_pub ;
./rocket_pub.sh  --prj phoenix  --tag $TAG --host $*
