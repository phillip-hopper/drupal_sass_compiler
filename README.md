# SCSS Compiler
Module compiles scss files into css via [ScssPhp Compiler](https://scssphp.github.io/scssphp/)
## Manual installation
1. Download last release of [ScssPhp Compiler](https://github.com/scssphp/scssphp/releases)
2. Rename it to `scssphp` and place into libraries directory
(DRUPAL_ROOT/libraries/)
3. Install module and all SCSS files defined in libraries.yml
will be compiled into css
## Composer installation
Module could work even if compiler library was in the vendor folder, but when
the core will be updated manually, the compiler library will be deleted
from the vendor folder, so it installs to the drupal libraries folder,
but composer doesn't allow to install packages outside of the vendor
folder, only via custom installers, so we use composer [custom-directory-installer](https://github.com/mnsami/composer-custom-directory-installer)
It allows us to change destination folder of package, by defining it manually.

Add scssphp/scssphp to drupal-libraries path in composer.json, libraries path
may be different, so don't replace entire path from the example.
```json
"extra": {
  "installer-paths": {
    "web/libraries/{$name}": ["type:drupal-library", "scssphp/scssphp"]
  }
}
```
Install dependencies and module
```
composer require 'mnsami/composer-custom-directory-installer:^1.1'
composer require 'scssphp/scssphp:^1.0'
composer require 'drupal/scss_compiler:1.x-dev'
```
## Usage
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: {}
```
By default, compiled files are saved to `public://scss_compiler`

Also you can define `css_path` — path where to save the compiled file,
path relative to module/theme where libraries.yml place, for example:
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: { css_path: '/css/' }
```
File will be saved to `my_theme/css/styles.css`
Path relative from DRUPAL_ROOT was removed because of risk overwrite files
which not belong to module/theme.

All module settings are on the performance page.
