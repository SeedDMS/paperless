#!/bin/sh

. ./credentials

curl --silent "${URL}/api/ui_settings/" -H "Authorization: ${AUTH}" | jq '.'

