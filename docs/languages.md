# Adding a language to the Contao Manager

To support a new language, the following steps are necessary:

1. Register the new language on transifex.com

2. Sync the files with Transifex (`phar tx`). Make sure both the .yml and .json file are created.

3. Load the new language files in `src/i18n/index.js`

4. Add the language to `src/i18n/locales.js` of the Contao Manager and contao-package-list
