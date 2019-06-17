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
   * Flag if cache enabled.
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
    $this->isCacheEnabled = $this->config->get('cache');
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
  public function buildCompilationFileInfo(array $info) {
    try {
      if (empty($info['data']) || empty($info['namespace'])) {
        $error_message = $this->t('Compilation file info build is failed. Required parameters are missing.');
        throw new \Exception($error_message);
      }

      $namespace_path = $this->getNamespacePath($info['namespace']);
      $name = pathinfo($info['data'], PATHINFO_FILENAME);
      if (!empty($info['css_path'])) {
        // If custom css path defined, build path relative to theme/module.
        $css_path = $namespace_path . '/' . trim($info['css_path'], '/. ') . '/' . $name . '.css';
      }
      else {
        // Get source file path relative to theme/module and add it to css path
        // to prevent overwriting files when two source files with the same name
        // defined in different folders.
        $source_folder = dirname($info['data']);
        if (substr($source_folder, 0, strlen($namespace_path)) === $namespace_path) {
          $internal_folder = substr($source_folder, strlen($namespace_path));
          $css_path = $this->getCacheFolder() . '/' . $info['namespace'] . '/' . trim($internal_folder, '/ ') . '/' . $name . '.css';
        }
        else {
          $css_path = $this->getCacheFolder() . '/' . $info['namespace'] . '/' . $name . '.css';
        }
      }

      return [
        'name'        => $name,
        'namespace'   => $info['namespace'],
        'source_path' => $info['data'],
        'css_path'    => $css_path,
      ];
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Returns namespace path.
   *
   * @param string $namespace
   *   Namespace name.
   *
   * @throws Exception
   *   If namespace is invalid.
   *
   * @return string
   *   Path to theme/module of given namespace.
   */
  protected function getNamespacePath($namespace) {
    $type = 'theme';
    if ($this->moduleHandler->moduleExists($namespace)) {
      $type = 'module';
    }
    $path = @drupal_get_path($type, $namespace);
    if (empty($path)) {
      $error_message = $this->t('@namespace is invalid', [
        '@namespace' => $type . ' ' . $namespace,
      ]);
      throw new \Exception($error_message);
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function compile(array $scss_file) {
    try {
      if (!file_exists($scss_file['source_path'])) {
        $error_message = $this->t('File @path not found', [
          '@path' => $scss_file['source_path'],
        ]);
        throw new \Exception($error_message);
      }

      if (!$parser = $this->parser) {
        if (!file_exists(DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php')) {
          $error_message = $this->t('SCSS Compiler library not found. Visit status page for more information.');
          throw new \Exception($error_message);
        }
        require_once DRUPAL_ROOT . '/libraries/scssphp/scss.inc.php';

        // leafo/scssphp no longer supported, it was forked to scssphp/scssphp.
        // @see https://github.com/leafo/scssphp/issues/707
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
          $error_message = $this->t('leafo/scssphp no longer supported. Update compiler library to scssphp/scssphp @url', [
            '@url' => '(https://github.com/scssphp/scssphp/releases)',
          ]);
          throw new \Exception($error_message);
        }
        $this->parser = $parser = new Compiler();
        $this->parser->setFormatter($this->getScssPhpFormatClass($this->getOption('output_format')));
        // Disable utf-8 support to increase performance.
        $this->parser->setEncoding(TRUE);
      }

      // Build path for @import, if import not found relative to current file,
      // find relative to DRUPAL_ROOT, for example, load scss from another
      // module, @import modules/custom/my_module/scss/mixins.
      $parser->setImportPaths([
        dirname($scss_file['source_path']),
        DRUPAL_ROOT,
      ]);

      // Add theme/module path to compiler to build path to static resources.
      $parser->drupalPath = '/' . $this->getNamespacePath($scss_file['namespace']) . '/';

      $css_folder = dirname($scss_file['css_path']);
      if ($this->getOption('sourcemaps')) {
        $parser->setSourceMap(Compiler::SOURCE_MAP_FILE);
        $host = $this->request->getSchemeAndHttpHost();
        $sourcemap_file = $css_folder . '/' . $scss_file['name'] . '.css.map';
        $parser->setSourceMapOptions([
          'sourceMapWriteTo'  => $sourcemap_file,
          'sourceMapURL'      => file_create_url($sourcemap_file),
          'sourceMapBasepath' => $host . '/',
          'sourceMapRootpath' => $host . '/',
        ]);
      }
      file_prepare_directory($css_folder, FILE_CREATE_DIRECTORY);
      // If custom css path defined, check if it located in the proper
      // theme/module folder else throw an error.
      if (substr($css_folder, 0, strlen($this->cacheFolder)) !== $this->cacheFolder) {
        $namespace_path = $this->getNamespacePath($scss_file['namespace']);
        if (strpos(realpath($css_folder), realpath($namespace_path)) !== 0) {
          $error_message = $this->t('Css destination path is wrong, @path', [
            '@path' => $scss_file['css_path'],
          ]);
          throw new \Exception($error_message);
        }
      }
      $content = $parser->compile(file_get_contents($scss_file['source_path']), $scss_file['source_path']);
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
