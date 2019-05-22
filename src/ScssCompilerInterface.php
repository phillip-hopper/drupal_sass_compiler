<?php

namespace Drupal\scss_compiler;

/**
 * Provides an interface defining a SCSS Compiler service.
 */
interface ScssCompilerInterface {

  /**
   * Compile single scss file into css
   * 
   * @param array $scss_file
   *   An associative array with scss file info
   */
  public function compile($scss_file);

  /**
   * Return list of scss files which need to be recompiled
   * 
   * @return array
   *   An associative array with scss files info
   */
  public function getCompileList();

  /**
   * Save list of scss files which need to be recompiled to json settings file
   * 
   * @param array $files
   *   List of scss files
   */
  public function setCompileList($files);

  /**
   * Return info about cache
   * 
   * @return bool
   *   true if cache enabled else false
   */
  public function isCacheEnabled();

  /**
   * Return info about sourcemap configuration
   * 
   * @return bool
   *   true if sourcemaps enabled else false
   */
  public function isSourcemapEnabled();

  /**
   * Return path to cache folder where compiled file save
   * 
   * @return string
   *   Internal drupal path to cache folder
   */
  public function getCacheFolder();

  /**
   * Return default namespace
   * 
   * @return string
   *   Namespace title
   */
  public function getDefaultNamespace();

}
