/**
 * @file defines isUrlExternal and parseProtocol functions.
 */

/**
 * Returns true if the URL matches the provided domains list.
 *
 * @param {string} string The URL as string.
 * @param {array} domains The domains list as an array.
 * @return {boolean} Returns either true or false, depending on the existence.
 */
export const isUrlExternal = (string, domains) => {
  let url;
  // Early return on <front>.
  if (string === '/' || string === '<front>') {
    return false;
  }

  const isInternal = (testUrl) => {
    const urlRegex = /https?:\/\/(?:www\.)?[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
    const isMatch = urlRegex.test(testUrl);
    return !isMatch;
  };

  try {
    url = new URL(string);
  } catch (_) {
    url = new URL(`https://${string}`);
    if (isInternal(url)) {
      return false;
    }
  }

  const host = url.hostname;
  return !domains.some(domain => (
    (domain.startsWith('*.') && host.endsWith(domain.slice(2))) ||
    domain === host
  ));
};

/**
 * Retrieves the tel or mailto protocol from given URL.
 *
 * @param {string} url The URL as string.
 * @return {string|boolean} The protocol as a string or false.
 */
export const parseProtocol = url => {
  try {
    const parsedURL = new URL(url);
    return (parsedURL.protocol === 'tel:' || parsedURL.protocol === 'mailto:')
      ? parsedURL.protocol.replace(':', '')
      : false;
  } catch (_) {
    return false; // We only need to return tel or mailto protocols.
  }
};

/**
 * Sanitizes the safe links as they may contain personal data.
 *
 * @param {string} url The URL as string.
 * @return {string} The sanitized URL as a string or original URL.
 */
export const sanitizeSafeLinks = url => {
  if (!url.includes('safelinks.protection.outlook.com')) {
    return url;
  }
  const matches = url.match(/(?<=\?url=).*?(?=&data=)/);
  return (matches) ? decodeURIComponent(matches[0]) : url;
};

/**
 * Add a class to a view.
 *
 * @param {object} view The view.
 * @param {string} className The class name.
 * @return {view|undefined} The view.
 */
export const addViewClass = (view, className) => {
  if (!view || typeof view.class !== 'string') {
    return;
  }

  const existing = view.class.split(' ');
  if (!existing.includes(className)) {
    view.class += ` ${className}`;
  }

  return view;
};
