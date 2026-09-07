const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
    entry: {
        'admin': './assets/js/admin.js',
        'admin.mediamanager': './assets/js/admin.mediamanager.js'
    },
    output: {
        filename: '[name].min.js',
        chunkFilename: '[name].min.js',
        path: path.resolve(__dirname, 'assets/js/'),
        // Resolve async chunks relative to this script's URL
        // (e.g. /vendor/nails/module-cdn/assets/js/)
        publicPath: 'auto'
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            },
            {
                test: /\.(css|scss|sass)$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            url: false
                        }
                    },
                    'postcss-loader',
                    {
                        loader: 'sass-loader',
                        options: {
                            api: 'modern'
                        }
                    }
                ]
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: '../css/[name].min.css',
            chunkFilename: '../css/[name].min.css'
        }),
        new VueLoaderPlugin(),

    ],
    // Keep the MediaManagerV2 async import as a single stable chunk
    // (avoid hashed vendor split chunks like 621.min.js)
    optimization: {
        splitChunks: false
    },
    externals: {
        vue: 'Vue'
    },
    mode: 'production'
};
