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
