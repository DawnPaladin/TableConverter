<?php

include "InputCSVReader.php";
include "db.php";
include "TableConverter.php";

$inputCSVReader = new InputCSVReader('./reportTable4.csv');

$db = getDatabaseConnection(array(
    "database_name" => "hki_limesurvey",
    "username"      => "hki_limesurvey",
    "password"      => "", // enter password here
    "ip_address"    => "192.168.25.11"
));

$tableConverter = new TableConverter(array(
    db_table => "lime_survey_585415",
    uniqueness_field => "startdate",
    qid => 15593,
    columns_file => "./columns-585415.json",
    debug => false,
    prevent_skipping => false
), $inputCSVReader, $db);

$tableConverter->run();

?>
