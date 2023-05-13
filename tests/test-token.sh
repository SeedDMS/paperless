#!/bin/sh

. ./credentials

USERNAME="admin"
if [ -n "$1" ]; then
	USERNAME=$1
fi
PASSWORD="admin"
if [ -n "$2" ]; then
	PASSWORD=$2
fi
JSON="{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}"
curl --silent -X POST "${URL}/api/token/" \
   -H 'Content-Type: application/json' \
   -d $JSON | jq '.'

