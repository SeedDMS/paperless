#!/bin/sh

. ./credentials

DOCID=22833

curl -L --silent "${URL}/fetch/doc/${DOCID}" -H "Authorization: ${AUTH}" --output ${DOCID}.pdf
