<?php

namespace Electro\Kernel\Config;

use Electro\Interfaces\DI\InjectorInterface;

class KernelSettings
{
  const FRAMEWORK_PATH = 'private/packages/electro/framework';
  /**
   * The real application name. This is read from templates.
   *
   * @var string
   */
  public $appName = '⚡️ ELECTRO';
  /**
   * The file path of current main application's root directory.
   *
   * @var string
   */
  public $baseDirectory;
  /**
   * The URL path of current main application's base URL.
   *
   * @var string
   */
  public $basePath;
  /**
   * The URL that matches the main application's root directory.
   *
   * @var string
   */
  public $baseUrl;
  /**
   * The virtual URL for an HTTP request for opening a source file on an editor at the error location.
   *
   * <p>Example: `'edit-source'` for generating `'edit-source?file=filename.php&line=8&col=1'`
   *
   * @var string
   */
  public $editorUrl = 'edit-source';
  /**
   * Favorite icon URL. This is read from templates.
   *
   * @var string
   */
  public $favicon = 'data:;base64,iVBORw0KGgo=';
  /**
   * The absolute path of the framework kernel's directory.
   *
   * @var string
   */
  public $frameworkPath;
  /**
   * The mapped public URI of the framework's public directory.
   *
   * @var string
   */
  public $frameworkURI = 'framework';
  /**
   * @var \Electro\DependencyInjection\Injector
   */
  public $injector;
  /**
   * If `true` the application is a console app, otherwise it may be a web app.
   *
   * @see $isWebBased
   * @var bool
   */
  public $isConsoleBased = false;
  /**
   * If `true` the application is a web app, otherwise it may be a console app.
   *
   * @see $isConsoleBased
   * @var bool
   */
  public $isWebBased = false;
  /**
   * The relative path of the public folder inside a module.
   *
   * @var string
   */
  public $modulePublicPath = 'public';
  /**
   * The folder where the framework will search for your application-specific modules.
   *
   * @var String
   */
  public $modulesPath = 'private/modules';
  /**
   * The path to the folder where symlinks to all modules' public folders are placeed.
   *
   * @var string
   */
  public $modulesPublishingPath = 'modules';
  /**
   * The application name.
   * This should be composed only of alphanumeric characters. It is used as the session name.
   *
   * @var string
   */
  public $name = 'electro';
  /**
   * @var string The path to the folder where Composer packages are installed.
   */
  public $packagesPath = 'private/packages';
  /**
   * The URL parameter name used for pagination.
   *
   * @var string
   */
  public $pageNumberParam = 'p';
  /**
   * The default page size for pagination (ex: on the DataGrid). It is only applicable when the user has not yet
   * selected a custom page size.
   *
   * @var number
   */
  public $pageSize = 15;
  /**
   * <p>The fallback folder name where the framework will search for modules.
   * <p>Plugin modules installed as Composer packages will be found there.
   *
   * @var String
   */
  public $pluginsPath = 'private/plugins';
  /**
   * The name of remember me token
   *
   * @var string
   */
  public $rememberMeTokenName = "rememberMe";
  /**
   * @var string The file path of a router script for the build-in PHP web server.
   */
  public $routerFile = 'private/packages/electro/framework/devServerRouter.php';
  /**
   * @var string
   */
  public $storagePath = 'private/storage';
  /**
   * A site name that can be used on auto-generated window titles (using the title tag).
   * The symbol @ will be replaced by the current page's title.
   *
   * @var string
   */
  public $title = '@';
  /**
   * How deep is the current sub-application's base URL in relation to the main application's root URL.
   *
   * @var int
   */
  public $urlDepth;

  function __construct (InjectorInterface $injector)
  {
    $this->injector = $injector;
  }

  /**
   * Returns the directory where bootloaders for each known profile are stored.
   *
   * @return string
   */
  function getBootloadersPath ()
  {
    return "$this->storagePath/boot";
  }

  /**
   * Gets an array of file path mappings for the core framework, to aid debugging symlinked directories.
   *
   * @return array
   */
  function getMainPathMap ()
  {
    $rp = realpath ($this->frameworkPath);

    $o = [];
    if ($rp != $this->frameworkPath)
      $o[$rp] = self::FRAMEWORK_PATH;

    //TODO: register all PHP-KIT packages.
    $phpKit  = 'private/packages/php-kit/php-web-console';
    $oPath   = "$this->baseDirectory/$phpKit";
    $rPhpKit = realpath ($oPath);

    if ($rPhpKit != $oPath)
      $o[$rPhpKit] = $phpKit;

    return $o;
  }

  /**
   * Sets the application's root directory and adjusts PHP's include path accordingly; it also saves how deep is the
   * current sub-application's base URL in relation to the main application's root URL.
   *
   * @param string $rootDir     The application's root directory path.
   * @param int    $urlDepth    How many URL segments should be stripped when calculating the application's root URL.
   *                            Use it when booting a sub-application from an index.php on a sub-directory of the main
   *                            application.
   */
  function setApplicationRoot ($rootDir, $urlDepth)
  {
    $this->urlDepth = $urlDepth;
    // Note: due to eventual symlinking, we can't use dirname(__DIR__) here
    $this->baseDirectory = $rootDir;
    $this->frameworkPath = "$rootDir/" . self::FRAMEWORK_PATH;
    /*
     * Setup the include path by prepending the application's root and the framework's to it.
     * Note: the include path is used, for instance, by stream_resolve_include_path() or file_exists()
    */
    $path = get_include_path ();
    $path = substr ($path, 0, 2) == '.' . PATH_SEPARATOR
      ? $rootDir . substr ($path, 1)       // replace the '.' directory by $rootDir
      : $rootDir . PATH_SEPARATOR . $path; // otherwise prepend $rootDir to the path
    set_include_path ($path);
  }

  /**
   * Converts filesystem paths relative fron the application's base directory into absolute paths.
   * <p>Absolute paths will be returned unmodified.
   *
   * @param string $path
   * @return string
   */
  function toAbsolutePath ($path)
  {
    if ($path[0] == '/' || $path[0] == '\\')
      return $path;
    return $this->baseDirectory . DIRECTORY_SEPARATOR . $path;
  }

  /**
   * Converts absolute filesystem paths to relative paths fron the application's base directory.
   * <p>Relative paths will be returned unmodified.
   *
   * @param string $path
   * @return string
   */
  function toRelativePath ($path)
  {
    if ($path[0] == '/' || $path[0] == '\\')
      return substr ($path, strlen ($this->baseDirectory) + 1);
    return $path;
  }

}
