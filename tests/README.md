# Testing the API

This scripts can be used to test almost all endpoints of the
paperless-ngx API.

In order to use them, create a file `credentials` and define `AUTH` and
`URL` in it. `URL` is the base url of the restapi service. `AUTH` are the
credentials passed in the http header `Authorization`. It can be a basic
authentication, a paperless token or a regular SeedDMS key.

Example:

    URL="http://my.seeddms.org/restapi/index.php"
    AUTH="my seeddms api key"
    #AUTH="Token <paperless token>"
    #AUTH="Basic <credentials>"

