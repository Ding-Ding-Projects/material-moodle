# Postman collection

`material-moodle.postman_collection.json` lists the HTTP endpoints the Material plugins add: passkey sign-in and
registration, wording packs and meetup calendar files.

1. Import the file into Postman.
2. Set `baseUrl` to your site.
3. Sign in with a browser, then copy the `MoodleSession` cookie into Postman's cookie jar and the value of
   `M.cfg.sesskey` (browser console) into the `sesskey` variable.

The passkey sign-in and register requests need answers from a real authenticator, so they show the body shape only.
