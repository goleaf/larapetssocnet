export default {
    customSyntax: 'postcss-scss',
    extends: ['stylelint-config-standard-scss'],
    plugins: ['stylelint-order'],
    ignoreFiles: ['build/**', 'node_modules/**', 'storage/**', 'vendor/**'],
    rules: {
        'at-rule-no-unknown': null,
        'scss/at-rule-no-unknown': [
            true,
            {
                ignoreAtRules: [
                    'apply',
                    'config',
                    'custom-variant',
                    'layer',
                    'plugin',
                    'reference',
                    'source',
                    'tailwind',
                    'theme',
                    'utility',
                    'variant',
                ],
            },
        ],
        'color-function-notation': null,
        'custom-property-pattern': null,
        'import-notation': null,
        'order/order': [
            'dollar-variables',
            'custom-properties',
            'declarations',
            {
                name: 'include',
                type: 'at-rule',
            },
            'rules',
        ],
        'selector-class-pattern': null,
    },
};
