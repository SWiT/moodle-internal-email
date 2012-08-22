<?php // $Id: version.php,v 1.3 2006/10/18 16:41:20 tmas Exp $
/**
 * Code fragment to define the version of email
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author Toni Mas
 * @version $Id: version.php,v 1.3 2006/10/18 16:41:20 tmas Exp $
 * @package email
 * @license The source code packaged with this file is Free Software, Copyright (C) 2006 by
 *          <toni.mas at uib dot es>.
 *          It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
 *          You can get copies of the licenses here:
 * 		                   http://www.affero.org/oagpl.html
 *          AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
 **/

$module->version  = 2011100601;  // The current module version (Date: YYYYMMDDXX)
$module->cron     = 60;           // Period for cron to check this module (secs)

?>
