<?php

namespace Electro\Lib;

/**
 * Allows reading and writing to a composer.json data structure.
 *
 * <p>All data is read or written as associative arrays.
 * <p>When instantiating this class, data is immediately loaded from disk.
 */
class ComposerConfigHandler extends JsonFile
{
  function __construct ($composerJsonPath = null, $noError = false)
  {
    $composerJsonPath = $composerJsonPath ?: 'composer.json';
    parent::__construct ($composerJsonPath, true, true);
    if (!$this->exists ()) {
      if (!$noError)
        throw new \RuntimeException("$composerJsonPath was not found");
    }
    else $this->load ();
  }

  /**
   * Adds a package to the composer.json's `require` section.
   *
   * @param string $package
   * @param string $version
   * @return $this
   */
  function requirePackage ($package, $version)
  {
    $require           = $this->get ('require', []);
    $require[$package] = $version;
    $this->set ('require', $require);
    return $this;
  }

  /**
   * Removes a package from the composer.json's `require` section.
   *
   * @param string $moduleName
   * @return $this
   */
  function unrequirePackage ($moduleName)
  {
    $require = $this->get ('require', []);
    unset ($require[$moduleName]);
    $this->set ('require', $require);
    return $this;
  }

}
