# SCSS Compiler
Module compiles scss files into css via [ScssPhp Compiler](https://scssphp.github.io/scssphp/)
## Installation
1. Download last release of [ScssPhp Compiler](https://github.com/scssphp/scssphp/releases)
2. Rename it to `scssphp` and place into libraries directory (DRUPAL_ROOT/libraries/)
3. Install module and all SCSS files defined in libraries.yml will be compiled into css
## Usage
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: {}
```
By default, compiled files are saved to public://scss_compiler

Also you can define `css_path` — path where to save the compiled file, 
full path from DRUPAL_ROOT, for example:
```yml
# my_module.libraries.yml
main:
  version: VERSION
  css:
    theme:
      scss/styles.scss: { css_path: 'themes/my_theme/css/' }
```
All module settings are on the performance page.
