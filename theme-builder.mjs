import path from 'node:path';
import { buildAll, watchAndBuild } from '@hdbt/theme-builder/builder';
import { buildOutdatedBrowser } from './tools/build-outdated-browser.mjs';

const __dirname = path.resolve();
const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const watchPaths = ['assets/js', 'assets/scss'];
const outDir = path.resolve(__dirname, 'dist');

// React apps.
const reactApps = {
  // 'app-name': './src/js/react/apps/app-nem/index.tsx',
};

// Vanilla JS files.
const jsFiles = {
  autocomplete: 'assets/js/autocomplete.js',
  headingAnchorButtons: 'assets/js/headingAnchorButtons.js',
  reactAndShareSettings: 'assets/js/reactAndShareSettings.js',
  sentryIgnore: 'assets/js/sentryIgnore.js',
};

// SCSS files.
const styles = [
];

// The outdated browser script is ES5 for old browsers, so it is not built with
// esbuild (see assets/js/outdatedBrowser.js). It is built to a temporary file
// first and copied to dist as a static file, because the build empties dist.
const outdatedBrowserTmp = 'node_modules/.cache/outdated-browser/outdatedBrowser.js';
await buildOutdatedBrowser('assets/js/outdatedBrowser.js', path.resolve(__dirname, outdatedBrowserTmp));

// Static files.
const staticFiles = [
  ['node_modules/@drupal/autocomplete/dist/a11y.autocomplete.min.js', `${outDir}/js/a11y-autocomplete.min.js`],
  ['node_modules/tom-select/dist/js/tom-select.complete.js', `${outDir}/js/tom-select.js`],
  [outdatedBrowserTmp, `${outDir}/js/outdatedBrowser.js`],
];

// Builder configurations.
const reactConfig = { reactApps, isDev, outDir };
const jsConfig = { jsFiles, isDev, outDir };
const cssConfig = { styles, isDev, outDir };
const buildArguments = { outDir, staticFiles, jsConfig, reactConfig, cssConfig };

if (isWatch) {
  watchAndBuild({
    buildArguments,
    watchPaths,
  });
} else {
  buildAll(buildArguments).catch((e) => {
    console.error('❌ Build failed:', e);
    process.exit(1);
  });
}
