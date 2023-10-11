#!/bin/sh

. ./credentials

curl --silent "${URL}/api/documents/?format=json&page=1&correspondent__isnull=0&page_size=15&ordering=-added" -H "Authorization: ${AUTH}"
#| jq '.'
