<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_associations
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Associations\Administrator\Field;

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormHelper;
use Joomla\Component\Associations\Administrator\Helper\AssociationsHelper;
use Joomla\CMS\HTML\HTMLHelper;

FormHelper::loadFieldClass('groupedlist');

/**
 * A drop down containing all component item types that implement associations.
 *
 * @since  3.7.0
 */
class ItemtypeField extends \JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 *
	 * @since  3.7.0
	 */
	protected $type = 'Itemtype';

	/**
	 * Method to get the field input markup.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 *
	 * @since  3.7.0
	 *
	 * @throws  \UnexpectedValueException
	 */
	protected function getGroups()
	{
		$options    = array();
		$extensions = AssociationsHelper::getSupportedExtensions();

		foreach ($extensions as $extension)
		{
			if ($extension->get('associationssupport') === true)
			{
				foreach ($extension->get('types') as $type)
				{
					$context = $extension->get('component') . '.' . $type->get('name');
					$options[$extension->get('title')][] = HTMLHelper::_('select.option', $context, $type->get('title'));
				}
			}
		}

		// Sort by alpha order.
		uksort($options, 'strnatcmp');

		// Add options to parent array.
		return array_merge(parent::getGroups(), $options);
	}
}
