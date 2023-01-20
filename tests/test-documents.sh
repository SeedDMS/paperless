#!/bin/sh

. ./credentials

curl --silent "${URL}/api/documents/?format=json&query=added:%5B-70%20day%20to%20now%5D&is_tagged=0&page=3&page_size=5&ordering=-added" -H "Authorization: ${AUTH}" | jq '.'
#curl --silent "${URL}/api/documents/?format=json&query=barbaradio&page=3&page_size=5&ordering=-added" -H "Authorization: ${AUTH}" | jq '.'
