const Encore = require('@symfony/webpack-encore');
const CopyPlugin = require('copy-webpack-plugin');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/js/app.js')
    .addEntry('dashboard', './assets/js/dashboard.js')
    .addEntry('reference-article-form', './assets/js/pages/reference-article/form.js')
    .addEntry('reference-article-index', './assets/js/pages/reference-article/index.js')
    .addEntry('cart', './assets/js/pages/cart.js')
    .addEntry('settings-index', './assets/js/pages/settings/index.js')
    .addEntry('settings-data-imports', './assets/js/pages/settings/data/imports.js')
    .addEntry('settings-data-inventories-imports', './assets/js/pages/settings/data/inventories-imports.js')
    .addEntry('settings-users-roles-form', './assets/js/pages/settings/users/roles/form.js')
    .addEntry('vehicle', './assets/js/pages/vehicle.js')
    .autoProvidejQuery()

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
    .configureBabel((config)=>{
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })

    // enables Sass/SCSS support
    .enableSassLoader()
    .addPlugin(new CopyPlugin({
        patterns : [
            {
                from: 'node_modules/leaflet/dist/images',
                to: 'vendor/leaflet/images'
            }
        ]
    }))
    .addPlugin(new CopyPlugin({
        patterns : [
            {
                from: 'node_modules/intl-tel-input/build/js/utils.js',
                to: 'vendor/intl-tel-input/utils.js'
            }
        ]
    }));

module.exports = Encore.getWebpackConfig();
