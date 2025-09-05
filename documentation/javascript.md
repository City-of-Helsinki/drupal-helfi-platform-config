# Javascript in helfi_platform_config

Helfi platform config supports HDBT theme builder to compile the JS files.

## Requirements

Requirements for developing:
- [NodeJS](https://nodejs.org/en/)
- [NPM](https://npmjs.com/)
- optional [NVM](https://github.com/nvm-sh/nvm)

## Commands

| Command         | Description                                                        |
|-----------------|--------------------------------------------------------------------|
| `nvm use`       | Selects the correct Node version                                   |
| `npm i`         | Install dependencies and link local packages.                      |
| `npm run dev`   | Compile assets for development environment and watch file changes. |
| `npm run build` | Build packages for production. Minify CSS/JS.                      |

Node version defined in `.nvmrc` should be used. Set up the developing environment with `nvm` by running

    nvm use
    npm i

Related files.
- `.nvmrc` : Defines the node version used to compile the theme.
- `package.json and package-lock.json` : Defines the node modules for compiling the theme.
- `theme-builder.mjs` : Configuration file for the theme builder tool that is used to build the theme.

Start SCSS/JS watcher by running

    npm run dev

Build the minified versions of CSS/JS into dist with

    npm run build
