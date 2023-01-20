#!/bin/sh

. ./credentials

curl --silent "${URL}/api/documents/23768/metadata/" -H "Authorization: ${AUTH}" | jq '.'

