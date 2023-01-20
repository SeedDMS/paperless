#!/bin/sh

. ./credentials

curl --silent "${URL}/api/tags/" -H "Authorization: ${AUTH}"
#| jq '.'

