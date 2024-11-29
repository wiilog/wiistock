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
    .addEntry('article-index', './assets/js/pages/article/index.js')
    .addEntry('reference-article-form', './assets/js/pages/reference-article/form.js')
    .addEntry('article-form', './assets/js/pages/article/form.js')
    .addEntry('reference-article-form-common', './assets/js/pages/reference-article/common.js')
    .addEntry('reference-article-index', './assets/js/pages/reference-article/index.js')
    .addEntry('cart', './assets/js/pages/cart.js')
    .addEntry('settings-index', './assets/js/pages/settings/index.js')
    .addEntry('settings-data-imports', './assets/js/pages/settings/data/imports.js')
    .addEntry('kiosk-settings', './assets/js/pages/settings/kiosk.js')
    .addEntry('settings-data-exports', './assets/js/pages/settings/data/exports.js')
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
    .addEntry('handling-index', './assets/js/pages/handling/index.js')
    .addEntry('collect-request-index', './assets/js/pages/collect-request/index.js')
    .addEntry('collect-request-show', './assets/js/pages/collect-request/show.js')
    .addEntry('register', './assets/js/pages/register/register.js')
    .addEntry('customer-index', './assets/js/pages/customer/index.js')
    .addEntry('kiosk', './assets/js/pages/kiosk.js')
    .addEntry('carrier-index', './assets/js/pages/carrier/index.js')
    .addEntry('settings-inventory-planner', './assets/js/pages/settings/inventory/inventoryPlanner.js')
    .addEntry('form-add-inventory-locations', './assets/js/pages/inventory-mission/form-add-inventory-locations.js')
    .addEntry('inventory-mission-show', './assets/js/pages/inventory-mission/show.js')
    .addEntry('truck-arrival-index', './assets/js/pages/truck-arrival/index.js')
    .addEntry('truck-arrival-show', './assets/js/pages/truck-arrival/show.js')
    .addEntry('delivery-request-show', './assets/js/pages/delivery-request/show.js')
    .addEntry('shipping-request-index', './assets/js/pages/shipping-request/index.js')
    .addEntry('shipping-request-show', './assets/js/pages/shipping-request/show.js')
    .addEntry('delivery-station', './assets/js/pages/delivery-station.js')
    .addEntry('tracking-movement', './assets/js/pages/tracking-movement/tracking-movement.js')
    .addEntry('tracking-movement-wrong-location-warning', './assets/js/pages/tracking-movement/wrong-location-warning.js')
    .addEntry('filters', './assets/js/filters.js')
    .addEntry('receipt-association-index', './assets/js/pages/receipt-association/index.js')
    .addEntry('production-index', './assets/js/pages/production/index.js')
    .addEntry('production-show', './assets/js/pages/production/show.js')
    .addEntry('production-planning', './assets/js/pages/production/planning.js')
    .addEntry('dashboard-settings', './assets/js/pages/dashboard/settings.js')
    .addEntry('location-index', './assets/js/pages/location/index.js')
    .addEntry('transfer-order-index', './assets/js/pages/transfer/order/index.js')
    .addEntry('transfer-order-show', './assets/js/pages/transfer/order/show.js')
    .addEntry('transfer-request-index', './assets/js/pages/transfer/request/index.js')
    .addEntry('transfer-request-show', './assets/js/pages/transfer/request/show.js')
    .addEntry('purchase-request-index', './assets/js/pages/purchase-request/index.js')
    .addEntry('purchase-request-show', './assets/js/pages/purchase-request/show.js')
    .addEntry('supplier-index', './assets/js/pages/supplier/index.js')
    .addEntry('stock-movement-index', './assets/js/pages/stock-movement/index.js')
    .addEntry('nature-index', './assets/js/pages/nature/index.js')
    .addEntry('warning-header', './assets/js/warning-header.js')
    .addEntry('pack-show', './assets/js/pages/pack/show.js')
    .addEntry('pack-index', './assets/js/pages/pack/pack.js')
    .addEntry('encours-index', '/assets/js/pages/encours/index.js')
    .addEntry('security-login', './assets/js/pages/security/login.js')
    .addEntry('security-password-forgotten', '/assets/js/pages/security/password-forgotten.js')
    .addEntry('security-reset-password', '/assets/js/pages/security/reset-password.js')
    .addEntry('arrival-show', './assets/js/pages/arrival/show.js')
    .addStyleEntry('pack-common', './assets/scss/utils/pack.scss')
    .autoProvidejQuery()

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    .addAliases({
        '@app': path.resolve(__dirname, 'assets/js'),
        '@generated': path.resolve(__dirname, 'assets/generated'),
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
    .enableSassLoader((options) => {
        options.additionalData = `$app-context: '${process.env.APP_CONTEXT || `prod`}';`;
    })
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
