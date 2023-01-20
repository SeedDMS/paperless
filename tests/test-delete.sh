#!/bin/sh

. ./credentials

curl -v -X DELETE "${URL}/api/documents/23761/" -H "Authorization: ${AUTH}"
#| jq '.'

