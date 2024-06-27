# Helfi Robots Header

Handles robots-tag header if `helfi_proxy` module can't be installed.

This module adds `X-Robots-Tag: noindex, nofollow` to all page responses if `DRUPAL_X_ROBOTS_TAG_HEADER` if set to 1.

**NOTE:** Because this duplicates some functionality from `helfi_proxy` module, both modules should not be enabled at the same time.

