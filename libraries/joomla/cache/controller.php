<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Cache
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

use Joomla\String\StringHelper;

/**
 * Public cache handler
 *
 * @since  11.1
 */
class JCacheController
{
	/**
	 * JCache object
	 *
	 * @var    JCache
	 * @since  11.1
	 */
	public $cache;

	/**
	 * Array of options
	 *
	 * @var    array
	 * @since  11.1
	 */
	public $options;

	/**
	 * Constructor
	 *
	 * @param   array  $options  Array of options
	 *
	 * @since   11.1
	 */
	public function __construct($options)
	{
		$this->cache = new JCache($options);
		$this->options = & $this->cache->_options;
	}

	/**
	 * Magic method to proxy JCacheController method calls to JCache
	 *
	 * @param   string  $name       Name of the function
	 * @param   array   $arguments  Array of arguments for the function
	 *
	 * @return  mixed
	 *
	 * @since   11.1
	 */
	public function __call($name, $arguments)
	{
		return call_user_func_array(array($this->cache, $name), $arguments);
	}

	/**
	 * Returns a reference to a cache adapter object, always creating it
	 *
	 * @param   string  $type     The cache object type to instantiate; default is output.
	 * @param   array   $options  Array of options
	 *
	 * @return  JCacheController
	 *
	 * @since   11.1
	 * @throws  RuntimeException
	 */
	public static function getInstance($type = 'output', $options = array())
	{
		self::addIncludePath(JPATH_PLATFORM . '/joomla/cache/controller');

		$type = strtolower(preg_replace('/[^A-Z0-9_\.-]/i', '', $type));

		$class = 'JCacheController' . ucfirst($type);

		if (!class_exists($class))
		{
			// Search for the class file in the JCache include paths.
			jimport('joomla.filesystem.path');

			$path = JPath::find(self::addIncludePath(), strtolower($type) . '.php');

			if ($path !== false)
			{
				include_once $path;
			}

			// The class should now be loaded
			if (!class_exists($class))
			{
				throw new RuntimeException('Unable to load Cache Controller: ' . $type, 500);
			}
		}

		return new $class($options);
	}

	/**
	 * Add a directory where JCache should search for controllers. You may either pass a string or an array of directories.
	 *
	 * @param   array|string  $path  A path to search.
	 *
	 * @return  array  An array with directory elements
	 *
	 * @since   11.1
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

	/**
	 * Get stored cached data by ID and group
	 *
	 * @param   string  $id     The cache data ID
	 * @param   string  $group  The cache data group
	 *
	 * @return  mixed  Boolean false on no result, cached object otherwise
	 *
	 * @since   11.1
	 * @deprecated  4.0  Write own method in subclass
	 */
	public function get($id, $group = null)
	{
		$data = $this->cache->get($id, $group);

		if ($data === false)
		{
			$lock = $this->cache->lock($id, $group);

			// If locklooped is true try to get the cached data again; it could exist now.
			if ($lock->locked === true && $lock->locklooped === true)
			{
				$data = $this->cache->get($id, $group);
			}

			if ($lock->locked === true)
			{
				$this->cache->unlock($id, $group);
			}
		}

		return $data;
	}

	/**
	 * Store data to cache by ID and group
	 *
	 * @param   mixed    $data        The data to store
	 * @param   string   $id          The cache data ID
	 * @param   string   $group       The cache data group
	 * @param   boolean  $wrkarounds  True to use wrkarounds
	 *
	 * @return  boolean  True if cache stored
	 *
	 * @since   11.1
	 * @deprecated  4.0  Write own method in subclass
	 */
	public function store($data, $id, $group = null, $wrkarounds = true)
	{
		$lock = $this->cache->lock($id, $group);

		if ($lock->locked === false)
		{
			// We can not store data because another process is in the middle of saving
			return false;
		}

		$result = $this->cache->store($data, $id, $group);

		if ($lock->locked === true)
		{
			$this->cache->unlock($id, $group);
		}

		return $result;
	}

	/**
	 * Perform workarounds on retrieved cached data
	 *
	 * @param   string  $data     Cached data
	 * @param   array   $options  Array of options
	 *
	 * @return  string  Body of cached data
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getWorkarounds($data, $options = array())
	{
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();
		$body     = null;

		// Get the document head out of the cache.
		if (isset($options['mergehead']) && $options['mergehead'] == 1 && isset($data['head']) && !empty($data['head'])
			&& method_exists($document, 'mergeHeadData'))
		{
			$document->mergeHeadData($data['head']);
		}
		elseif (isset($data['head']) && method_exists($document, 'setHeadData'))
		{
			$document->setHeadData($data['head']);
		}

		// Get the document MIME encoding out of the cache
		if (isset($data['mime_encoding']))
		{
			$document->setMimeEncoding($data['mime_encoding'], true);
		}

		// If the pathway buffer is set in the cache data, get it.
		if (isset($data['pathway']) && is_array($data['pathway']))
		{
			// Push the pathway data into the pathway object.
			$app->getPathway()->setPathway($data['pathway']);
		}

		// @todo check if the following is needed, seems like it should be in page cache
		// If a module buffer is set in the cache data, get it.
		if (isset($data['module']) && is_array($data['module']))
		{
			// Iterate through the module positions and push them into the document buffer.
			foreach ($data['module'] as $name => $contents)
			{
				$document->setBuffer($contents, 'module', $name);
			}
		}

		// Set cached headers.
		if (isset($data['headers']) && $data['headers'])
		{
			foreach ($data['headers'] as $header)
			{
				$app->setHeader($header['name'], $header['value']);
			}
		}

		// The following code searches for a token in the cached page and replaces it with the proper token.
		if (isset($data['body']))
		{
			$token       = JSession::getFormToken();
			$search      = '#<input type="hidden" name="[0-9a-f]{32}" value="1" />#';
			$replacement = '<input type="hidden" name="' . $token . '" value="1" />';

			$data['body'] = preg_replace($search, $replacement, $data['body']);
			$body         = $data['body'];
		}

		// Get the document body out of the cache.
		return $body;
	}

	/**
	 * Create workarounds for data to be cached
	 *
	 * @param   string  $data     Cached data
	 * @param   array   $options  Array of options
	 *
	 * @return  string  Data to be cached
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function setWorkarounds($data, $options = array())
	{
		$loptions = array(
			'nopathway'  => 0,
			'nohead'     => 0,
			'nomodules'  => 0,
			'modulemode' => 0,
		);

		if (isset($options['nopathway']))
		{
			$loptions['nopathway'] = $options['nopathway'];
		}

		if (isset($options['nohead']))
		{
			$loptions['nohead'] = $options['nohead'];
		}

		if (isset($options['nomodules']))
		{
			$loptions['nomodules'] = $options['nomodules'];
		}

		if (isset($options['modulemode']))
		{
			$loptions['modulemode'] = $options['modulemode'];
		}

		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();

		if ($loptions['nomodules'] != 1)
		{
			// Get the modules buffer before component execution.
			$buffer1 = $document->getBuffer();

			if (!is_array($buffer1))
			{
				$buffer1 = array();
			}

			// Make sure the module buffer is an array.
			if (!isset($buffer1['module']) || !is_array($buffer1['module']))
			{
				$buffer1['module'] = array();
			}
		}

		// View body data
		$cached['body'] = $data;

		// Document head data
		if ($loptions['nohead'] != 1 && method_exists($document, 'getHeadData'))
		{
			if ($loptions['modulemode'] == 1)
			{
				$headnow = $document->getHeadData();
				$unset   = array('title', 'description', 'link', 'links', 'metaTags');

				foreach ($unset as $un)
				{
					unset($headnow[$un]);
					unset($options['headerbefore'][$un]);
				}

				$cached['head'] = array();

				// Only store what this module has added
				foreach ($headnow as $now => $value)
				{
					if (isset($options['headerbefore'][$now]))
					{
						// We have to serialize the content of the arrays because the may contain other arrays which is a notice in PHP 5.4 and newer
						$nowvalue    = array_map('serialize', $headnow[$now]);
						$beforevalue = array_map('serialize', $options['headerbefore'][$now]);

						$newvalue = array_diff_assoc($nowvalue, $beforevalue);
						$newvalue = array_map('unserialize', $newvalue);

						// Special treatment for script and style declarations.
						if (($now == 'script' || $now == 'style') && is_array($newvalue) && is_array($options['headerbefore'][$now]))
						{
							foreach ($newvalue as $type => $currentScriptStr)
							{
								if (isset($options['headerbefore'][$now][strtolower($type)]))
								{
									$oldScriptStr = $options['headerbefore'][$now][strtolower($type)];

									if ($oldScriptStr != $currentScriptStr)
									{
										// Save only the appended declaration.
										$newvalue[strtolower($type)] = StringHelper::substr($currentScriptStr, StringHelper::strlen($oldScriptStr));
									}
								}
							}
						}
					}
					else
					{
						$newvalue = $headnow[$now];
					}

					if (!empty($newvalue))
					{
						$cached['head'][$now] = $newvalue;
					}
				}
			}
			else
			{
				$cached['head'] = $document->getHeadData();
			}
		}

		// Document MIME encoding
		$cached['mime_encoding'] = $document->getMimeEncoding();

		// Pathway data
		if ($app->isSite() && $loptions['nopathway'] != 1)
		{
			$cached['pathway'] = is_array($data) && isset($data['pathway']) ? $data['pathway'] : $app->getPathway()->getPathway();
		}

		if ($loptions['nomodules'] != 1)
		{
			// @todo Check if the following is needed, seems like it should be in page cache
			// Get the module buffer after component execution.
			$buffer2 = $document->getBuffer();

			if (!is_array($buffer2))
			{
				$buffer2 = array();
			}

			// Make sure the module buffer is an array.
			if (!isset($buffer2['module']) || !is_array($buffer2['module']))
			{
				$buffer2['module'] = array();
			}

			// Compare the second module buffer against the first buffer.
			$cached['module'] = array_diff_assoc($buffer2['module'], $buffer1['module']);
		}

		// Headers data
		if (isset($options['headers']) && $options['headers'])
		{
			$cached['headers'] = $app->getHeaders();
		}

		return $cached;
	}
}
