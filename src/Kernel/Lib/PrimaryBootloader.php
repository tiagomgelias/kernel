<?php

namespace Electro\Kernel\Lib;

use Electro\ConsoleApplication\ConsoleApplication;
use Electro\Interfaces\BootloaderInterface;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ProfileInterface;
use Electro\Profiles\ConsoleProfile;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A class that implements the application's initial bootstrapping sequence, which sets everything else in motion.
 *
 * <p>It is responsible for launching both web and console based applications.
 * <p>It loads a 2nd level bootloader that is specific for the kind of application being run (which is selected by the
 * configuration profile specified when calling {@see boot}).
 */
class PrimaryBootloader
{
  /**
   * The original 'current working directory'. It can be used later by workman commands or other CLI sctipts if the app
   * is lauched from a directory other than the app's root.
   *
   * @var string
   */
  public $cwd;
  /**
   * @var string The application's root directory.
   */
  public $root;

  /**
   * Initializes the Bootloader instance.
   */
  function __construct ()
  {
    $this->cwd = getcwd ();
  }

  /**
   * A factory method that creates, sets up and returns a bootloader instance, ready for booting up.
   *
   * <p>This is useful for booting up the application without creating a global variable to hold the bootloader
   * instance.
   *
   * <p>After calling this, you are free to reference any class on the application, or do any kind of operation prior
   * to calling {@see boot}(), which will continue the boot up sequence.
   *
   * #### Example
   * You may run a web application using a single statement:
   *       App\Bootloader::make ()->boot (Electro\Profiles\WebProfile::class);
   * Of course, for this example to work, a `require` statement for `Bootloader.php` would be needed prior to the
   * statement, as the autoloader is not yet available at the time the statement runs.
   *
   * @return static
   */
  static function make ()
  {
    return (new static)->setup ();
  }

  /**
   * @internal
   * This is called by Composer on the `post-install` event.
   *
   * @param Composer\Script\PackageEvent $event
   * @return int
   */
  static function runInitCommand ($event)
  {
    return static::runCommand ('init', [], $event);
  }
  
  /**
   * @internal
   * This is called by Composer on the `pre-package-uninstall` event.
   *
   * @param Composer\Script\PackageEvent $event
   * @return int
   */
  static function runUninstallCommand ($event)
  {
    $package = $event->getOperation ()->getPackage ();
    return static::runCommand ('module:cleanup', ['-s', $package->getName ()], $event);
  }

  /**
   * @internal
   * This is called by Composer on the `post-update` event.
   *
   * @param Composer\Script\PackageEvent $event
   * @return int
   */
  static function runUpdateCommand ($event)
  {
    return static::runCommand ('module:refresh', [], $event);
  }

  /**
   * Runs a console command from within a Composer execution context.
   *
   * @param string                       $name Command name.
   * @param string[]                     $args Command arguments.
   * @param Composer\Script\PackageEvent $event
   * @return int
   */
  static private function runCommand ($name, $args = [], $event = null)
  {
    return static::make ()->boot (new ConsoleProfile, 0,
      function (KernelInterface $kernel) use ($name, $args, $event) {
        $kernel->onConfigure (function (ConsoleApplication $consoleApp) use ($name, $args, $event) {
          $consoleApp->runCommand ($name, $args, $event);
        });
      });
  }

  /**
   * Boots up the framework and runs the application.
   *
   * @param ProfileInterface $profile   A configuration profile instance.
   * @param int              $urlDepth  How many URL segments should be stripped when calculating the application's root
   *                                    URL.
   *                                    Use it when booting a sub-application from an index.php on a sub-directory of
   *                                    the main application.
   * @param callable         $onStartUp If specified, the callback will be invoked right before the kernel boots up. It
   *                                    will be given the kernel instance as an argument, so that you can use this to
   *                                    register listeners for kernel events, similar to what
   *                                    {@seeModuleInterface::startUp} does for modules.
   * @return int Exit status code. Only meaningful for console applications.
   */
  function boot (ProfileInterface $profile, $urlDepth = 0, callable $onStartUp = null)
  {
    // Initialize the injector with services defined on the profile.

    /** @var InjectorInterface $injector */
    $injector = $profile->getInjector ();
    $injector
      ->share ($injector)
      ->alias (InjectorInterface::class, get_class ($injector))
      ->share ($profile)
      ->alias (ProfileInterface::class, get_class ($profile))
      ->alias (KernelInterface::class, $profile->getKernelClass ());

    // Create and run the bootloader defined by the profile.

    $bootloaderClass = $profile->getBootloaderClass ();
    /** @var BootloaderInterface $bootloader */
    $bootloader = new $bootloaderClass ($injector);
    return $bootloader->boot ($this->root, $urlDepth, $onStartUp);
  }

  /**
   * Sets up the application's bootstrapping environment.
   *
   * - It initializes global configuration options for the PHP execution environment (ex. current directory, some INI
   * settings).
   * - It loads the class autoloader and the `.env` file (if one exists).
   *
   * <p>After calling this, you are free to reference any class on the application, or do any kind of operation prior
   * to calling {@see boot}(), which will continue the boot up sequence.
   *
   * @return $this Self, for chaining.
   */
  function setup ()
  {
    /*
     * On some web servers, the current directory may not be the application's root directory.
     * We need to make sure the current directory is the app's root so that all includes run flawlessly.
     */
    chdir ($this->root);

    mb_internal_encoding ("UTF-8"); // override ini.default_charset

    /*
     * You may temporarily uncomment the following line for troubleshooting on restricted hosting environments.
     * It enables error logging to a file on the project's root directory.
    */
    //ini_set ('error_log', $this->root . '/error.log');

    /*
     * Start Composer's autoloader.
     */
    if (!file_exists ("private/packages/autoload.php")) {
      $msg = "<h3>Project not installed</h3>
Please run <b><kbd>composer install</kbd></b> on the command line
";;
      echo defined ('STDIN') && !isset($_SERVER['REQUEST_METHOD']) ? preg_replace ('/<.*?>/', '', $msg) : $msg;
      exit (1);
    }
    include "private/packages/autoload.php";
    return $this;
  }

}
