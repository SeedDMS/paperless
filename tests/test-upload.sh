#!/bin/sh

. ./credentials

curl -v -X POST -F "file=@test-upload.sh" -F "tags=5" "${URL}/api/documents/post_document/" -H "Authorization: ${AUTH}"
#| jq '.'

