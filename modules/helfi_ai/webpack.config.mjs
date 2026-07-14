import path from 'path';
import fs from 'fs';
import webpack from 'webpack';
import TerserPlugin from 'terser-webpack-plugin';
import { fileURLToPath } from 'url';

// Convert `import.meta.url` to `__dirname` equivalent.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function getDirectories(srcpath) {
  return fs
    .readdirSync(srcpath)
    .filter((item) => fs.statSync(path.join(srcpath, item)).isDirectory());
}

const webpackConfigs = [];

// Loop through every subdirectory in ckeditor5_plugins, each a different
// plugin, and build each one into ./assets/js/build.
getDirectories('./assets/js/ckeditor5_plugins').forEach((dir) => {
  webpackConfigs.push({
    mode: 'production',
    optimization: {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            ecma: 2020,
            mangle: {
              reserved: ['Drupal', 'drupal', 'drupalSettings'],
            },
            format: {
              comments: false,
            },
          },
          test: /\.js(\?.*)?$/i,
          extractComments: false,
        }),
      ],
      moduleIds: 'named',
    },
    entry: {
      path: path.resolve(__dirname, 'assets/js/ckeditor5_plugins', dir, 'src/index.js'),
    },
    output: {
      path: path.resolve(__dirname, './assets/js/build'),
      filename: `${dir}.js`,
      library: ['CKEditor5', dir],
      libraryTarget: 'umd',
      libraryExport: 'default',
    },
    plugins: [
      new webpack.DllReferencePlugin({
        manifest: JSON.parse(
          fs.readFileSync(path.resolve(__dirname, './node_modules/ckeditor5/build/ckeditor5-dll.manifest.json'), 'utf8')
        ),
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll',
      }),
    ],
    module: {
      rules: [{ test: /\.svg$/, use: 'raw-loader' }],
    },
  });
});

export default webpackConfigs;
