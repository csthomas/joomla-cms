<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Cache
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Memcached cache storage handler
 *
 * @see    https://secure.php.net/manual/en/book.memcached.php
 * @since  12.1
 */
class JCacheStorageMemcached extends JCacheStorage
{
	/**
	 * Memcached connection object
	 *
	 * @var    Memcached
	 * @since  12.1
	 */
	protected static $_db = null;

	/**
	 * Compression flag
	 *
	 * @var    boolean
	 * @since  12.1
	 */
	protected $_compress = false;

	/**
	 * Constructor
	 *
	 * @param   array  $options  Optional parameters.
	 *
	 * @since   12.1
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		$this->_compress = (bool) JFactory::getConfig()->get('memcached_compress', false);

		if (static::$_db === null)
		{
			$this->getConnection();
		}
	}

	/**
	 * Create the Memcached connection
	 *
	 * @return  void
	 *
	 * @since   12.1
	 * @throws  RuntimeException
	 */
	protected function getConnection()
	{
		if (!static::isSupported())
		{
			throw new RuntimeException('Memcached Extension is not available');
		}

		$config = JFactory::getConfig();

		$host = $config->get('memcached_server_host', 'localhost');
		$port = $config->get('memcached_server_port', 11211);


		// Create the memcached connection
		if ($config->get('memcached_persist', true))
		{
			static::$_db = new Memcached($this->_hash);
			$servers = static::$_db->getServerList();

			if ($servers && ($servers[0]['host'] != $host || $servers[0]['port'] != $port))
			{
				static::$_db->resetServerList();
				$servers = array();
			}

			if (!$servers)
			{
				static::$_db->addServer($host, $port);
			}
		}
		else
		{
			static::$_db = new Memcached;
			static::$_db->addServer($host, $port);
		}

		static::$_db->setOption(Memcached::OPT_COMPRESSION, $this->_compress);

		$stats  = static::$_db->getStats();
		$result = !empty($stats["$host:$port"]) && $stats["$host:$port"]['pid'] > 0;

		if (!$result)
		{
			// Null out the connection to inform the constructor it will need to attempt to connect if this class is instantiated again
			static::$_db = null;

			throw new JCacheExceptionConnecting('Could not connect to memcached server');
		}
	}

	/**
	 * Get a cache_id string from an id/group pair
	 *
	 * @param   string  $id     The cache data id
	 * @param   string  $group  The cache data group
	 *
	 * @return  string   The cache_id string
	 *
	 * @since   11.1
	 */
	protected function _getCacheId($id, $group)
	{
		$prefix   = JCache::getPlatformPrefix();
		$length   = strlen($prefix);
		$cache_id = parent::_getCacheId($id, $group);

		if ($length)
		{
			// Memcached use suffix instead of prefix
			$cache_id = substr($cache_id, $length) . strrev($prefix);
		}

		return $cache_id;
	}

	/**
	 * Get cached data by ID and group
	 *
	 * @param   string   $id         The cache data ID
	 * @param   string   $group      The cache data group
	 * @param   boolean  $checkTime  True to verify cache time expiration threshold
	 *
	 * @return  mixed  Boolean false on failure or a cached data object
	 *
	 * @since   12.1
	 */
	public function get($id, $group, $checkTime = true)
	{
		return static::$_db->get($this->_getCacheId($id, $group));
	}

	/**
	 * Get all cached data
	 *
	 * @return  mixed  Boolean false on failure or a cached data object
	 *
	 * @since   12.1
	 */
	public function getAll()
	{
		$keys   = static::$_db->get($this->_hash . '-index');
		$secret = $this->_hash;

		$data = array();

		if (is_array($keys))
		{
			foreach ($keys as $key)
			{
				if (empty($key))
				{
					continue;
				}

				$namearr = explode('-', $key->name);

				if ($namearr !== false && $namearr[0] == $secret && $namearr[1] == 'cache')
				{
					$group = $namearr[2];

					if (!isset($data[$group]))
					{
						$item = new JCacheStorageHelper($group);
					}
					else
					{
						$item = $data[$group];
					}

					$item->updateSize($key->size / 1024);

					$data[$group] = $item;
				}
			}
		}

		return $data;
	}

	/**
	 * Store the data to cache by ID and group
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 * @param   string  $data   The data to store in cache
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	public function store($id, $group, $data)
	{
		$cache_id = $this->_getCacheId($id, $group);

		if (!$this->lockindex())
		{
			return false;
		}

		$index = static::$_db->get($this->_hash . '-index');

		if (!is_array($index))
		{
			$index = array();
		}

		$tmparr       = new stdClass;
		$tmparr->name = $cache_id;
		$tmparr->size = strlen($data);

		$index[] = $tmparr;
		static::$_db->set($this->_hash . '-index', $index, 0);
		$this->unlockindex();

		static::$_db->set($cache_id, $data, $this->_lifetime);

		return true;
	}

	/**
	 * Remove a cached data entry by ID and group
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	public function remove($id, $group)
	{
		$cache_id = $this->_getCacheId($id, $group);

		if (!$this->lockindex())
		{
			return false;
		}

		$index = static::$_db->get($this->_hash . '-index');

		if (is_array($index))
		{
			foreach ($index as $key => $value)
			{
				if ($value->name == $cache_id)
				{
					unset($index[$key]);
					static::$_db->set($this->_hash . '-index', $index, 0);
					break;
				}
			}
		}

		$this->unlockindex();

		return static::$_db->delete($cache_id);
	}

	/**
	 * Clean cache for a group given a mode.
	 *
	 * group mode    : cleans all cache in the group
	 * notgroup mode : cleans all cache not in the group
	 *
	 * @param   string  $group  The cache data group
	 * @param   string  $mode   The mode for cleaning cache [group|notgroup]
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	public function clean($group, $mode = null)
	{
		if (!$this->lockindex())
		{
			return false;
		}

		$index = static::$_db->get($this->_hash . '-index');

		if (is_array($index))
		{
			$prefix = $this->_hash . '-cache-' . $group . '-';

			foreach ($index as $key => $value)
			{
				if (strpos($value->name, $prefix) === 0 xor $mode != 'group')
				{
					static::$_db->delete($value->name);
					unset($index[$key]);
				}
			}

			static::$_db->set($this->_hash . '-index', $index, 0);
		}

		$this->unlockindex();

		return true;
	}

	/**
	 * Flush all existing items in storage.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function flush()
	{
		if (!$this->lockindex())
		{
			return false;
		}

		return static::$_db->flush();
	}

	/**
	 * Test to see if the storage handler is available.
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	public static function isSupported()
	{
		/*
		 * GAE and HHVM have both had instances where Memcached the class was defined but no extension was loaded.
		 * If the class is there, we can assume support.
		 */
		return class_exists('Memcached');
	}

	/**
	 * Lock cached item
	 *
	 * @param   string   $id        The cache data ID
	 * @param   string   $group     The cache data group
	 * @param   integer  $locktime  Cached item max lock time
	 *
	 * @return  mixed  Boolean false if locking failed or an object containing properties lock and locklooped
	 *
	 * @since   12.1
	 */
	public function lock($id, $group, $locktime)
	{
		$returning = new stdClass;
		$returning->locklooped = false;

		$looptime = $locktime * 10;

		$cache_id = $this->_getCacheId($id, $group);

		$data_lock = static::$_db->add($cache_id . '_lock', 1, $locktime);

		if ($data_lock === false)
		{
			$lock_counter = 0;

			// Loop until you find that the lock has been released.
			// That implies that data get from other thread has finished.
			while ($data_lock === false)
			{
				if ($lock_counter > $looptime)
				{
					break;
				}

				usleep(100);
				$data_lock = static::$_db->add($cache_id . '_lock', 1, $locktime);
				$lock_counter++;
			}

			$returning->locklooped = true;
		}

		$returning->locked = $data_lock;

		return $returning;
	}

	/**
	 * Unlock cached item
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	public function unlock($id, $group = null)
	{
		$cache_id = $this->_getCacheId($id, $group) . '_lock';
		return static::$_db->delete($cache_id);
	}

	/**
	 * Lock cache index
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	protected function lockindex()
	{
		$looptime  = 300;
		$data_lock = static::$_db->add($this->_hash . '-index_lock', 1, 30);

		if ($data_lock === false)
		{
			$lock_counter = 0;

			// Loop until you find that the lock has been released.  that implies that data get from other thread has finished
			while ($data_lock === false)
			{
				if ($lock_counter > $looptime)
				{
					return false;
				}

				usleep(100);
				$data_lock = static::$_db->add($this->_hash . '-index_lock', 1, 30);
				$lock_counter++;
			}
		}

		return true;
	}

	/**
	 * Unlock cache index
	 *
	 * @return  boolean
	 *
	 * @since   12.1
	 */
	protected function unlockindex()
	{
		return static::$_db->delete($this->_hash . '-index_lock');
	}
}
