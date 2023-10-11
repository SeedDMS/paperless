#!/bin/sh

. ./credentials

curl --silent -X PATCH "${URL}/api/saved_views/" \
   -H 'Content-Type: application/json' \
	 -H "Authorization: ${AUTH}" \
   -d '{"id":0,"name":"autoview","username":"admin","password":"admin"}'
	# | jq '.'

