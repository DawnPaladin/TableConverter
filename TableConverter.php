<?php
/**
 * Author: James Harris
 * Date: April 2018
 */

class TableConverter {

    /**
     * Controls which database table we'll upload to. Normally of the form "lime_survey_" plus a survey number.
     * @var $db_table string
     */
    private $db_table;

    /**
     * Field used by row_exists_in_db() to track which records have been uploaded to the server.
     * To use dates, you'll need to enable "Date stamp" while activating the survey.
     * @var $uniqueness_field string
     */
    private $uniqueness_field;

    /**
     * If your survey has multiple-cholce questions like "Satisfied / Dissatisfied / N/A",
     * enter the QID of one such question so getSatisfactionCode() can look up the answer IDs.
     * You can find the QID in Settings -> Overview -> Tools -> Survey Logic File.
     * @var $qid int
     */
    private $qid;

    /**
     * Path to the JSON file denoting which columns in the input file correspond to which columns in the output file.
     * The top-level keys in this file are occasionally used by this program to apply special logic to a particular column.
     * The values are arrays that can have the following sub-keys:
     * - input_header: Name of the column in the input file
     * - output_header: Name of the column in the database it will be uploaded to
     * - content: Use this if you want each row of this column to have the same value.
     * - function: Pass each row from `input_column` or `content` to a function of your choice. Define the function as a
     *     method on the TableConverter class and put its name here.
     * These keys are optional, and you shouldn't use them all in one place. Common combinations are input_header +
     * output_header, input_header + function + output_header, or function + output_header (to, for example, generate and
     * store a datestamp). You can even omit both input_header and output_header, and just use `function` to generate
     * data that you use to make decisions about other columns in the same row.
     * @var $columns_file string
     */
    private $columns_file;

    /**
     * The "debug" flag disables uploading of data (giving you a "dry run" mode) and logs a lot of information to the screen.
     * @var $debug bool
     */
    private $debug;

    /**
     * Normally the uploader checks for the presences of duplicate entries before
     * @var $prevent_skipping bool
     */
    private $prevent_skipping;

    // Functions: Database

    private $db;
    private $inputFileReader;

    public function db_insert($row, $output_headers) {
        foreach ($row as &$el) {
            $el = $this->db->real_escape_string($el);
        }
        $query = "INSERT INTO `$this->db_table` (";
        $query .= implode(', ', $output_headers);
        $query .= ") VALUES('";
        $query .= implode("', '", $row);
        $query .= "');";
        $query = str_replace("''", "NULL", $query);
        if ($this->debug == true) echo "Insertion query: " . $query . "<br>";
        if ($this->debug == false) $this->db->query($query);
        if ($this->db->error != "") {
            die("Error: " . $this->db->error);
        }
    }

    public function row_exists_in_db($row, $columns, $indexes) {
        $startdate = $this->convert_date_to_sql($row[$indexes[$this->uniqueness_field]]);
        $startdateColumnOnServer = $columns[$this->uniqueness_field]['output_header'];
        $query = "SELECT $startdateColumnOnServer FROM `$this->db_table` WHERE $startdateColumnOnServer = '$startdate'";
        // if ($this->debug) echo "<p>$query</p>";
        $result = $this->db->query($query);
        // if ($this->debug) echo "<p>Result:" . $result . "</p>";
        if ($result == false) return false;
        $duplicateFound = sizeof($result) > 0;
        return $duplicateFound;
    }

    // Functions: Generating data
    // $this->run() may call these functions by reading their names from columns.json

    private function datestamp() {
        $now = new DateTime();
        return $now->format('Y-m-d H:i:s');
    }

    private function lastpage($cell, $row, $indexes) {
        $surveyCompleted = $row[$indexes['status']] == "Complete";
        if ($surveyCompleted == true) {
            return "13";
        }
    }

    // Functions: Converting data
    // $this->run() may call these functions by reading their names from columns.json

    private $regionToCode = array(); // memoization array
    public function regionToLSCode($region) {
        $region = trim(str_replace('region',"",strtolower($region)));
        if (sizeof($this->regionToCode) == 0) { // on first call, populate array
            $query = "SELECT ANSWER, CODE FROM `lime_answers` WHERE `qid` = 2293 AND `language` LIKE 'en'";
            $results = $this->db->query($query);
            foreach($results as $result) {
                $this->regionToCode[strtolower($result['ANSWER'])] = $result['CODE'];
            }
        }

        if (isset($this->regionToCode[$region])) {
            return $this->regionToCode[$region];
        }
        return false;
    }

    private $satisfactionCodes = array(), $frequency = array();
    public function getSatisfactionCode($satisfaction) {
        if (sizeof($this->satisfactionCodes) == 0) {
            $query = "SELECT ANSWER, CODE FROM `lime_answers` WHERE `qid` = $this->qid AND `language` LIKE 'EN'";
            //            echo "<p>Satisfaction code query: $query </p>";
            $results = $this->db->query($query);
            //            echo "<p>Satisfaction code results: " . print_r($results) . "</p>";
            foreach ($results as $result) {
                $this->satisfactionCodes[$result['ANSWER']] = $result['CODE'];
            }
            $this->frequency = array();
        }
        $titleCaseSat = ucwords(strtolower($satisfaction));
        if (isset($this->satisfactionCodes[$satisfaction])) {
            $this->frequency[$this->satisfactionCodes[$satisfaction]] += 1;
            return $this->satisfactionCodes[$satisfaction];
        } else if (isset($this->satisfactionCodes[$titleCaseSat])) {
            $this->frequency[$this->satisfactionCodes[$titleCaseSat]] += 1;
            return $this->satisfactionCodes[$titleCaseSat];
        }
        return "";
    }

    private function convert_date_to_sql($datestring) {
        if ($datestring == "") return null;
        $date = strtotime($datestring);
        $sqlDate = date('Y-m-d H:i:s', $date);
        return $sqlDate;
    }

    // This function is used when an address that spans several columns needs to be combined into one.
    private function concatenate_columns($concat_sets, $input_row, $indexes, &$output_arr) {
        foreach ($concat_sets as $concat_set) {
            $input_column_names = $concat_set["input_columns"];
            $separator = $concat_set["separator"];

            $concatenated_columns = array();
            foreach ($input_column_names as $input_column_name) {
                array_push($concatenated_columns, $input_row[$indexes[$input_column_name]]);
            }

            array_unshift($output_arr, implode($separator, $concatenated_columns));
        }
    }

    // Functions: Miscellaneous
    // $this->run() may call these functions by reading their names from columns.json

    private function submitdate($cell, $row, $indexes) {
        $surveyCompleted = $row[$indexes['status']] == "Complete";
        if (!$surveyCompleted) return $this->convert_date_to_sql(new DateTime());

        $completedAt = $row[$indexes['datestamp']];
        if ($completedAt == "") {
            return $this->convert_date_to_sql($row[$indexes['startdate']]);
        } else {
            return $this->convert_date_to_sql($completedAt);
        }
    }

    private function override_status($cell, $row, $indexes) {
        // If user has answered the last three-button question, change status to "Completed".
        if ($row[$indexes['OverallSatisfaction']]) {
            return "Completed";
        } else {
            return $cell;
        }
    }

    // Functions: Structural

    public function __construct(array $options, iInputFileReader $inputFileReader, iIPM_db_plugin $db) {
        if (!is_string($options["db_table"])) die("options[db_table] must be a string");
        if (   !is_int($options["qid"])      ) die("options[qid] must be an int");

        $this->db_table         = $options["db_table"];
        $this->uniqueness_field = $options["uniqueness_field"] or "startdate";
        $this->qid              = $options["qid"];
        $this->columns_file     = $options["columns_file"];
        $this->debug            = $options["debug"] || false;
        $this->prevent_skipping = $options["prevent_skipping"] || false;
        $this->inputFileReader  = $inputFileReader;

        $this->db = $db;
    }

    function run() {
        echo "<h1>Converting Gary's survey data to LimeSurvey format</h1>";
        if ($this->debug) echo "<h2>Debug Mode - uploads will be SIMULATED</h2>";

        $columns = json_decode(file_get_contents($this->columns_file), true);
        if (empty($columns)) die("Failed to load columns.");

        $input_data = $this->inputFileReader->getData();
        $input_headers = $this->inputFileReader->getHeaders();

        $indexes = array(); // stores the index (number) of each .csv column name
        $output_headers = array();
        $column_names = array_keys($columns);
        echo "Column names";
        print_r($column_names);
        foreach ($column_names as $column_name) { // populate $indexes and $output_headers
            if (isset($columns[$column_name]['input_header'])) {
                $input_header = $columns[$column_name]['input_header'];
                if ($indexes[$column_name]) {
                    die("Error - there is more than one copy of the input header $input_header in the columns file");
                }
                $indexes[$column_name] = array_search($columns[$column_name]['input_header'], $input_headers);
            }
            if (isset($columns[$column_name]['output_header'])) {
                $output_header = $columns[$column_name]['output_header'];
                if (in_array($output_header, $output_headers)) {
                    die("Error - there is more than one copy of the output header $output_header in the columns file");
                }
                array_push($output_headers, $output_header);
            }
        }
        if (isset($columns["concatenate_columns"])) {
            foreach ($columns["concatenate_columns"] as $concat_set) {
                $output_header_name = $concat_set["output_header"];
                array_unshift($output_headers, $output_header_name);
            }
        }
        if ($this->debug) echo 'Columns: <strong>' . implode(', ', $output_headers) . '</strong><br>';

        $upload_count = 0;
        foreach ($input_data as $input_row) { // process rows
            $input_row = str_getcsv($input_row);

            if ($this->prevent_skipping == false) {
                $date = $input_row[$indexes["startdate"]];
                if ($this->row_exists_in_db($input_row, $columns, $indexes)) {
                    echo "<p>Datestamp $date already exists in the database - skipping</p>";
                    continue;
                } else {
                    echo "<p>Uploading record with date $date</p>";
                }
            }

            $output_arr = array();

            if (isset($columns["concatenate_columns"])) {
                $this->concatenate_columns($columns["concatenate_columns"], $input_row, $indexes, $output_arr);
            }

            foreach ($column_names as $column_name) { // process each column in a row
                // echo "<p>Row $upload_count, column $column_name</p>";
                if ($column_name == "concatenate_columns") continue;

                $cell = $input_row[$indexes[$column_name]];
                if (isset($columns[$column_name]['content'])) {
                    $cell = $columns[$column_name]['content'];
                }
                if (isset($columns[$column_name]['function'])) {
                    $function_name = $columns[$column_name]['function'];
                    if (method_exists($this, $function_name)) {
                        $cell = call_user_func(array($this, $function_name), $cell, $input_row, $indexes, $column_name);
                    } else {
                        die("$function_name is not a function on this class");
                    }
                }
                if (isset($columns[$column_name]['output_header'])) {
                    array_push($output_arr, $cell);
                }
            }

            if (sizeof($output_arr) != sizeof($output_headers)) {
                echo "<p>Wrong number of columns in output</p>";
                echo "output_arr: ";
                print_r($output_arr);
                echo "<br>output headers:";
                print_r($output_headers);
                die();
            }

            $this->db_insert($output_arr, $output_headers);
            $upload_count += 1;
        }
        echo "<p>Uploaded " . $upload_count . " records</p>";

        echo "<p>Satisfaction code frequency:";
        print_r($this->frequency);
        echo "</p>";

        echo "<p>Finished.</p>";
    }
}
