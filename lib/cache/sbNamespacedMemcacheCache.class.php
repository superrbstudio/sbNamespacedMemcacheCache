<?php

/**
 * Extend sfMemcacheCache to allow namespacing
 */
class sbNamespacedMemcacheCache extends sfMemcacheCache 
{
  /**
   * Override the initialize to use the sbNamespacedMemcache class
   * Will throw an error if the prefix option is not set
   * 
   * @param type $options
   * @throws sfInitializationException
   */
  public function initialize($options = array())
  {
    parent::initialize($options);

    if (!class_exists('sbNamespacedMemcache'))
    {
      throw new sfInitializationException('You must have namespaced memcache installed and enabled to use sbNamespacedMemcacheCache class.');
    }
    
    if(!$this->getOption('prefix') or $this->getOption('prefix') == '')
    {
      throw new sfInitializationException('You must set the prefix to be able to use sbNamespacedMemcacheCache');
    }

    if ($this->getOption('memcache'))
    {
      $this->memcache = $this->getOption('memcache');
    }
    else
    {
      $this->memcache = new sbNamespacedMemcache();

      if ($this->getOption('servers'))
      {
        foreach ($this->getOption('servers') as $server)
        {
          $port = isset($server['port']) ? $server['port'] : 11211;
          if (!$this->memcache->addServer($server['host'], $port, isset($server['persistent']) ? $server['persistent'] : true))
          {
            throw new sfInitializationException(sprintf('Unable to connect to the memcache server (%s:%s).', $server['host'], $port));
          }
        }
      }
      else
      {
        $method = $this->getOption('persistent', true) ? 'pconnect' : 'connect';
        if (!$this->memcache->$method($this->getOption('host', 'localhost'), $this->getOption('port', 11211), $this->getOption('timeout', 1)))
        {
          throw new sfInitializationException(sprintf('Unable to connect to the memcache server (%s:%s).', $this->getOption('host', 'localhost'), $this->getOption('port', 11211)));
        }
      }
    }
  }
  
  /**
  * @see sbNamespacedMemcache
  */
  public function get($key, $default = null)
  {
    $value = $this->memcache->ns_get($this->getOption('prefix'), $key);

    return false === $value ? $default : $value;
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function has($key)
  {
    return !(false === $this->memcache->ns_get($this->getOption('prefix'), $key));
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function set($key, $data, $lifetime = null)
  {
    $lifetime = null === $lifetime ? $this->getOption('lifetime') : $lifetime;

    // save metadata
    $this->setMetadata($key, $lifetime);

    // save key for removePattern()
    if ($this->getOption('storeCacheInfo', false))
    {
      $this->setCacheInfo($key);
    }

    if (false !== $this->memcache->ns_replace($this->getOption('prefix'), $key, $data, false, time() + $lifetime))
    {
      return true;
    }

    return $this->memcache->ns_set($this->getOption('prefix'), $key, $data, false, time() + $lifetime);
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function remove($key)
  {
    // delete metadata
    $this->memcache->ns_delete($this->getOption('prefix'), '_metadata'.self::SEPARATOR.$key, 0);
    if ($this->getOption('storeCacheInfo', false))
    {
      $this->setCacheInfo($key, true);
    }
    return $this->memcache->ns_delete($this->getOption('prefix'), $key, 0);
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function clean($mode = sfCache::ALL)
  {
    if (sfCache::ALL === $mode)
    {
      return $this->memcache->ns_flush($this->getOption('prefix'));
    }
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function removePattern($pattern)
  {
    /* This method is currently not supported */
    throw new Exception('The method sbNamespacedMemcacheCache::removePattern() is currently not supported');
  }
  
  /**
   * @see sbNamespacedMemcache
   */
  public function getMany($keys)
  {
    $values = array();
    
    foreach($keys as $key)
    {
      $value = $this->memcache->ns_get($this->getOption('prefix'), $key);
      
      if($value)
      {
        $values[$key] = $value;
      }
    }

    return $values;
  }
  
  /**
   * Gets metadata about a key in the cache.
   *
   * @param string $key A cache key
   *
   * @return array An array of metadata information
   */
  protected function getMetadata($key)
  {
    return $this->memcache->ns_get($this->getOption('prefix'), '_metadata'.self::SEPARATOR.$key);
  }

  /**
   * Stores metadata about a key in the cache.
   *
   * @param string $key      A cache key
   * @param string $lifetime The lifetime
   */
  protected function setMetadata($key, $lifetime)
  {
    $this->memcache->ns_set($this->getOption('prefix'), '_metadata'.self::SEPARATOR.$key, array('lastModified' => time(), 'timeout' => time() + $lifetime), false, $lifetime);
  }
  
  /**
   * Updates the cache information for the given cache key.
   *
   * @param string $key The cache key
   * @param boolean $delete Delete key or not
   */
  protected function setCacheInfo($key, $delete = false)
  {
    $keys = $this->memcache->ns_get($this->getOption('prefix'), '_metadata');
    if (!is_array($keys))
    {
      $keys = array();
    }

    if ($delete)
    {
       if (($k = array_search($key, $keys)) !== false)
       {
         unset($keys[$k]);
       }
    }
    else
    {
      if (!in_array($key, $keys))
      {
        $keys[] = $key;
      }
    }

    $this->memcache->ns_set($this->getOption('prefix'), '_metadata', $keys, 0);
  }

  /**
   * Gets cache information.
   */
  protected function getCacheInfo()
  {
    $keys = $this->memcache->ns_get($this->getOption('prefix'), '_metadata');
    if (!is_array($keys))
    {
      return array();
    }

    return $keys;
  }
}
