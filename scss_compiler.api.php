<?php

/**
 * @file
 * Hooks related to SCSS compiler module.
 */

/**
 * Add additional scss import paths.
 *
 * For examble need to import Foundation framework into your scss file, you can
 * define path where framework place and use @import foundation.
 *
 * @param array $additional_import_paths
 *   The array with additional paths.
 */
function my_module_scss_compiler_import_paths_alter(array &$additional_import_paths) {
  $additional_import_paths[] = \Drupal::service('file_system')->realpath('vendor/zurb/foundation/scss');
}
