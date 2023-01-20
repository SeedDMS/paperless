#!/bin/sh

. ./credentials

curl --silent "${URL}/api/" -H "Authorization: ${AUTH}" | jq '.'

