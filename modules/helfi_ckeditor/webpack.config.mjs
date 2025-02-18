import path from 'path';
import fs from 'fs';
import webpack from 'webpack';
import { styles, builds } from '@ckeditor/ckeditor5-dev-utils';
import TerserPlugin from 'terser-webpack-plugin';
import FriendlyErrorsWebpackPlugin from '@nuxt/friendly-errors-webpack-plugin';
import { CKEditorTranslationsPlugin } from '@ckeditor/ckeditor5-dev-translations';
import glob from 'glob';
import { fileURLToPath } from 'url';

// Convert `import.meta.url` to `__dirname` equivalent
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function getDirectories(srcpath) {
  return fs
    .readdirSync(srcpath)
    .filter((item) => fs.statSync(path.join(srcpath, item)).isDirectory());
}

const webpackConfigs = [];

// Loop through every subdirectory in src, each a different plugin, and build
// each one in ./build.
getDirectories('./assets/js/ckeditor5_plugins').forEach((dir) => {
  const bc = {
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
      ...(dir === 'helfiLanguageSelector'
        ? [
          new CKEditorTranslationsPlugin({
            language: 'en',
            additionalLanguages: 'all',
            packageNamesPattern: /assets[/\\]js[/\\]ckeditor5_plugins[/\\][^/\\]+[/\\]/i,
            sourceFilesPattern: /assets[/\\]js[/\\]ckeditor5_plugins[/\\][^/\\]+[/\\]/i,
          }),
        ]
        : []),
    ],
    module: {
      rules: [{ test: /\.svg$/, use: 'raw-loader' }],
    },
  };

  webpackConfigs.push(bc);
});

// Handle non-CKE5 plugin entry points
const NonCKEPluginEntries = () => {
  let entries = {};
  glob.sync('./assets/js/*.js', { ignore: ['./assets/**/*.min.*'] }).forEach((item) => {
    entries[path.parse(item).name] = item;
  });
  return entries;
};

// Set the base config for non-CKEditor plugins
const NonCKEPluginConfig = {
  entry: NonCKEPluginEntries(),
  output: {
    path: path.resolve(__dirname, 'assets'),
    chunkFilename: 'js/async/[name].chunk.js',
    pathinfo: false,
    filename: 'js/[name].min.js',
    publicPath: '../',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: ['babel-loader'],
        type: 'javascript/auto',
      },
    ],
  },
  resolve: {
    modules: [path.join(__dirname, 'node_modules')],
    extensions: ['.js', '.json'],
  },
  plugins: [new FriendlyErrorsWebpackPlugin()],
  watchOptions: {
    aggregateTimeout: 300,
  },
  stats: 'errors-only',
  performance: {
    hints: false,
    maxEntrypointSize: 512000,
    maxAssetSize: 512000,
  },
  mode: 'production',
  devtool: false,
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          ecma: 2015,
          mangle: {
            reserved: ['Drupal', 'drupalSettings'],
          },
          format: {
            comments: false,
          },
        },
        extractComments: false,
      }),
    ],
  },
};

webpackConfigs.push(NonCKEPluginConfig);

export default webpackConfigs;
