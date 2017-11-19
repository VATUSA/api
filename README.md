VATUSA API

Middleware notes:
-----
- auth:jwt,web - only for authenticated sessions, no CORS checks
- Private - CORS check for only vatusa related websites, does not authenticate
- SemiPrivate - Authenticates and if no authentication, does apikey check
- Public - for readability only, to be phased out
- APIKey - legacy for v1, only checks API Key in URL path
