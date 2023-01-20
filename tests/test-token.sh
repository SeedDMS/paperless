#!/bin/sh

. ./credentials

curl --silent -X POST "${URL}/api/token/" \
   -H 'Content-Type: application/json' \
   -d '{"username":"admin","password":"admin"}' | jq '.'

