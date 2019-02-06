<?php

include "../../../../inc/config.php";

function getDatabaseConnection($options) {
    // $options must be an array storing database_name, username, password, and ip_address as strings
    $db = new IPM_db_plugin_mysqli();
    $db->open($options["username"], $options["password"], $options["ip_address"]);
    $db->setvar("SQL_MODE","NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION");
    $db->setdb($options["database_name"]);
    if ($db->errno) die("Unable to connect to database - error #" . $db->errno . '<br>' . print_r(debug_backtrace()));
    return $db;
}
