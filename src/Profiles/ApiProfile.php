<?php
namespace Electro\Profiles;

use Electro\DependencyInjection\Injector;
use Electro\Interfaces\ProfileInterface;
use Electro\Kernel\Services\Kernel;
use Electro\WebServer\WebBootloader;

/**
 * A configuration profile tailored for web application APIs.
 */
class ApiProfile implements ProfileInterface
{
  public function getBootloaderClass ()
  {
    return WebBootloader::class;
  }

  public function getExcludedModules ()
  {
    return [];
  }

  public function getIncludedModules ()
  {
    return [];
  }

  public function getInjector ()
  {
    return new Injector;
  }

  public function getKernelClass ()
  {
    return Kernel::class;
  }

  public function getName ()
  {
    return str_segmentsLast (static::class, '\\');
  }
}
