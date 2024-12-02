# HDBT Cookie banner

The HDBT Cookie Banner module manages configurations for the HDS cookie consent banner and provides global JavaScript functions in Drupal to trigger and display the banner. The HDS cookie consent JavaScript file is loaded and attached to the HTML head via the `CookieSettings` service and the `hdbt_cookie_banner` JavaScript behavior manages the banner's creation.

## Configuration

Configuration settings can be modified at: `/admin/structure/hdbt-cookie-banner`.

By default, cookie settings and the HDS cookie consent JavaScript file are loaded from the Hel.fi Etusivu instance (local/test/stage/prod), depending on the current environment.

Settings can be overridden by selecting the `Use instance specific cookie settings` option in the configuration form and entering the required information. There is also an option to load the `hds-cookie-consent.min.js` file from a custom location, if necessary.

## Troubleshooting

#### The cookie banner doesn’t appear, and the "Content cannot be displayed" message is shown on each YouTube, Map, or Chart paragraph.
Check the browser console for a message such as `The hds-cookie-consent.min.js script is not loaded. Check the HDBT cookie banner configurations.`. If this message appears, it means the HDS cookie consent JavaScript file is not loaded. Verify that the Hel.fi Etusivu instance is up and running. If your site uses custom cookie settings, check the configurations at `/admin/structure/hdbt-cookie-banner`.

## hds-cookie-consent.min.js changes
In the 4.0.0 version change the theme--dark stopped working. The style string in the current min.js file has been replaced by the older version string in order to keep the theme styles working. Style string can be found from this variable in the file `const o=document.createElement("style");o.textContent="..."`.
