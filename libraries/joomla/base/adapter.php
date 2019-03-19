<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Base
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;

/**
 * Adapter Class
 * Retains common adapter pattern functions
 * Class harvested from joomla.installer.installer
 *
 * @since  1.7.0
 */
class JAdapter extends JObject
{
	/**
	 * Associative array of adapters
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $_adapters = array();

	/**
	 * Adapter Folder
	 * @var    string
	 * @since  1.7.0
	 */
	protected $_adapterfolder = 'adapters';

	/**
	 * @var    string	Adapter Class Prefix
	 * @since  1.7.0
	 */
	protected $_classprefix = 'J';

	/**
	 * Base Path for the adapter instance
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $_basepath = null;

	/**
	 * Database Connector Object
	 *
	 * @var    JDatabaseDriver
	 * @since  1.7.0
	 */
	protected $_db;

	/**
	 * Constructor
	 *
	 * @param   string  $basepath       Base Path of the adapters
	 * @param   string  $classprefix    Class prefix of adapters
	 * @param   string  $adapterfolder  Name of folder to append to base path
	 *
	 * @since   1.7.0
	 */
	public function __construct($basepath, $classprefix = null, $adapterfolder = null)
	{
		$this->_basepath = $basepath;
		$this->_classprefix = $classprefix ? $classprefix : 'J';
		$this->_adapterfolder = $adapterfolder ? $adapterfolder : 'adapters';

		$this->_db = Factory::getDbo();
	}

	/**
	 * Get the database connector object
	 *
	 * @return  \Joomla\Database\DatabaseDriver  Database connector object
	 *
	 * @since   1.7.0
	 */
	public function getDbo()
	{
		return $this->_db;
	}

	/**
	 * Return an adapter.
	 *
	 * @param   string  $name     Name of adapter to return
	 * @param   array   $options  Adapter options
	 *
	 * @return  object  Adapter of type 'name' or false
	 *
	 * @since   1.7.0
	 */
	public function getAdapter($name, $options = array())
	{
		if (array_key_exists($name, $this->_adapters))
		{
			return $this->_adapters[$name];
		}

		if ($this->setAdapter($name, $options))
		{
			return $this->_adapters[$name];
		}

		return false;
	}

	/**
	 * Set an adapter by name
	 *
	 * @param   string  $name      Adapter name
	 * @param   object  &$adapter  Adapter object
	 * @param   array   $options   Adapter options
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   1.7.0
	 */
	public function setAdapter($name, &$adapter = null, $options = array())
	{
		if (is_object($adapter))
		{
			$this->_adapters[$name] = &$adapter;

			return true;
		}

		$class = rtrim($this->_classprefix, '\\') . '\\' . ucfirst($name);

		if (class_exists($class))
		{
			$this->_adapters[$name] = new $class($this, $this->_db, $options);

			return true;
		}

		$class = rtrim($this->_classprefix, '\\') . '\\' . ucfirst($name) . 'Adapter';

		if (class_exists($class))
		{
			$this->_adapters[$name] = new $class($this, $this->_db, $options);

			return true;
		}

		$fullpath = $this->_basepath . '/' . $this->_adapterfolder . '/' . strtolower($name) . '.php';

		if (!file_exists($fullpath))
		{
			return false;
		}

		// Try to load the adapter object
		$class = $this->_classprefix . ucfirst($name);

		JLoader::register($class, $fullpath);

		if (!class_exists($class))
		{
			return false;
		}

		// Check for a possible service from the container otherwise manually instantiate the class
		if (Factory::getContainer()->exists($class))
		{
			$this->_adapters[$name] = Factory::getContainer()->get($class);
		}
		else
		{
			$this->_adapters[$name] = new $class($this, $this->_db, $options);
		}

		return true;
	}

	/**
	 * Loads all adapters.
	 *
	 * @param   array  $options  Adapter options
	 *
	 * @return  void
	 *
	 * @since   1.7.0
	 */
	public function loadAllAdapters($options = array())
	{
		$files = new DirectoryIterator($this->_basepath . '/' . $this->_adapterfolder);

		/* @type  $file  DirectoryIterator */
		foreach ($files as $file)
		{
			$fileName = $file->getFilename();

			// Only load for php files.
			if (!$file->isFile() || $file->getExtension() != 'php')
			{
				continue;
			}

			// Try to load the adapter object
			require_once $this->_basepath . '/' . $this->_adapterfolder . '/' . $fileName;

			// Derive the class name from the filename.
			$name = str_ireplace('.php', '', ucfirst(trim($fileName)));
			$class = $this->_classprefix . ucfirst($name);

			if (!class_exists($class))
			{
				// Skip to next one
				continue;
			}

			$adapter = new $class($this, $this->_db, $options);
			$this->_adapters[$name] = clone $adapter;
		}
	}
}
