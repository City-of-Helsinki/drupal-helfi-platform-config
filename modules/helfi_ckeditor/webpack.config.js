const path = require('path');
const fs = require('fs');
const webpack = require('webpack');
const { styles, builds } = require('@ckeditor/ckeditor5-dev-utils');
const TerserPlugin = require('terser-webpack-plugin');
const FriendlyErrorsWebpackPlugin = require('@nuxt/friendly-errors-webpack-plugin');
const { CKEditorTranslationsPlugin } = require( '@ckeditor/ckeditor5-dev-translations' );
const glob = require('glob');

function getDirectories(srcpath) {
  return fs
    .readdirSync(srcpath)
    .filter((item) => fs.statSync(path.join(srcpath, item)).isDirectory());
}

module.exports = [];
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
              reserved:[
                'Drupal',
                'drupal',
                'drupalSettings',
              ],
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
      path: path.resolve(
        __dirname,
        'assets/js/ckeditor5_plugins',
        dir,
        'src/index.js',
      ),
    },
    output: {
      path: path.resolve(__dirname, './assets/js/build'),
      filename: `${dir}.js`,
      library: ['CKEditor5', dir],
      libraryTarget: 'umd',
      libraryExport: 'default',
    },
    plugins: [
      // It is possible to require the ckeditor5-dll.manifest.json used in
      // core/node_modules rather than having to install CKEditor 5 here.
      // However, that requires knowing the location of that file relative to
      // where your module code is located.
      new webpack.DllReferencePlugin({
        manifest: require('./node_modules/ckeditor5/build/ckeditor5-dll.manifest.json'), // eslint-disable-line global-require, import/no-unresolved
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll',
      }),
      () => {
        // Use CKEditor translations only for the helfiLanguageSelector plugin.
        // See README.md why we're only handling helfiLanguageSelector
        return dir === 'helfiLanguageSelector'
          ? new CKEditorTranslationsPlugin( {
            // The main language that will be built into the main bundle.
            language: 'en',

            // Additional languages that will be emitted to the `outputDirectory`.
            additionalLanguages: 'all',

            // Pattern for helfiLanguageSelector plugin.
            packageNamesPattern: /assets[/\\]js[/\\]ckeditor5_plugins[/\\][^/\\]+[/\\]/i,
            sourceFilesPattern: /assets[/\\]js[/\\]ckeditor5_plugins[/\\][^/\\]+[/\\]/i,
            // For more advanced options see
            // https://github.com/ckeditor/ckeditor5-dev/tree/master/packages/ckeditor5-dev-translations.
          } )
          : '';
      },
     ],
    module: {
      rules: [
        { test: /\.svg$/, use: 'raw-loader' }
      ],
    },
  };

  module.exports.push(bc);

  // Handle non CKE5 plugin entry points.
  const NonCKEPluginEntries = () => {
    let entries = {};
    glob.sync('./assets/js/*.js', { ignore: ['./assets/**/*.min.*']}).map((item) => {
      entries[path.parse(item).name] = item }
    );
    return entries;
  };

  // Set the base config
  const NonCKEPluginConfig = {
    entry() {
      return NonCKEPluginEntries();
    },
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
      modules: [
        path.join(__dirname, 'node_modules'),
      ],
      extensions: ['.js', '.json'],
    },
    plugins: [
      new FriendlyErrorsWebpackPlugin(),
    ],
    watchOptions: {
      aggregateTimeout: 300,
    },
    // Tell us only about the errors.
    stats: 'errors-only',
    // Suppress performance errors.
    performance: {
      hints: false,
      maxEntrypointSize: 512000,
      maxAssetSize: 512000
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
              reserved:[
                'Drupal',
                'drupalSettings'
              ]
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
  module.exports.push(NonCKEPluginConfig);
});
