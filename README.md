# SeedDMS paperless extension

Paperless (and Paperless ngx) is another free document management system.
It has a different focus than SeedDMS and misses many of the features
of SeedDMS but there are three Android apps for uploading and browsing,
which can be used for SeedDMS as well, if this extension is installed.

All apps are available at
[Google Play](https://play.google.com/store/apps) and/or [f-droid](https://f-droid.org/).

### paperless-mobile

This is the youngest but most feature complete app. It has all
the features of both apps paperless and paperless-share.
[Google Play](https://play.google.com/store/apps/details?id=eu.bauerj.paperless_app),
[f-droid](https://f-droid.org/packages/eu.bauerj.paperless_app/).

### paperless

This one is already a couple of years around but development has
slowed down a bit. It supports listing and uploading documents.

### paperless-share

This app just adds a share button. Any shared document will
be uploaded to the server. Once the app was started it is mostly
invisible.

## How it works

The extension adds additional routes and a so called middleware to
the restapi. The middleware is just for handling the token based
and basic authentication of paperless. Because this middleware applies
to all routes of the rest api, even the existing routes may use
the new authentication methods.

## Installation

Install this extension like any other extension by uploading the
zip file in the extension manager or copy the content of this
repository into a directory `paperless` in SeedDMS' extension
directory `www/ext`.

Afterwards import one of the database files

* paperless.sql (MySQL)
* paperless-sqlite3.sql (SQLite3)

into your database.

You can test the extension by accessing `http://<your-seeddms>/restapi/index.php/api/`
with your browser. It should return a json object containing various links.

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

### Document Types

There is no direct equivalent to Document Types in SeedDMS, but they can
be easily simulated with a custom attribute. Since version 1.0.1 of this
extension the configuration contains a parameter for an attribute which
must be a value set containing the different document types. Do *not* make
it a multi value attribute, because a document in Paperless may have only
one type. Any more types while you need it or changing the order is possible.

### Correspondents

Just like Document Types, there is a second attribute for correspondents.
It's implemented in SeedDMS just like document types.

## Folder hierachy

Paperless does not have folders. Consequently, SeedDMS disolves its folder
hierarchy and delivers all documents like they were stored in one single folder.
If a document is uploaded, it will be stored either in the root folder or a configured
upload folder. Moving documents at its right place must be done within SeedDMS.
Which documents are actually visible also depends on which root folder is used.
The root folder can be set in the configuration or can be the user's home folder.

Well, paperless seems to have a different concept called 'storage path'. Though
that's not like folder paths in SeedDMS but it's worth a try to map the both.
Since version 1.2.0 of this extension, there storage paths will resolve on folder
paths in SeedDMS.

## Searching for documents

The extension enforces any search to be limited to released documents.

## Searching for similar documents

There is some experimental support for searching for similar documents. This
is done by extracting the most frequent words from the content and using them
to issue a second query with this list of words. Since this list of most frequent
words can be very long it will be reduced. For a word to qualify for the
query

* it must be longer than 4 characters
* have a frequency greater 2

If less than five words meet these conditions, the list will be filled up with
subsequent words from the most frequent word list. If the than executed query
doesn't yield a result the list will be diminished again word by word until the
search succeeds or the query is empty.

## Changing meta data

The app paperless-mobile has support for editing meta data and even the content
of a document. Currently, this extension only supports editing the correspondent
and the tags of a document. All other changes made in paperless mobile we be
disregarded.

