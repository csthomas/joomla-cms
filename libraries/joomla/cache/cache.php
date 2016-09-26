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
 * Joomla! Cache base object
 *
 * @since  11.1
 */
class JCache
{
	/**
	 * Storage handler
	 *
	 * @var    JCacheStorage[]
	 * @since  11.1
	 * @deprecated  4.0
	 */
	public static $_handler = array();

	/**
	 * Cache options
	 *
	 * @var    array
	 * @since  11.1
	 */
	public $_options;

	/**
	 * Storage handler
	 *
	 * @var    JCacheStorage[]
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $handlers = array();

	/**
	 * Cache options object.
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $handler_options;

	/**
	 * Cache handler signature.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $handler_hash;

	/**
	 * Constructor
	 *
	 * @param   array  $options  Cache options
	 *
	 * @since   11.1
	 */
	public function __construct($options)
	{
		$config = JFactory::getConfig();

		$this->handler_options = [
			'locking'   => true,
			'cachebase' => $config->get('cache_path', JPATH_CACHE),
			'lifetime'  => $config->get('cachetime'),
			'language'  => $config->get('language', 'en-GB'),
			'storage'   => $config->get('cache_handler', ''),
			'caching'   => $config->get('caching') >= 1,
			'hash'      => md5($config->get('secret')),
		];

		// Add platform key when Global Config is set to platform specific prefix
		if ($config->get('cache_platformprefix'))
		{
			$webclient = new JApplicationWebClient;

			if ($webclient->mobile)
			{
				$this->handler_options['platform_key'] = 'M';
			}
		}

		$this->_options = array_merge($this->handler_options, array(
			'defaultgroup' => 'default',
			'locktime'     => 15,
			'checkTime'    => true,
		));

		if ($this->handler_options['storage'] === 'memcache')
		{
			$this->handler_options['memcache_server_host'] = $config->get('memcache_server_host');
			$this->handler_options['memcache_server_port'] = $config->get('memcache_server_port');
			$this->handler_options['memcache_persist']     = $config->get('memcache_persist');
			$this->handler_options['memcache_compress']    = $config->get('memcache_compress');
		}
		elseif ($this->handler_options['storage'] === 'memcached')
		{
			$this->handler_options['memcached_server_host'] = $config->get('memcached_server_host');
			$this->handler_options['memcached_server_port'] = $config->get('memcached_server_port');
			$this->handler_options['memcached_persist']     = $config->get('memcached_persist');
			$this->handler_options['memcached_compress']    = $config->get('memcached_compress');
		}
		elseif ($this->handler_options['storage'] === 'redis')
		{
			$this->handler_options['redis_server_host'] = $config->get('redis_server_host');
			$this->handler_options['redis_server_host'] = $config->get('redis_server_host');
			$this->handler_options['redis_persist']     = $config->get('redis_persist');
			$this->handler_options['redis_server_auth'] = $config->get('redis_server_auth');
			$this->handler_options['redis_server_db']   = $config->get('redis_server_db');
		}

		// Overwrite default options with given options
		foreach ($options as $option => $value)
		{
			if ($value !== null && $value !== '')
			{
				if (!in_array($option, ['defaultgroup', 'locktime', 'checkTime', 'browsercache']))
				{
					$this->handler_options[$option] = $value;
				}

				$this->_options[$option] = $value;
			}
		}

		if (!$this->handler_options['storage'])
		{
			$this->setCaching(false);
		}
	}

	/**
	 * Returns a reference to a cache adapter object, always creating it
	 *
	 * @param   string  $type     The cache object type to instantiate
	 * @param   array   $options  The array of options
	 *
	 * @return  JCacheController
	 *
	 * @since   11.1
	 * @deprecated  4.0  Use JCacheController::getInstance().
	 */
	public static function getInstance($type = 'output', $options = array())
	{
		JLog::add(__METHOD__ . '() is deprecated. Use JCacheController::getInstance() instead.', JLog::WARNING, 'deprecated');

		return JCacheController::getInstance($type, $options);
	}

	/**
	 * Get the storage handlers
	 *
	 * @return  array
	 *
	 * @since   11.1
	 */
	public static function getStores()
	{
		$handlers = array();

		// Get an iterator and loop trough the driver classes.
		$iterator = new DirectoryIterator(__DIR__ . '/storage');

		/** @type  $file  DirectoryIterator */
		foreach ($iterator as $file)
		{
			$fileName = $file->getFilename();

			// Only load for php files.
			if (!$file->isFile() || $file->getExtension() != 'php' || $fileName == 'helper.php')
			{
				continue;
			}

			// Derive the class name from the type.
			$class = str_ireplace('.php', '', 'JCacheStorage' . ucfirst(trim($fileName)));

			// If the class doesn't exist we have nothing left to do but look at the next type. We did our best.
			if (!class_exists($class))
			{
				continue;
			}

			// Sweet!  Our class exists, so now we just need to know if it passes its test method.
			if ($class::isSupported())
			{
				// Connector names should not have file extensions.
				$handlers[] = str_ireplace('.php', '', $fileName);
			}
		}

		return $handlers;
	}

	/**
	 * Get the cache storage handler
	 *
	 * @return  JCacheStorage
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getStorage()
	{
		if (!$this->handler_hash)
		{
			$this->handler_hash = md5(serialize($this->handler_options));
		}

		if (isset(static::$handlers[$this->handler_hash]))
		{
			return static::$handlers[$this->handler_hash];
		}

		static::$handlers[$this->handler_hash] = JCacheStorage::getInstance(
			$this->handler_options['storage'],
			$this->handler_options
		);

		return static::$handlers[$this->handler_hash];
	}

	/**
	 * Get caching state
	 *
	 * @return  boolean
	 *
	 * @since   11.1
	 */
	public function getCaching()
	{
		return $this->handler_options['caching'];
	}

	/**
	 * Set caching enabled state
	 *
	 * @param   boolean  $enabled  True to enable caching
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function setCaching($enabled)
	{
		$this->_options['caching'] = $enabled;
		$this->handler_options['caching'] = $enabled;
		$this->handler_hash = null;
	}

	/**
	 * Get cache lifetime.
	 *
	 * @return  integer|float  Cache lifetime in minutes
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getLifeTime()
	{
		return $this->handler_options['lifetime'];
	}

	/**
	 * Set cache lifetime.
	 *
	 * @param   integer|float  $lt  Cache lifetime in minutes
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function setLifeTime($lt)
	{
		$this->_options['lifetime'] = $lt;
		$this->handler_options['lifetime'] = $lt;
		$this->handler_hash = null;
	}

	/**
	 * Get cached data by ID and group
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  mixed  Boolean false on failure or a cached data object
	 *
	 * @since   11.1
	 */
	public function get($id, $group = null)
	{
		if (!$this->getCaching())
		{
			return false;
		}

		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		$data = $this->getStorage()->get($id, $group, $this->_options['checkTime']);

		// Trim to fix unserialize errors
		return $data === false ? false : unserialize(trim($data));
	}

	/**
	 * Get a list of all cached data
	 *
	 * @return  mixed  Boolean false on failure or an object with a list of cache groups and data
	 *
	 * @since   11.1
	 */
	public function getAll()
	{
		if (!$this->getCaching())
		{
			return false;
		}

		return $this->getStorage()->getAll();
	}

	/**
	 * Store the cached data by ID and group
	 *
	 * @param   mixed   $data   The data to store
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  boolean
	 *
	 * @since   11.1
	 */
	public function store($data, $id, $group = null)
	{
		if (!$this->getCaching())
		{
			return false;
		}

		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		// Get the storage and store the cached data
		return $this->getStorage()->store($id, $group, serialize($data));
	}

	/**
	 * Remove a cached data entry by ID and group
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  boolean
	 *
	 * @since   11.1
	 */
	public function remove($id, $group = null)
	{
		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		return $this->getStorage()->remove($id, $group);
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
	 * @return  boolean  True on success, false otherwise
	 *
	 * @since   11.1
	 */
	public function clean($group = null, $mode = 'group')
	{
		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		return $this->getStorage()->clean($group, $mode);
	}

	/**
	 * Garbage collect expired cache data
	 *
	 * @return  boolean
	 *
	 * @since   11.1
	 */
	public function gc()
	{
		return $this->getStorage()->gc();
	}

	/**
	 * Set lock flag on cached item
	 *
	 * @param   string  $id        The cache data ID
	 * @param   string  $group     The cache data group
	 * @param   string  $locktime  The default locktime for locking the cache.
	 *
	 * @return  stdClass  Object with properties of lock and locklooped
	 *
	 * @since   11.1
	 */
	public function lock($id, $group = null, $locktime = null)
	{
		$returning = new stdClass;
		$returning->locklooped = false;

		if (!$this->getCaching())
		{
			$returning->locked = false;

			return $returning;
		}

		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		// Get the default locktime
		$locktime = $locktime ?: $this->_options['locktime'];

		/*
		 * Allow storage handlers to perform locking on their own
		 * NOTE drivers with lock need also unlock or unlocking will fail because of false $id
		 */

		if ($this->_options['locking'] == true)
		{
			$locked = $this->getStorage()->lock($id, $group, $locktime);

			if ($locked !== false)
			{
				return $locked;
			}
		}

		// Fallback
		$curentlifetime = $this->getLifeTime();

		$this->setLifeTime($locktime/60);

		$looptime = $locktime * 10;
		$id2      = $id . '_lock';

		if ($this->_options['locking'] == true)
		{
			$data_lock = $this->getStorage()->get($id2, $group, $this->_options['checkTime']);
		}
		else
		{
			$data_lock         = false;
			$returning->locked = false;
		}

		if ($data_lock !== false)
		{
			$lock_counter = 0;

			// Loop until you find that the lock has been released. That implies that data get from other thread has finished
			while ($data_lock !== false)
			{
				if ($lock_counter > $looptime)
				{
					$returning->locked = false;
					$returning->locklooped = true;
					break;
				}

				usleep(100);
				$data_lock = $this->getStorage()->get($id2, $group, $this->_options['checkTime']);
				$lock_counter++;
			}
		}

		if ($this->_options['locking'] == true)
		{
			$returning->locked = $this->getStorage()->store(1, $id2, $group);
		}

		$this->setLifeTime($curentlifetime);

		return $returning;
	}

	/**
	 * Unset lock flag on cached item
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  boolean
	 *
	 * @since   11.1
	 */
	public function unlock($id, $group = null)
	{
		if (!$this->getCaching())
		{
			return false;
		}

		$unlock = false;

		// Get the default group
		$group = $group ?: $this->_options['defaultgroup'];

		// Allow handlers to perform unlocking on their own
		$unlocked = $this->getStorage()->unlock($id, $group);

		if ($unlocked !== false)
		{
			return $unlocked;
		}

		return $this->remove($id . '_lock', $group);
	}

	/**
	 * Get the cache storage handler
	 *
	 * @return  JCacheStorage
	 *
	 * @since   11.1
	 * @deprecated  4.0  Use JCacheController::getStorage().
	 */
	public function _getStorage()
	{
		JLog::add(
			__METHOD__ . '() is deprecated. Use JCache::getStorage() instead.',
			JLog::WARNING,
			'deprecated'
		);

		static::$_handler = &static::$handlers;

		return $this->getStorage();
	}

	/**
	 * Perform workarounds on retrieved cached data
	 *
	 * @param   string  $data     Cached data
	 * @param   array   $options  Array of options
	 *
	 * @return  string  Body of cached data
	 *
	 * @since   11.1
	 * @deprecated  4.0  Use JCacheController::getWorkarounds().
	 */
	public static function getWorkarounds($data, $options = array())
	{
		JLog::add(
			__METHOD__ . '() is deprecated. Use JCacheController::getWorkarounds() instead.',
			JLog::WARNING,
			'deprecated'
		);

		return JCacheController::getWorkarounds($data, $options);
	}

	/**
	 * Create workarounds for data to be cached
	 *
	 * @param   string  $data     Cached data
	 * @param   array   $options  Array of options
	 *
	 * @return  string  Data to be cached
	 *
	 * @since   11.1
	 * @deprecated  4.0  Use JCacheController::setWorkarounds().
	 */
	public static function setWorkarounds($data, $options = array())
	{
		JLog::add(
			__METHOD__ . '() is deprecated. Use JCacheController::setWorkarounds() instead.',
			JLog::WARNING,
			'deprecated'
		);

		return JCacheController::setWorkarounds($data, $options);
	}

	/**
	 * Create a safe ID for cached data from URL parameters
	 *
	 * @return  string  MD5 encoded cache ID
	 *
	 * @since   11.1
	 */
	public static function makeId()
	{
		$app = JFactory::getApplication();

		$registeredurlparams = new stdClass;

		// Get url parameters set by plugins
		if (!empty($app->registeredurlparams))
		{
			$registeredurlparams = $app->registeredurlparams;
		}

		// Platform defaults
		$defaulturlparams = array(
			'format' => 'WORD',
			'option' => 'WORD',
			'view'   => 'WORD',
			'layout' => 'WORD',
			'tpl'    => 'CMD',
			'id'     => 'INT',
		);

		// Use platform defaults if parameter doesn't already exist.
		foreach ($defaulturlparams as $param => $type)
		{
			if (!property_exists($registeredurlparams, $param))
			{
				$registeredurlparams->$param = $type;
			}
		}

		$safeuriaddon = new stdClass;

		foreach ($registeredurlparams as $key => $value)
		{
			$safeuriaddon->$key = $app->input->get($key, null, $value);
		}

		return md5(serialize($safeuriaddon));
	}

	/**
	 * Set a prefix cache key if device calls for separate caching
	 *
	 * @return  string
	 *
	 * @since   3.5
	 * @deprecated  4.0
	 */
	public static function getPlatformPrefix()
	{
		JLog::add(__METHOD__ . '() is deprecated.', JLog::WARNING, 'deprecated');

		// No prefix when Global Config is set to no platfom specific prefix
		if (!JFactory::getConfig()->get('cache_platformprefix', '0'))
		{
			return '';
		}

		$webclient = new JApplicationWebClient;

		if ($webclient->mobile)
		{
			return 'M-';
		}

		return '';
	}

	/**
	 * Add a directory where JCache should search for handlers. You may either pass a string or an array of directories.
	 *
	 * @param   array|string  $path  A path to search.
	 *
	 * @return  array   An array with directory elements
	 *
	 * @since   11.1
	 * @deprecated  4.0
	 */
	public static function addIncludePath($path = '')
	{
		static $paths;

		if (!isset($paths))
		{
			$paths = array();
		}

		if (!empty($path) && !in_array($path, $paths))
		{
			jimport('joomla.filesystem.path');
			array_unshift($paths, JPath::clean($path));
		}

		return $paths;
	}
}
