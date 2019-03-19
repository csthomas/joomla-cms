<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_contact
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>

<?php if ($this->params->get('presentation_style') === 'sliders') : ?>
	<?php echo HTMLHelper::_('bootstrap.addSlide', 'slide-contact', Text::_('COM_CONTACT_LINKS'), 'display-links'); ?>
<?php endif; ?>
<?php if ($this->params->get('presentation_style') === 'tabs') : ?>
	<?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'display-links', Text::_('COM_CONTACT_LINKS')); ?>
<?php endif; ?>
<?php if ($this->params->get('presentation_style') === 'plain') : ?>
	<?php echo '<h3>' . Text::_('COM_CONTACT_LINKS') . '</h3>'; ?>
<?php endif; ?>

<div class="com-contact__links contact-links">
	<ul class="nav flex-column">
		<?php
		// Letters 'a' to 'e'
		foreach (range('a', 'e') as $char) :
			$link = $this->item->params->get('link' . $char);
			$label = $this->item->params->get('link' . $char . '_name');

			if (!$link) :
				continue;
			endif;

			// Add 'http://' if not present
			$link = (0 === strpos($link, 'http')) ? $link : 'http://' . $link;

			// If no label is present, take the link
			$label = $label ?: $link;
			?>
			<li class="nav-item">
				<a class="nav-link" href="<?php echo $link; ?>" itemprop="url">
					<?php echo $label; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>

<?php if ($this->params->get('presentation_style') === 'sliders') : ?>
	<?php echo HTMLHelper::_('bootstrap.endSlide'); ?>
<?php endif; ?>
<?php if ($this->params->get('presentation_style') === 'tabs') : ?>
	<?php echo HTMLHelper::_('bootstrap.endTab'); ?>
<?php endif; ?>
