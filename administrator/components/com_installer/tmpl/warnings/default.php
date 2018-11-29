<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_installer
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<div id="installer-warnings" class="clearfix">
	<form action="<?php echo Route::_('index.php?option=com_installer&view=warnings'); ?>" method="post" name="adminForm" id="adminForm">
		<div class="row">
			<div id="j-sidebar-container" class="col-md-2">
				<?php echo $this->sidebar; ?>
			</div>
			<div class="col-md-10">
				<div id="j-main-container" class="j-main-container">
					<?php if (count($this->messages)) : ?>
						<?php foreach ($this->messages as $message) : ?>
							<div class="alert alert-warning">
								<h4 class="alert-heading"><?php echo $message['message']; ?></h4>
								<p class="m-b-0"><?php echo $message['description']; ?></p>
							</div>
						<?php endforeach; ?>
						<div class="alert alert-info">
							<h4 class="alert-heading"><?php echo Text::_('COM_INSTALLER_MSG_WARNINGFURTHERINFO'); ?></h4>
							<p class="m-b-0"><?php echo Text::_('COM_INSTALLER_MSG_WARNINGFURTHERINFODESC'); ?></p>
						</div>
					<?php else: ?>
						<div class="alert alert-info">
							<h4 class="alert-heading"><?php echo Text::_('INFO'); ?></h4>
							<p class="m-b-0"><?php echo Text::_('COM_INSTALLER_MSG_WARNINGS_NONE'); ?></p>
						</div>
					<?php endif; ?>
					<div>
						<input type="hidden" name="boxchecked" value="0">
						<?php echo HTMLHelper::_('form.token'); ?>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
