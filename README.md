# SeedDMS paperless extension

Paperless (and Paperless ngx) is another free document management system.
It has a different focus than SeedDMS and misses many of the features
of SeedDMS but there are three Android apps for uploading and browsing,
which can be used for SeedDMS as well, if this extension is installed.

All apps are available at google play and/or f-droid.

paperless-mobile
  This is the youngest but most feature complete app. It has all
  the features of both apps paperless and paperless-share.

paperless
  This one is already a couple of years around but development has
  slowed down a bit. It supports listing and uploading documents.

paperless-share
  This app just adds a share button. Any shared document will
  be uploaded to the server. Once the app was started it is mostly
  invisible.

## How it works

The extension adds additional routes and a so called middleware to
the restapi. The middleware is just for handling the token based
and basic authentication of paperless. Because this middleware applies
to all routes of the rest api, even the existing routes may use
the new authentication methods.

## Restrictions

The concept of paperless is quite different from SeedDMS. Fortunately,
there are hardly any features in paperless which cannot be simulated in SeedDMS.
Nevertheless, there are some notable differences and restrictions.

### Fulltext search

This extension use the fulltext search for most operations. Hence, ensure
to setup fulltext search before using it.

### Authentication

Paperless uses a token based or http basic authentication. Both are
implemented by another slim middleware. There is no session, but the
token is encrypted and stores all the required data to identify the user.
The password to encrypt the token can be set in the configuration, just
like the expiration date of the token. Once the password changes all
token will become invalid and users will have to relogin.

### Archive

Paperless has the notion of an archive, which does not exist in SeedDMS.
There is also no archive serial number.

### Document formats

Paperless stores documents preferably as pdf and has a strong focus on
scanned documents additionally run through ocr. It also does some document
classifying based on the content. This is not supported by SeedDMS.

### Tags

Tags in Paperless are equivalent to categories in SeedDMS with some restrictions.
A category in SeedDMS does not have a color and cannot be marked as inbox tags.
SeedDMS deriveѕ the color from the category name and keeps a list of
categories, which are treated as inbox tags, in the configuration.

## Folder hierachy

Paperless does not have folders. Consequently, SeedDMS disolves its folder
hierarchy and delivers all documents like they were stored in one single folder.
If a document is uploaded, it will be stored either in the root folder or a configured
upload folder. Moving documents at its right place must be done within SeedDMS.
Which documents are actually visible also depends on which root folder is used.
The root folder can be set in the configuration or can be the user's home folder.


