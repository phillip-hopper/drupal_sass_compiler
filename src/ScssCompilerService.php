<?php

namespace Drupal\scss_compiler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
* Defines a class for scss compiler service.
*/
class ScssCompilerService implements ScssCompilerInterface {

  /**
   * Configuration object of scss compiler.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The module handler class to use for check existing modules.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Current theme name
   *
   * @var string
   */
  protected $activeThemeName;
  
  /**
   * Compiler object instance
   *
   * @var \Drupal\scss_compiler\Compiler
   */
  protected $parser;
  
  /**
   * Path to cache folder
   *
   * @var string
   */
  protected $cacheFolder;
  
  /**
   * Flag if sourcemap enabled
   *
   * @var bool
   */
  protected $isSourcemapEnabled;

  /**
   * Flag if cache enabled
   *
   * @var bool
   */
  protected $isCacheEnabled;

  /**
   * Constructs a SCSS Compiler service object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration object factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler class to use for check existing modules.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config, ThemeManagerInterface $theme_manager, ModuleHandlerInterface $module_handler, RequestStack $request_stack) {
    $this->config = $config->get('scss_compiler.settings');
    $this->themeManager = $theme_manager;
    $this->moduleHandler = $module_handler;
    $this->request = $request_stack->getCurrentRequest();

    $this->activeThemeName = $theme_manager->getActiveTheme()->getName();
    $this->cacheFolder = 'public://scss_compiler';
    $this->isCacheEnabled = $this->config->get('cache');
    $this->isSourcemapEnabled = $this->config->get('sourcemaps');
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheEnabled() {
    return $this->isCacheEnabled;
  }

  /**
   * {@inheritdoc}
   */
  public function isSourcemapEnabled() {
    return $this->isSourcemapEnabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheFolder() {
    return $this->cacheFolder;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultNamespace() {
    return $this->activeThemeName;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompileList($files) {
    $settings_path = drupal_get_path('module', 'scss_compiler') . '/settings';

    if (file_prepare_directory($settings_path, FILE_CREATE_DIRECTORY)) {
      $old_files = $this->getCompileList();
      if (is_array($old_files)) {
        $files = array_merge($old_files, $files);
      }
      file_put_contents($settings_path . '/' . $this->activeThemeName . '.json', json_encode($files));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCompileList($all = false) {
    if ($all) {
      // @todo replace json files to drupal cache system
      // load all json files with scss info and merge it to remove duplicates
      $settings_path = drupal_get_path('module', 'scss_compiler') . '/settings/*.json';
      $files = [];
      foreach (glob($settings_path) as $file) {
        $content = file_get_contents($file);
        $files[] = json_decode($content, true);
      }
      return array_merge_recursive(...$files);
    } else {
      $settings_path = drupal_get_path('module', 'scss_compiler') . '/settings/' . $this->activeThemeName . '.json';
      $scss_files = '';
      if (file_exists($settings_path)) {
        $scss_files = file_get_contents($settings_path);
      }
      return json_decode($scss_files, true);
    }
  }

  /**
  * {@inheritdoc}
  */
  public function compileAll($all = false) {
    $scss_files = $this->getCompileList($all);
    if (!empty($scss_files)) {
      foreach ($scss_files as $namespace) {
        foreach ($namespace as $scss_file) {
          $this->compile($scss_file);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function compile($scss_file) {

    try {

      if (!file_exists($scss_file['scss_path'])) {
        throw new \Exception('File ' . $scss_file['scss_path'] . ' not found');
      }

      $type = 'theme';
      if ($this->moduleHandler->moduleExists($scss_file['namespace'])) {
        $type = 'module';
      }
      $path = @drupal_get_path($type, $scss_file['namespace']);
      if (empty($path)) {
        throw new \Exception($type . ' ' .  $scss_file['namespace'] . ' not found');
      }

      $theme_folder = '/' . $path;
      $cache_folder = $this->cacheFolder . '/' . $scss_file['namespace'];

      if (!$parser = $this->parser) {
        if (!file_exists(DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php')) {
          throw new \Exception('SCSS Compiler library not found. Visit status page for more information');
        }
        require_once DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php';
        $this->parser = $parser = new \Drupal\scss_compiler\Compiler();
      }

      $parser->setImportPaths([
        dirname($scss_file['scss_path']),
        DRUPAL_ROOT,
      ]);
      $parser->setVariables([
        'theme' => $theme_folder,
      ]);

      $parser->drupal_path = $theme_folder . '/';
      //disable utf-8 support to increase performance
      $parser->setEncoding(true);
      if ($this->isSourcemapEnabled) {
        $parser->setSourceMap(Compiler::SOURCE_MAP_FILE);
        $host = $this->request->getSchemeAndHttpHost();
        $parser->setSourceMapOptions([
          'sourceMapWriteTo' => $cache_folder . '/' . $scss_file['name'] . '.css.map',
          'sourceMapURL' => file_create_url($cache_folder . '/' . $scss_file['name'] . '.css.map'),
          'sourceMapBasepath' => $host . '/',
          'sourceMapRootpath' => $host . '/',
        ]);
      }
      file_prepare_directory($cache_folder, FILE_CREATE_DIRECTORY);
      $content = $parser->compile(file_get_contents($scss_file['scss_path']), $scss_file['scss_path']);
      file_put_contents($scss_file['css_path'], trim($content));

    } catch (\Exception $e) {
      trigger_error($e->getMessage(), E_USER_ERROR);
    }
  }

}
