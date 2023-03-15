#!/bin/sh

. ./credentials

curl --silent "${URL}/api/correspondents/" -H "Authorization: ${AUTH}"
#| jq '.'

