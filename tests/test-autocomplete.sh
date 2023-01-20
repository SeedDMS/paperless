#!/bin/sh

. ./credentials

curl --silent "${URL}/api/search/autocomplete/?term=wa" -H "Authorization: ${AUTH}"
#| jq '.'

