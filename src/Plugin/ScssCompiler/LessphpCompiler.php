<?php

namespace Drupal\scss_compiler\Plugin\ScssCompiler;

use Drupal\scss_compiler\ScssCompilerManagerInterface;

/**
 * Plugin implementation of the Less compiler.
 *
 * @ScssCompiler(
 *   id   = "scss_compiler_lessphp",
 *   name = "LessPhp Compiler",
 *   description = "PHP port of the official LESS processor",
 *   extensions = {
 *     "less" = "less",
 *   }
 * )
 */
class LessphpCompiler implements ScssCompilerManagerInterface {

  /**
   * Compiler object instance.
   *
   * @var \Less_Parser
   */
  protected $parser;

  /**
   * Constructs LessphpCompiler object.
   */
  public function __construct() {
    $compiler_class_exists = class_exists('Less_Parser');
    if (!$compiler_class_exists) {
      $error_message = t('LessPhp Compiler library not found. Install it manually via composer "composer require wikimedia/less.php"');
      throw new \Exception($error_message);
    }
    $this->parser = new \Less_Parser();
  }

  /**
   * {@inheritdoc}
   */
  public function compile(array $scss_file) {
    $import_paths = [
      dirname($scss_file['source_path']),
      DRUPAL_ROOT,
    ];
    if (\Drupal::service('scss_compiler')->getAdditionalImportPaths()) {
      $import_paths = array_merge($import_paths, \Drupal::service('scss_compiler')->getAdditionalImportPaths());
    }

    $this->parser->setImportDirs($import_paths);

    $css_folder = dirname($scss_file['css_path']);
    if (\Drupal::service('scss_compiler')->getOption('sourcemaps')) {
      $sourcemap_file = $css_folder . '/' . $scss_file['name'] . '.css.map';
      $host = \Drupal::request()->getSchemeAndHttpHost();
      $this->parser->setOptions([
        'sourceMap'         => TRUE,
        'sourceMapWriteTo'  => $sourcemap_file,
        'sourceMapURL'      => file_create_url($sourcemap_file),
        'sourceMapBasepath' => DRUPAL_ROOT,
        'sourceMapRootpath' => $host . '/',
      ]);
    }

    file_prepare_directory($css_folder, FILE_CREATE_DIRECTORY);
    $this->parser->parseFile($scss_file['source_path'], '/' . $scss_file['source_path']);
    $content = $this->parser->getCss();

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function checkLastModifyTime(array &$source_file) {
    return time();
  }

}
