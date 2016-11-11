<?php
namespace Electro\Kernel\Services;

use Electro\Exceptions\Fatal\ConfigException;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Interfaces\ProfileInterface;
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

  /**
   * @var bool|null The value set on the .env file for the DEBUG variable. When null, reading {@see getDebugMode} will
   *                set it to the correct value.
   */
  private $debugMode = null;
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

  function __construct (InjectorInterface $injector, ProfileInterface $profile)
  {
    $this->injector = $injector;
    $this->profile  = $profile;
  }

  function boot ()
  {
    /*
     * Load all remaining modules, allowing them to subscribe to bootstrap events.
     */
    $exclude    = array_flip ($this->profile->getExcludedModules ());
    $subsystems = array_flip ($this->profile->getSubsystems ());

    /** @var ModulesRegistry $registry */
    $registry = $this->injector->make (ModulesRegistry::class);

    foreach ($registry->onlyBootable ()->onlyEnabled ()->getModules () as $name => $module) {
      /** @var ModuleInfo $module */
      if (isset ($exclude[$module->name]) ||
          ($module->type == ModuleInfo::TYPE_SUBSYSTEM && !isset($subsystems[$module->name]))
      ) continue;
      $modBoot = $module->bootstrapper;
      /** @var ModuleInterface|string $modBoot */
      if (!class_exists ($modBoot)) // don't load this module.
        throw new ConfigException("Class $modBoot was not found.");
      elseif (is_a ($modBoot, ModuleInterface::class, true))
        $modBoot::startUp ($this, $module);
      //else ignore the module
    }

    /**
     * Boot up all non-core modules.
     */

    $this->emit (PRE_REGISTER, $this->injector);
    $this->emit (REGISTER_SERVICES, $this->injector);
    $this->emitAndInject (CONFIGURE);
    $this->emitAndInject (RECONFIGURE);
    $this->emitAndInject (RUN);
    $this->emitAndInject (SHUTDOWN);
  }

  function isDevEnv ()
  {
    return is_null ($this->debugMode) ? $this->debugMode = getenv ('DEBUG') == 'true' : $this->debugMode;
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
