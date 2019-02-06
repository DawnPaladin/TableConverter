<?php
/**
 * Author: James Harris
 * Date: April 2018
 * Purpose: Load CSV exports of Gary's survey system for handling by upload-to-db.php and importation into LimeSurvey
 */

interface iInputFileReader {
    public function __construct(String $filePath);
    public function getHeaders(): array;
    public function getData(): array;
}

class InputCSVReader implements iInputFileReader {

    /**
     * Data from the input file, parsed into a two-dimensional array. Does not include headers - see $headers for those.
     * @var $data array
     */
    private $data;

    /**
     * Headers from the input file
     * @var $headers array
     */
    private $headers;

    public function __construct(String $filePath) {
        echo "<h1>Loading CSV data</h1>";

        $input_file = file_get_contents($filePath);
        if (empty($input_file)) die("Failed to load input file $filePath.");

        $this->data = explode("\r\n", $input_file);
        $this->headers = str_getcsv(array_shift($this->data));
        $possible_duplicate_header = array_shift($this->data);
        if (str_getcsv($possible_duplicate_header) == $this->headers) {
            echo "<p>First line of CSV is a duplicate. Removing it.</p>";
        } else {
            array_unshift($this->data, $possible_duplicate_header);
        }
        if (empty($this->headers) || empty($this->headers[0])) die("Failed to load headers.");
        if (!array_filter(end($this->data))) {
            echo "<p>Last line of CSV is empty. Removing it.</p>";
            array_pop($this->data);
        }
    }

    public function getHeaders(): array {
        return $this->headers;
    }
    public function getData(): array {
        return $this->data;
    }

}
