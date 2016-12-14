<?php
namespace Electro\Kernel\Services;

use Electro\Exceptions\Fatal\ConfigException;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Interfaces\ProfileInterface;
use Electro\Kernel\Config\KernelSettings;
use Electro\Kernel\Lib\ModuleInfo;
use Electro\Traits\EventEmitterTrait;

/**
 * Use this event for overriding core framework services.
 */
const PRE_REGISTER = 0;
/**
 * Use this event for registering a module's services on the injector.
 */
const REGISTER_SERVICES = 1;
/**
 * Use this event for configuring services.
 */
const CONFIGURE = 2;
/**
 * Use this event for performing additional initialization/configuration steps.
 */
const RECONFIGURE = 3;
/**
 * Use this event for performing "useful work" after all module initializations were performed.
 */
const RUN = 4;
/**
 * Use this event for performing cleanup operations after all "useful work" has been performed and just before the
 * application finishes.
 */
const SHUTDOWN = 5;

/**
 * The service that loads the bulk of the framework code and the application's modules.
 *
 * <p>Modules should use this service to subscribe to startup events (see the `Electro\Kernel\Services` constants).
 */
class Kernel implements KernelInterface
{
  use EventEmitterTrait;

  /** @var int */
  private $exitCode = 0;
  /**
   * @var InjectorInterface
   */
  private $injector;
  /**
   * @var ProfileInterface
   */
  private $profile;
  /**
   * @var KernelSettings
   */
  private $settings;

  function __construct (InjectorInterface $injector, ProfileInterface $profile, KernelSettings $settings)
  {
    $this->injector = $injector;
    $this->profile  = $profile;
    $this->settings = $settings;
  }

  function boot ()
  {
    /*
     * Load all botable modules, allowing them to subscribe to lifecycle events.
     */
    $exclude = array_flip ($this->profile->getExcludedModules ());
    $include = array_flip ($this->profile->getIncludedModules ());

    /** @var ModulesRegistry $registry */
    $registry = $this->injector->make (ModulesRegistry::class);

    foreach ($registry->onlyBootable ()->onlyEnabled ()->getModules () as $name => $module) {
      /** @var ModuleInfo $module */

      // Skip module if it's blacklisted.
      if (isset ($exclude[$module->name]))
        continue;

      /** @var ModuleInterface|string $modBoot */
      $modBoot = $module->bootstrapper;

      // Don't try to load modules that have no bootstraper class.
      if (!$modBoot)
        continue;

      if (!class_exists ($modBoot))
        throw new ConfigException("Class '$modBoot' from module '$module->name' was not found.", -1);

      if (!is_a ($modBoot, ModuleInterface::class, true))
        throw new ConfigException("Class '$modBoot' does not implement '" . ModuleInterface::class . "'.");

      // Boot module immediately if it's whitelisted.
      if (isset($include[$module->name]))
        $modBoot::startUp ($this, $module);

      // Boot the module only if it's compatible with the current profile.
      else {
        $compat = $modBoot::getCompatibleProfiles ();
        foreach ($compat as $class)
          if ($this->profile instanceof $class) {
            $modBoot::startUp ($this, $module);
            break;
          }
      }

      // Loop to next module.
    }

    /**
     * Run the application lifecycle stages.
     */

    $this->emit (PRE_REGISTER, $this->injector);
    $this->emit (REGISTER_SERVICES, $this->injector);
    $this->emitAndInject (CONFIGURE);
    $this->emitAndInject (RECONFIGURE);
    $this->emitAndInject (RUN);
    $this->emitAndInject (SHUTDOWN);
  }

  function devEnv ()
  {
    return $this->settings->devEnv;
  }

  /**
   * Gets the exit status code that will be returned to the operating system when the program ends.
   *
   * <p>This is only relevant for console applications.
   *
   * @return int 0 if everything went fine, or an error code.
   */
  function getExitCode ()
  {
    return $this->exitCode;
  }

  /**
   * Sets the exit status code that will be returned to the operating system when the program ends.
   *
   * <p>This is only relevant for console applications.
   *
   * @param int $code 0 if everything went fine, or an error code.
   * @return void
   */
  function setExitCode ($code)
  {
    $this->exitCode = $code;
  }

  function getProfile ()
  {
    return $this->profile;
  }

  function onConfigure (callable $handler)
  {
    return $this->on (CONFIGURE, $handler);
  }

  function onPreRegister (callable $handler)
  {
    return $this->on (PRE_REGISTER, $handler);
  }

  function onReconfigure (callable $handler)
  {
    return $this->on (RECONFIGURE, $handler);
  }

  function onRegisterServices (callable $handler)
  {
    return $this->on (REGISTER_SERVICES, $handler);
  }

  function onRun (callable $handler)
  {
    return $this->on (RUN, $handler);
  }

  function onShutdown (callable $handler)
  {
    return $this->on (SHUTDOWN, $handler);
  }

  /**
   * Emits an event to all handlers registered to that event (if any), injecting the arguments to each calling handler.
   *
   * @param string $event The event name.
   */
  protected function emitAndInject ($event)
  {
    foreach (get ($this->listeners, $event, []) as $l)
      $this->injector->execute ($l);
  }

}
