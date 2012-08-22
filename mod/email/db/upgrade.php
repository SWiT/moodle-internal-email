<?php 

/**
 * Database upgrade code.
 *
 */

function xmldb_email_upgrade($oldversion = 0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager(); /// loads ddl manager and xmldb classes

    if ($oldversion < 2011100601) {
        update_capabilities();
        upgrade_mod_savepoint(true, 2011100601, 'email');
    }

}

?>