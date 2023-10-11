#!/bin/sh

. ./credentials

curl --silent "${URL}/api/storage_paths/" -H "Authorization: ${AUTH}"
#| jq '.'

