Changes in version 1.1.3
==========================

- fix color of labels
- return json array with element `non_field_errors` when login with
  token failed
- convert none pdf documents to pdf when downloading them (must be
  configured)

Changes in version 1.1.2
==========================

- date of modification is taken from upload date of version
- add filtering by modification date
- support sorting by correspondent
- show warning on settings page if jwt secret is not set

Changes in version 1.1.1
==========================

- some more logging
- rename class.Paperless.php to class.PaperlessView.php

Changes in version 1.1.0
==========================

- add support for correspondents and document types
- use document id as archive serial number
- return only released documents
- add experimental support for searching for similar docs

Changes in version 1.0.0
==========================

- Initial version
