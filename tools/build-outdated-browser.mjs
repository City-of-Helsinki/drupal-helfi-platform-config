/**
 * Builds the outdated browser notification script.
 *
 * Generates the user agent regexes and injects them to
 * assets/js/outdatedBrowser.js in place of OUTDATED_BROWSER_REGEXES.
 *
 * The regexes are only as current as the last build of this module:
 * - The "baseline widely available" cutoff is calculated from the build date,
 *   so every build moves it forward.
 * - The browser versions that match it come from the baseline-browser-mapping
 *   package (through browserslist). Keep it up to date
 *   (npm update baseline-browser-mapping) to get the latest browser releases.
 *
 * Two regexes are generated:
 * - "known": every version of the browsers we recognise. A user agent that
 *   does not match this (bots, niche browsers, IE) never gets the warning.
 * - "supported": the browser versions that are considered up to date.
 *
 * A browser is outdated when it matches "known" but not "supported".
 *
 * @see assets/js/outdatedBrowser.js
 */
import fs from 'node:fs/promises';
import path from 'node:path';
import { getUserAgentRegex } from 'browserslist-useragent-regexp';

/**
 * Supported browser versions.
 *
 * "Baseline widely available" means the browser versions that support every
 * web platform feature that has been available in all the core browsers
 * (Chrome, Edge, Firefox, Safari) for at least 30 months.
 *
 * This is a time based rule instead of a version count, which treats the
 * browsers fairly despite their different release cycles.
 */
const supportedBrowsers = ['baseline widely available'];

/**
 * All versions of the browsers covered by the query above.
 */
const knownBrowsers = ['Chrome > 0', 'Edge > 0', 'Firefox > 0', 'Safari > 0', 'iOS > 0'];

const regexOptions = {
  // Browsers newer than the caniuse data used in the build are supported.
  allowHigherVersions: true,
  allowZeroSubversions: true,
};

const PLACEHOLDER = '(OUTDATED_BROWSER_REGEXES)';

/**
 * Builds the outdated browser script with the generated regexes.
 *
 * @param {string} srcFile - Path to the source script.
 * @param {string} outFile - Path to the built script.
 * @return {Promise<void>} - Resolves when the file is written.
 */
export async function buildOutdatedBrowser(srcFile, outFile) {
  const supported = getUserAgentRegex({ browsers: supportedBrowsers, ...regexOptions });
  const known = getUserAgentRegex({ browsers: knownBrowsers, ...regexOptions });
  const source = await fs.readFile(srcFile, 'utf8');

  if (source.split(PLACEHOLDER).length !== 2) {
    throw new Error(`${srcFile} must contain ${PLACEHOLDER} exactly once.`);
  }

  const regexes = `({ supported: ${supported.toString()}, known: ${known.toString()} })`;
  const content = `// This file is built from ${path.basename(srcFile)} by tools/build-outdated-browser.mjs. Do not edit.
// Supported browsers: ${supportedBrowsers.join(', ')}
${source.replace(PLACEHOLDER, () => regexes)}`;

  await fs.mkdir(path.dirname(outFile), { recursive: true });
  await fs.writeFile(outFile, content, 'utf8');
}
