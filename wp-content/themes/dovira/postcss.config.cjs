const { basename } = require('path');
const themeDirName = basename(__dirname);

module.exports = {
  plugins: {
    'postcss-assets': {
      loadPaths: ['images/', 'fonts/'],
      basePath: 'source/',
      baseUrl: process.env.NODE_ENV === 'development' ? `/wp-content/themes/${themeDirName}/source/` : `/wp-content/themes/${themeDirName}/assets/`,
    },
    'postcss-preset-env': {
      stage: 1,
    },
    'postcss-prefix-selector': {
      prefix: '.acf-block-preview',
      exclude: ['body', 'div', 'div.acf-block-preview', '.entity-status', '.entity-status--new', '.entity-status--processed'],
      skipGlobalSelectors: true,
      ignoreFiles: ['app.css'],
    },
    'postcss-replace': {
      pattern: '.acf-block-preview .acf-block-preview',
      data: { replaceAll: '.acf-block-preview' }
    }
  },
};
