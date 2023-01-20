#!/bin/sh

. ./credentials

curl -v -X DELETE "${URL}/api/saved_views/3/" -H "Authorization: ${AUTH}"
#| jq '.'

