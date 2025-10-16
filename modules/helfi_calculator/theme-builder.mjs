import path from 'path';
import { buildAll, watchAndBuild } from '@hdbt/theme-builder/builder';
import { globSync } from 'glob';

const __dirname = path.resolve();
const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const watchPaths = ['assets/js', 'assets/scss'];
const outDir = path.resolve(__dirname, 'dist');

// Vanilla JS files.
const jsFiles = globSync('./assets/js/**/*.js', {
  ignore: [
    'assets/js/**/tests/**',
  ],
}).reduce((acc, file) => ({
  ...acc, [path.parse(file).name]: file
}), {});

// SCSS files.
const styles = [
];

// Static files.
const staticFiles = [
];

console.log(outDir);

// Builder configurations.
const reactConfig = { reactApps: {}, isDev, outDir };
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
