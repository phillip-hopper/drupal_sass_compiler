<?php

namespace Drupal\scss_compiler;

/**
 * Collects alter data.
 *
 * Invoke alters on each file which run thru compiler is not effective, so this
 * storage collects all data and allow performing altering based on
 * module/theme name or file path.
 */
class ScssCompilerAlterStorage {

  /**
   * Array with collected data.
   *
   * @var array
   */
  protected $storage = [];

  /**
   * Set data by module/theme name.
   *
   * @param array $values
   *   Array with data.
   * @param string $namespace
   *   Module/theme name. By default global scope.
   */
  public function set(array $values, $namespace = '_global') {
    if (!isset($this->storage['namespace'][$namespace])) {
     $this->storage['namespace'][$namespace] = [];
    }
    $this->storage['namespace'][$namespace] = array_merge($this->storage['namespace'][$namespace], $values);
  }

  /**
   * Set data by file name.
   *
   * @param array $values
   *   Array with data.
   * @param string $file_path
   *   Path to source file from DRUPAL_ROOT.
   */
  public function setByFile(array $values, $file_path) {
    if (!isset($this->storage['file'][$file_path])) {
      $this->storage['file'][$file_path] = [];
    }
    $this->storage['file'][$file_path] = array_merge($this->storage['file'][$file_path], $values);
  }

  /**
   * Get data by module/theme name.
   *
   * @param string $namespace
   *   Module/theme name. By default global scope.
   */
  public function get($namespace = '_global') {
    if (!isset($this->storage['namespace'][$namespace])) {
      return [];
    }
    return $this->storage['namespace'][$namespace];
  }

  /**
   * Get data by file path.
   *
   * @param string $file_path
   *   Path to source file from DRUPAL_ROOT.
   */
  public function getByFile($file_path) {
    if (!isset($this->storage['file'][$file_path])) {
      return [];
    }
    return $this->storage['file'][$file_path];
  }

  /**
   * Returns merged data in all scopes.
   *
   * @param string $namespace
   *   Module/theme name.
   * @param string $file_path
   *   Path to source file from DRUPAL_ROOT.
   */
  public function getAll($namespace, $file_path) {
    return array_merge($this->get(), $this->get($namespace), $this->getByFile($file_path));
  }

  /**
   * Returns entire storage.
   *
   * @return array
   *   Array with data.
   */
  public function getStorage() {
    return $this->storage;
  }

}
