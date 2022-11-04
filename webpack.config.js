const Encore = require('@symfony/webpack-encore');
const CopyPlugin = require('copy-webpack-plugin');
const path = require('path');

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
    .addEntry('article-form', './assets/js/pages/article/form.js')
    .addEntry('reference-article-index', './assets/js/pages/reference-article/index.js')
    .addEntry('cart', './assets/js/pages/cart.js')
    .addEntry('settings-index', './assets/js/pages/settings/index.js')
    .addEntry('settings-data-imports', './assets/js/pages/settings/data/imports.js')
    .addEntry('settings-data-exports', './assets/js/pages/settings/data/exports.js')
    .addEntry('settings-data-inventories-imports', './assets/js/pages/settings/data/inventories-imports.js')
    .addEntry('settings-users-roles-form', './assets/js/pages/settings/users/roles/form.js')
    .addEntry('settings-languages', './assets/js/pages/settings/users/languages.js')
    .addEntry('vehicle', './assets/js/pages/vehicle.js')
    .addEntry('project', './assets/js/pages/project.js')
    .addEntry('transport-request-index', './assets/js/pages/transport/request/index.js')
    .addEntry('transport-request-show', './assets/js/pages/transport/request/show.js')
    .addEntry('transport-order-index', './assets/js/pages/transport/order/index.js')
    .addEntry('transport-order-show', './assets/js/pages/transport/order/show.js')
    .addEntry('transport-round-index', './assets/js/pages/transport/round/index.js')
    .addEntry('transport-round-plan', './assets/js/pages/transport/round/plan.js')
    .addEntry('transport-round-show', './assets/js/pages/transport/round/show.js')
    .addEntry('transport-subcontract-index', './assets/js/pages/transport/subcontract/index.js')
    .addEntry('transport-order-planning', './assets/js/pages/transport/order/planning.js')
    .addEntry('preparation-planning', './assets/js/pages/preparation/planning.js')
    .addEntry('reception-show', './assets/js/pages/reception/show.js')
    .addEntry('handling-show', './assets/js/pages/handling/show.js')
    .addEntry('handling-edit', './assets/js/pages/handling/edit.js')
    .addEntry('register', './assets/js/pages/register/register.js')
    .addEntry('customer-index', './assets/js/pages/customer/index.js')
    .autoProvidejQuery()

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    .addAliases({
        '@app': path.resolve(__dirname, 'assets/js'),
        '@styles': path.resolve(__dirname, 'assets/scss')
    })

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
