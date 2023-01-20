#!/bin/sh

. ./credentials

DOCID=19263

curl -L --silent "${URL}/fetch/thumb/${DOCID}" -H "Authorization: ${AUTH}" --output ${DOCID}.png
