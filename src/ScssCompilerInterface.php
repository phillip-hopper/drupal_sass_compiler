<?php

namespace Drupal\scss_compiler;

/**
 * Provides an interface defining a SCSS Compiler service.
 */
interface ScssCompilerInterface {

  /**
   * Compiles single scss file into css.
   *
   * @param array $scss_file
   *   An associative array with scss file info.
   */
  public function compile(array $scss_file);

  /**
   * Compiles all scss files which was registered.
   *
   * @param bool $all
   *   If TRUE compiles all scss files from all themes in system,
   *   else compiles only scss files from active theme.
   */
  public function compileAll($all);

  /**
   * Returns list of scss files which need to be recompiled.
   *
   * @param bool $all
   *   If TRUE loads all scss files from all themes in system,
   *   else loads only scss files from active theme.
   *
   * @return array
   *   An associative array with scss files info.
   */
  public function getCompileList($all);

  /**
   * Saves list of scss files which need to be recompiled.
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
   * Returns info about cache.
   *
   * @return bool
   *   TRUE if cache enabled else FALSE.
   */
  public function isCacheEnabled();

  /**
   * Returns path to cache folder where compiled files save.
   *
   * @return string
   *   Internal drupal path to cache folder.
   */
  public function getCacheFolder();

  /**
   * Returns default namespace.
   *
   * @return string
   *   Default namespace name.
   */
  public function getDefaultNamespace();

}
