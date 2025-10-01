import path from 'path';
import { buildAll, watchAndBuild } from '@hdbt/theme-builder/builder';

const __dirname = path.resolve();
const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const watchPaths = ['src/js', 'src/scss'];
const outDir = path.resolve(__dirname, 'dist');

// React apps.
const reactApps = {
  // 'app-name': './src/js/react/apps/app-nem/index.tsx',
};

// Vanilla JS files.
const jsFiles = {
  autocomplete: 'assets/js/autocomplete.js',
  header_id_injector: 'assets/js/header_id_injector.js',
};

// SCSS files.
const styles = [
];

// Static files.
const staticFiles = [
  ['node_modules/@drupal/autocomplete/dist/a11y.autocomplete.min.js', `${outDir}/js/a11y-autocomplete.min.js`],
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
