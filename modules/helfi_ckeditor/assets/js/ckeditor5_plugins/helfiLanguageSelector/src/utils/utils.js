/**
 * @file defines stringifyLanguageAttribute and parseLanguageAttribute functions.
 * These functions are based on CKEditor5/language plugin.
 */
import { getLanguageDirection } from 'ckeditor5/src/utils';

/**
 * Returns the language attribute value in a human-readable text format.
 *
 * @param {string} languageCode The language code.
 * @param {string} textDirection The language text direction.
 * @return {string} Returns the human-readable text of lang and dir.
 */
export function stringifyLanguageAttribute(languageCode, textDirection) {
  textDirection = textDirection || getLanguageDirection(languageCode);
  return `${ languageCode }:${ textDirection }`;
}

/**
 * Retrieves language properties converted to attribute value by
 * the stringifyLanguageAttribute function.
 *
 * @param {string} str The attribute value.
 * @return {{textDirection: *, languageCode: *}} The object with properties:
 * * languageCode - The language code in the ISO 639 format.
 * * textDirection - The language text direction.
 */
export function parseLanguageAttribute(str) {
  const [ languageCode, textDirection ] = str.split(':');
  return { languageCode, textDirection };
}

/**
 * Simplify the language code.
 *
 * @param {string} str The attribute value.
 * @return {string} The language code without country code or empty string.
 */
export function simplifyLangCode(str) {
  // Return empty string if the string is not a supported language code.
  // Supported formats: "xx", "xx-YY".
  if (!/^[a-z]{2}(-[a-z]{2})?$/i.test(str)) {
    return '';
  }

  // Return the language code without country code.
  return str.slice(0, 2).toLowerCase();
}
