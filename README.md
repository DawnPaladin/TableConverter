# TableConverter

This PHP script takes CSV data from a proprietary survey system, transforms it, and uploads it to a LimeSurvey database. A JSON file defines input and output columns, and allows you to specify default values and custom functions that should be run on each on each column. It includes protections against uploading duplicate records into LimeSurvey, so it can be safely run more than once. See comments in TableConverter.php for details.
