import js from '@eslint/js';

export default [
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js', 'scripts/**/*.mjs', 'tests/JavaScript/**/*.js', 'tests/Fixtures/**/*.js', 'vite.config.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                Buffer: 'readonly',
                console: 'readonly',
                document: 'readonly',
                process: 'readonly',
                window: 'readonly',
            },
        },
    },
];
