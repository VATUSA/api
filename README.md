# VATUSA API
This repository is the API of the VATUSA website, and is the subdomain api.vatusa.net. It works in parallel to vatusa/current.


Middleware notes:
-----
- auth:jwt,web - Session authentication only. Does not include CORS checks.
- Private - Only for internal calls to the API. Includes CORS checks.
- SemiPrivate - Attempts both session authentication and API Key check.
- Public - For readability only, to be phased out.
- APIKey - Legacy for v1, only checks API Key in URL path.
