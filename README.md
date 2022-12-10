SeedDMS paperless extension
============================

Paperless (and Paperless ngx) is another free document management system.
It has a different focus than SeedDMS and misses many of the features
of SeedDMS but there are three Android apps for uploading and browsing,
which can be used for SeedDMS as well, if this extension is installed.

All apps are available at google play and/or f-droid.

paperless-mobile
  This is the youngest but most feature complete app.

paperless
  This one is already a couple of years around but development has
  slowed down a bit. It supports listing and uploading documents.

paperless-share
  This app just adds a share button. Any shared document will
  be uploaded to the server. Once the app was started it is mostly
  invisible.

How it works
-------------

The extension adds additional routes and a so called middleware to
the restapi. The middleware is just for handling the token based
and basic authentication of paperless.

