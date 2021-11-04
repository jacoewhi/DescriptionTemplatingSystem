<?php


namespace App\Console\Commands\DTS\ValueConversion;


use App\Console\Commands\DTS\Exceptions\FileNotFoundException;

class SpecValueConverter {

    private ValueConversions $valueConversions;

    /**
     * SpecValueConverter constructor.
     * @throws FileNotFoundException
     */
    public function __construct(string $filePath) {
        $this->getSpecValuesFromCSV($filePath);
    }

    /**
     * @param string $filePath
     * @throws FileNotFoundException
     */
    private function getSpecValuesFromCSV(string $filePath) {
        //attempt to open file stream
        $csv = fopen($filePath, 'r');
        //if file open fails throw a fatal error
        if ($csv === false) {
            throw new FileNotFoundException("value conversion file open failure");
        }
        error_log("value conversion file found");
        //remove headers
        fgetcsv($csv);
        //get the first line
        $line = fgetcsv($csv);
        //create the ValueConversions object
        $valueConversions = new ValueConversions();
        //loop through every spec and value
        while ($line) {

            //clean raw data
            $specification = trim($line[0]);
            $value = trim($line[1]);
            $templateGroup = trim($line[9]);
            //check that both spec and value are provided
            if (!empty($specification) && $value !== "" && !empty($templateGroup)) {

                //create a new ValueConversionEntry
                $valueConversionEntry = new ValueConversionEntry($specification, $value, $templateGroup);
                // keep track of which column were on to skip the first two
                $count = 0;
                foreach ($line as $column) {

                    $count++;
                    //skip the first two columns that contained the spec and value and the last column that contains the template group
                    if ($count <= 2 || $count > 9) {
                        continue;
                    }
                    //add the value conversion to the entry's list of value conversions
                    if (!empty($column)) {
                        $valueConversionEntry->addValueConversionString(trim($column));
                    }
                }
                //add the entry to the ValueConversions
                $valueConversions->addValueConversionEntry($valueConversionEntry);
            }
            //get the next line
            $line = fgetcsv($csv);
        }
        $this->setValueConversions($valueConversions);
    }

    /**
     * @param ValueConversions $valueConversions
     */
    private function setValueConversions(ValueConversions $valueConversions): void {
        $this->valueConversions = $valueConversions;
    }

    /**
     * @return ValueConversions
     */
    public function getValueConversions(): ValueConversions {
        return $this->valueConversions;
    }

}
