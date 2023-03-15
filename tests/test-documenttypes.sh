#!/bin/sh

. ./credentials

curl --silent "${URL}/api/document_types/" -H "Authorization: ${AUTH}"
#| jq '.'

