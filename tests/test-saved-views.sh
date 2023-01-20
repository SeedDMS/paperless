#!/bin/sh

. ./credentials

curl --silent "${URL}/api/saved_views/" -H "Authorization: ${AUTH}" | jq '.'

