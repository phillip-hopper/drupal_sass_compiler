<?php

namespace Drupal\scss_compiler\Plugin\ScssCompiler;

use Drupal\scss_compiler\ScssCompilerPluginInterface;

/**
 * Plugin implementation of the Less compiler.
 *
 * @ScssCompilerPlugin(
 *   id   = "scss_compiler_lessphp",
 *   name = "LessPhp Compiler",
 *   description = "PHP port of the official LESS processor",
 *   extensions = {
 *     "less" = "less",
 *   }
 * )
 */
class LessphpCompiler implements ScssCompilerPluginInterface {

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
    $status = self::getStatus();
    if ($status !== TRUE) {
      throw new \Exception($status);
    }
    $this->parser = new \Less_Parser();
  }

  /**
   * {@inheritdoc}
   */
  public static function getVersion() {
    if (class_exists('Less_Version')) {
      return \Less_Version::version;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getStatus() {
    $compiler_class_exists = class_exists('Less_Parser');
    if (!$compiler_class_exists) {
      $error_message = t('LessPhp Compiler library not found. Install it via composer "composer require wikimedia/less.php"');
    }
    if (!empty($error_message)) {
      return $error_message;
    }
    return TRUE;
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
    $last_modify_time = filemtime($source_file['source_path']);
    $source_folder = dirname($source_file['source_path']);
    $import = [];
    $content = file_get_contents($source_file['source_path']);
    preg_match_all('/@import(.*);/', $content, $import);
    if (!empty($import[1])) {
      foreach ($import[1] as $file) {
        // Normalize @import path.
        $file_path = trim($file, '\'" ');
        $pathinfo = pathinfo($file_path);
        $extension = '.less';
        $filename = $pathinfo['filename'];
        $dirname = $pathinfo['dirname'] === '.' ? '' : $pathinfo['dirname'] . '/';

        $file_path = $source_folder . '/' . $dirname . $filename . $extension;
        $less_path = $source_folder . '/' . $dirname . '_' . $filename . $extension;

        if (file_exists($file_path) || file_exists($file_path = $less_path)) {
          $file_modify_time = filemtime($file_path);
          if ($file_modify_time > $last_modify_time) {
            $last_modify_time = $file_modify_time;
          }
        }
      }
    }
    return $last_modify_time;
  }

}
