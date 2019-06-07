<?php

namespace Drupal\scss_compiler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Defines a class for scss compiler service.
 */
class ScssCompilerService implements ScssCompilerInterface {

  use StringTranslationTrait;
  use MessengerTrait;

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
   * The module handler.
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
   * The default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Current theme name.
   *
   * @var string
   */
  protected $activeThemeName;

  /**
   * Compiler object instance.
   *
   * @var \Drupal\scss_compiler\Compiler
   */
  protected $parser;

  /**
   * Path to cache folder.
   *
   * @var string
   */
  protected $cacheFolder;

  /**
   * Flag if sourcemap enabled.
   *
   * @var bool
   */
  protected $isSourcemapEnabled;

  /**
   * Flag if cache enabled.
   *
   * @var bool
   */
  protected $isCacheEnabled;

  /**
   * Output format type.
   *
   * @var string
   */
  protected $outputFormat;

  /**
   * Constructs a SCSS Compiler service object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration object factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache bin.
   */
  public function __construct(ConfigFactoryInterface $config, ThemeManagerInterface $theme_manager, ModuleHandlerInterface $module_handler, RequestStack $request_stack, CacheBackendInterface $cache) {
    $this->config = $config->get('scss_compiler.settings');
    $this->themeManager = $theme_manager;
    $this->moduleHandler = $module_handler;
    $this->request = $request_stack->getCurrentRequest();
    $this->cache = $cache;

    $this->activeThemeName = $theme_manager->getActiveTheme()->getName();
    $this->cacheFolder = 'public://scss_compiler';
    $this->outputFormat = $this->config->get('output_format');
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
  public function getOption($option) {
    if (!is_string($option)) {
      return NULL;
    }
    return $this->config->get($option);
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
  public function setCompileList(array $files) {
    // Save list of scss files which need to be recompiled to the cache.
    // Each theme has own list of files, to prevent recompile files
    // which not loaded in current theme.
    $data = [];
    if ($cache = $this->cache->get('scss_compiler_list')) {
      $data = $cache->data;
      if (!empty($data[$this->activeThemeName])) {
        $old_files = $data[$this->activeThemeName];
        if (is_array($old_files)) {
          $files = array_merge($old_files, $files);
        }
      }
    }
    $data[$this->activeThemeName] = $files;
    $this->cache->set('scss_compiler_list', $data, CacheBackendInterface::CACHE_PERMANENT);
  }

  /**
   * {@inheritdoc}
   */
  public function getCompileList($all = FALSE) {
    $files = [];
    if ($cache = $this->cache->get('scss_compiler_list')) {
      $data = $cache->data;
      if ($all) {
        $files = array_merge_recursive(...array_values($data));
      }
      elseif (!empty($data[$this->activeThemeName])) {
        $files = $data[$this->activeThemeName];
      }
    }

    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function compileAll($all = FALSE) {
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
  public function compile(array $scss_file) {
    try {
      if (!file_exists($scss_file['scss_path'])) {
        $error_message = $this->t('File @path not found', [
          '@path' => $scss_file['scss_path'],
        ]);
        throw new \Exception($error_message);
      }

      $type = 'theme';
      if ($this->moduleHandler->moduleExists($scss_file['namespace'])) {
        $type = 'module';
      }
      $path = @drupal_get_path($type, $scss_file['namespace']);
      if (empty($path)) {
        $error_message = $this->t('@path not found', [
          '@path' => $type . ' ' . $scss_file['namespace'],
        ]);
        throw new \Exception($error_message);
      }

      $theme_folder = '/' . $path;
      $cache_folder = $this->cacheFolder . '/' . $scss_file['namespace'];

      if (!$parser = $this->parser) {
        if (!file_exists(DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php')) {
          $error_message = $this->t('SCSS Compiler library not found. Visit status page for more information.');
          throw new \Exception($error_message);
        }
        require_once DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php';

        // leafo/scssphp no longer supported, it was forked to scssphp/scssphp.
        // @see https://github.com/leafo/scssphp/issues/707
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
          $error_message = $this->t('leafo/scssphp no longer supported. Use scssphp/scssphp instead (https://github.com/scssphp/scssphp/releases)');
          throw new \Exception($error_message);
        }
        $this->parser = $parser = new Compiler();
        $this->parser->setFormatter($this->getScssPhpFormatClass($this->outputFormat));
      }

      // Build path for @import, if import not found relative to current file,
      // find relative to DRUPAL_ROOT, for example, load scss from another
      // module, @import modules/custom/my_module/scss/mixins.
      $parser->setImportPaths([
        dirname($scss_file['scss_path']),
        DRUPAL_ROOT,
      ]);

      // Add theme/module path to compiler to build path to static resources.
      $parser->drupalPath = $theme_folder . '/';
      // Disable utf-8 support to increase performance.
      $parser->setEncoding(TRUE);
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

    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Returns ScssPhp Compiler format classname.
   *
   * @param string $format
   *   Format name.
   *
   * @return string
   *   Format type classname.
   */
  private function getScssPhpFormatClass($format) {
    switch ($format) {
      case 'expanded':
        return '\ScssPhp\ScssPhp\Formatter\Expanded';

      case 'nested':
        return '\ScssPhp\ScssPhp\Formatter\Nested';

      case 'compact':
        return '\ScssPhp\ScssPhp\Formatter\Compact';

      case 'crunched':
        return '\ScssPhp\ScssPhp\Formatter\Crunched';

      default:
        return '\ScssPhp\ScssPhp\Formatter\Compressed';
    }
  }

}
