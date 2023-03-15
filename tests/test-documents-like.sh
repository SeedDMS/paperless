#!/bin/sh

. ./credentials

curl --silent "${URL}/api/documents/?format=json&more_like_id=19245&page=1&page_size=5&ordering=-added" -H "Authorization: ${AUTH}"
#| jq '.'
