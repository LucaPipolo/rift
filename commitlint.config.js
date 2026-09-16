import configConventional from '@commitlint/config-conventional';

const [, , conventionalTypes] = configConventional.rules['type-enum'];

export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'type-enum': [2, 'always', [...conventionalTypes, 'ai']],
        'scope-empty': [2, 'always'],
    },
};
