#!/bin/sh

. ./credentials

curl -L --silent "${URL}/fetch/doc/23311" -H "Authorization: ${AUTH}" --output 23311.pdf
