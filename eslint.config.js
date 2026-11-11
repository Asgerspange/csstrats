import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default [
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.{js,ts,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module'
        },
        rules: {
            'indent': ['error', 4],
            'vue/html-indent': ['error', 4],
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'warn'
        }
    }
];