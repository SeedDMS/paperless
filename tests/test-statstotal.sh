#!/bin/sh

. ./credentials

curl --silent "${URL}/api/statstotal/" -H "Authorization: ${AUTH}" | jq '.'

