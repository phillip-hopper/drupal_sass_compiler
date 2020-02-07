## INTRODUCTION
Module automatically compiles scss/less files defined in libraries.yml into css.

## REQUIREMENTS
Compiler library for scss files [ScssPhp][1]
Compiler library for less files [LessPhp][4]


## INSTALLATION
### Manual installation
1. Download last release of [ScssPhp Compiler][2]
2. Rename it to `scssphp` and place into libraries directory
(DRUPAL_ROOT/libraries/)
3. Install module and all SCSS files defined in libraries.yml
will be compiled into css

### Composer installation
If you manage your site with composer, just install it like other composer
packages, dependencies will be resolved automatically.

Less library is optional, you need to install it manually because of php 7.2.9
dependency.

## CONFIGURATION
All module settings are on the performance page.
Option "Check file modified time" will track last modified time of files and
compiler won't compile files before it changes.

## USAGE
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: {}
      less/styles.less: {}
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
File will be saved to `my_module/css/styles.css`

Assets path option allow to define where static resources places, by default
it's module/theme folder. Full path to assets folder. Suports token for
theme/module.
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: { assets_path: '@my_module/assets/' }
```
url(images.jpg) in css will be compiled to
url(modules/custom/my_module/assets/image.jpg);

[1]: https://scssphp.github.io/scssphp/
[2]: https://github.com/scssphp/scssphp/releases
[3]: https://github.com/mnsami/composer-custom-directory-installer
[4]: https://github.com/wikimedia/less.php
