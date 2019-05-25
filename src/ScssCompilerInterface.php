<?php

namespace Drupal\scss_compiler;

/**
 * Provides an interface defining a SCSS Compiler service.
 */
interface ScssCompilerInterface {

  /**
   * Compile single scss file into css.
   *
   * @param array $scss_file
   *   An associative array with scss file info.
   */
  public function compile(array $scss_file);

  /**
   * Compiles all scss files which was registered.
   *
   * @param bool $all
   *   If TRUE compile all scss files from all themes in system,
   *   else compile only scss files from active theme.
   */
  public function compileAll(bool $all);

  /**
   * Return list of scss files which need to be recompiled.
   *
   * @param bool $all
   *   If TRUE load all scss files from all themes in system,
   *   else load only scss files from active theme.
   *
   * @return array
   *   An associative array with scss files info.
   */
  public function getCompileList(bool $all);

  /**
   * Save list of scss files which need to be recompiled.
   *
   * @param array $files
   *   List of scss files.
   */
  public function setCompileList(array $files);

  /**
   * Gets a specific option.
   *
   * @param string $option
   *   The name of the option.
   *
   * @return mixed
   *   The value for a specific option,
   *   or NULL if it does not exist.
   */
  public function getOption($option);

  /**
   * Return info about cache.
   *
   * @return bool
   *   TRUE if cache enabled else FALSE.
   */
  public function isCacheEnabled();

  /**
   * Return path to cache folder where compiled file save.
   *
   * @return string
   *   Internal drupal path to cache folder.
   */
  public function getCacheFolder();

  /**
   * Return default namespace.
   *
   * @return string
   *   Namespace title.
   */
  public function getDefaultNamespace();

}
