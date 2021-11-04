<?php

namespace App\Console\Commands\SpecificationGenerators;

use Exception;
use Illuminate\Console\Command;
use PDO;
use PDOException;

class IECContactor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected
        $signature = 'IEC';

    /**
     * The console command description.
     *
     * @var string
     */
    protected
        $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public
    function handle()
    {

        //for testing development purposes
        error_reporting(E_ALL);

        ini_set('memory_limit', '16000M');

        define("DB_USER", "remote");
        define("DB_PASSWORD", '9%EGB6ndv6$SGfpu');
        define("DB_NAME", "automation_stop_port");
        define("DB_HOST", "3.215.248.178");

//        define("partName", "100-C");
//        define("partPattern", "^(10[04][SQ]?-CR?)(\d{0,3})([a-zA-Z]{0,2})?(\d+)?([a-zA-Z]+)?");
//        define("partDBPattern", "10_%-C%");

//        define("partName", "100-K");
//        define("partPattern", "^(10[04]-KR?)(\d{0,3})([a-zA-Z]{0,2})?(\d+)?([a-zA-Z]+)?");
//        define("partDBPattern", "10_%-K%");

        define("partName", "100-E");
        define("partPattern", "(10[04]S?-E)(\d{2,4})([a-zA-Z]{1,2})(\d{2,3}C?)(L)?");
        define("partDBPattern", "10_%-E%");

        define("rulePattern", "/[A-Z]\d+\s--\s\d+-\d+|[A-Z]\s--\s\d+-\d+/");
        define("specialRulePattern", "/\*/");
        define("testProduct", "1760-IA12XOW6I");

        /** Try to connect to the database */
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        error_log("pdo connection success");

        /** Variables to be used throughout the script */
        $file = null;
        $table = array();
        $rules = array();
        $specialRules = array();
        $sample = array();
        $htmlBody = null;
        $errorCount = 0;
        $processed = 0;
        $products = array();

        $query = $db->prepare("SELECT * FROM products WHERE part_number LIKE '" . partDBPattern . "'");
        $query->execute();
        $query = $query->fetchAll();

        error_log("products retrieved");

        foreach ($query as $result) {
            if (!preg_match('/' . partPattern . '/', $result['part_number'])) {
                continue;
            }
            $product = new Product();
            $product->product_id = $result['id'];
            $product->partNumber = $result['part_number'];
            $product->unseparatedPartNumber = $product->partNumber;
            $matches = array();
            preg_match('/' . partPattern . '/', $product->partNumber, $matches);
            $separatedNumber = "";
            for ($i = 1; $i < count($matches); $i++) {
                if ($matches[$i] != '') {
                    $separatedNumber .= "-" . $matches[$i];
                }
            }
            $separatedNumber = substr($separatedNumber, 1);
            $product->partNumber = $separatedNumber;
            $product->specifications = array();

            array_push($products, $product);
        }

        error_log("products built");

        /** Try to open the file */
        try {
            $file = fopen(getcwd() . $_SERVER['DOCUMENT_ROOT'] . "/app/Console/Commands/SpecificationGenerators/specFiles/" . partName . "specs_default.csv", 'r');
        } catch (Exception $e) {
            echo("Failed to open file -- " . $e->getMessage());
            exit("Script stopped running\n");
        }

        error_log("spec file open success");

        $headers = fgetcsv($file);
        for ($i = 0; $i < count($headers); $i++) {
            $table[0][$i] = $headers[$i];
        }
        /** Get the subsequent lines and fill the table with them */
        $lines = array();
        $line = fgetcsv($file);
        while ($line) {
            array_push($lines, $line);
            $line = fgetcsv($file);
        }
        for ($i = 0; $i < count($lines); $i++) {
            for ($j = 0; $j < count($headers); $j++) {
                if ($lines[$i][$j] == '') {
                    $table[$i + 1][$j] = null;
                }
                $table[$i + 1][$j] = $lines[$i][$j];
            }
        }

        error_log("table built");

        $ruleCols = array();
        $specRuleCols = array();
        for ($i = 0; $i < count($table[0]); $i++) {
            //Check if it is a rule, otherwise specification
            $ruleFound = preg_match(rulePattern, $table[0][$i]);
            $specRuleFound = preg_match(specialRulePattern, $table[0][$i]);
            if ($ruleFound === false || $specRuleFound === false) {
                //Error occurred, exit before the script has a chance to run for too long
                echo("Error in regex matching of headers to rules -- exiting");
                exit();
            } else if ($ruleFound) {
                //Add the index to the array keeping track of rule columns
                array_push($ruleCols, $i);
            } else if ($specRuleFound) {
                //Add the index to the array keeping track of special rule columns
                array_push($specRuleCols, $i);
            } else {
                //Specification, skip this
                continue;
            }
        }

        /** Very quickly lets take out all of the specification headers [keys] to use later when
         *  building $htmlBody and generating the CSV
         */
        $keys = array();
        $dimensions = array();
        array_push($keys, 'MPN');
        for ($i = 0; $i < count($headers); $i++) {
            if ((in_array($i, $ruleCols, true) || in_array($i, $specRuleCols, true)) || in_array($headers[$i], $keys, true)) {
                continue;
            }
            if (strpos($headers[$i], "Dimension") !== false) {
                $headers[$i] = explode(" - ", $headers[$i])[1];
                array_push($dimensions, $headers[$i]);
                continue;
            }

            array_push($keys, $headers[$i]);
        }

        /** Lets start actually building the rules */
        for ($i = 0; $i < count($table[0]); $i++) {
            if (in_array($i, $ruleCols, true)) {
                //Find the beginning offset of the consume range
                $rangeFound = preg_match("/\d+-\d+/", $table[0][$i], $rangeMatch, PREG_OFFSET_CAPTURE);
                if ($rangeFound == false) { //This can be '== false' because even if zero is returned (no match) that is an error and this should exit
                    echo("Error in regex matching; looking for Catalog Number consumption range -- exiting");
                    exit();
                }
                //Prepare the range
                $range = substr($table[0][$i], $rangeMatch[0][1]);
                $range = explode('-', $range);
                $start = intval($range[0]) - 1; //Minus 1 because indexing starts at 0
                $end = intval($range[1]);

                $rule = new Rule();
                $rule->consume_start = $start;
                $rule->consume_end = $end;
                $catalogOptions = array();
                for ($j = 1; $j < count($table); $j++) {
                    if ($table[$j][$i] == '') {
                        break;
                    } else {
                        $catalogOptionSpecification = array();
                        for ($k = $i + 1; $k < count($table[$j]); $k++) {
                            if ($table[$j][$k] == '' || (in_array($k, $ruleCols, true) || in_array($k, $specRuleCols, true))) {
                                break;
                            } else {
                                array_push($catalogOptionSpecification, $table[0][$k] . " -- " . $table[$j][$k]);
                            }
                        }
                        $catalogOptions[$table[$j][$i]] = $catalogOptionSpecification;
                    }
                }
                $rule->catalogOptions = $catalogOptions;
                $rule->header = $table[0][$i];
                array_push($rules, $rule);
            } else {
                continue;
            }
        }

        /** Now lets handle the special rules */
        for ($i = 0; $i < count($table[0]); $i++) {
            if (in_array($i, $specRuleCols, true)) {
                $rule = new Rule();
                $catalogOptions = array();
                for ($j = 1; $j < count($table); $j++) {
                    if ($table[$j][$i] == '') {
                        break;
                    } else {
                        $catalogOptionSpecification = array();
                        for ($k = $i + 1; $k < count($table[$j]); $k++) {
                            if ($table[$j][$k] == '' || (in_array($k, $ruleCols, true) || in_array($k, $specRuleCols, true))) {
                                break;
                            } else {
                                array_push($catalogOptionSpecification, $table[0][$k] . " -- " . $table[$j][$k]);
                            }
                        }
                        $catalogOptions[$table[$j][$i]] = $catalogOptionSpecification;
                    }
                }
                $rule->catalogOptions = $catalogOptions;
                $rule->header = $table[0][$i];
                array_push($specialRules, $rule);
            } else {
                continue;
            }
        }

        error_log("rules built");

        foreach ($products as $product) {
            $partNumSplit = explode("-", $product->partNumber);
            $group = 0;

            /** Rules */
            for ($i = 0; $i < count($rules); $i++) {
                $rule = $rules[$i];
                if (strpos($rule->header, "(MODS)") !== false) {
                    $mod = $partNumSplit[$group];
                    while ($mod) {
                        if (!isset($rule->catalogOptions[$mod])) {
                            $mod = $partNumSplit[++$group];
                            continue;
                        } else {
                            $mods = $rule->catalogOptions[$mod];
                            foreach ($mods as $spec) {
                                $spec = explode(" -- ", $spec);
                                if ($spec[1] === "BLANK") {
                                    continue;
                                } else if (!isset($product->specifications[$spec[0]])) {
                                    $product->specifications[$spec[0]] = $spec[1];
                                } else {
                                    $product->specifications[$spec[0]] = $product->specifications[$spec[0]] . ", " . $spec[1];
                                }
                            }
                        }
                        $mod = $partNumSplit[++$group];
                    }
                } else {
                    if (!isset($partNumSplit[$group])) {
                        $errorCount++;
                        continue;
                    }
                    $catalogOption = $partNumSplit[$group++];
                    if (!isset($rule->catalogOptions[$catalogOption])) {
                        $errorCount++;
                        continue;
                    }
                    $specs = $rule->catalogOptions[$catalogOption];
                    foreach ($specs as $spec) {
                        $spec = explode(" -- ", $spec);
                        $product->specifications[$spec[0]] = $spec[1];
                    }
                }
            }


            /** Special rules */
            foreach ($specialRules as $rule) {
                $specsHeader = explode(" -- ", $rule->header);

                $specsI = array();

                for ($i = 1; $i < count($specsHeader); $i++) {
                    if (preg_match("/\d+-\d+/", $specsHeader[$i])) {
                        array_push($specsI, $specsHeader[$i]);
                    } else if (!isset($product->specifications[$specsHeader[$i]])) {
                        continue 2;
                    } else {
                        $spec = $specsHeader[$i];
                        array_push($specsI, $spec);
                    }
                }

                foreach (array_keys($rule->catalogOptions) as $option) {
                    $match = true;
                    $specOptions = explode(" -- ", $option);
                    for ($i = 0; $i < count($specOptions); $i++) {
                        $rangeFound = preg_match("/\d+-\d+/", $specOptions[$i], $rangeMatch, PREG_OFFSET_CAPTURE);
                        if ($rangeFound) {
                            $range = substr($specOptions[$i], $rangeMatch[0][1]);
                            $range = explode('-', $range);
                            $start = intval($range[0]);
                            $end = intval($range[1]);

                            $catalogOption = $product->specifications[$specsI[$i]];
                            if (!($start <= $catalogOption) && !($catalogOption <= $end)) {
                                $match = false;
                                continue;
                            }
                        } else {
                            $catalogOption = $product->specifications[$specsI[$i]];
                            if ($catalogOption !== $specOptions[$i]) {
                                $match = false;
                            }
                        }
                    }

                    if ($match) {
                        $specs = $rule->catalogOptions[$option];
                        $count = 0;
                        foreach ($specs as $spec) {
                            $spec = explode(" -- ", $spec);
                            if (strpos($spec[0], "Dimension") !== false) {
                                $spec[0] = explode(" - ", $spec[0])[1];
                            }
                            $product->specifications[$spec[0]] = $spec[1];
                        }
                    }
                }
            }

            /** Housekeeping stuff */
            $product->specifications['MPN'] = $product->unseparatedPartNumber;
            $x = mt_rand(0, 1000);
            if ($x >= 0) {
                array_push($sample, $product);
            }
            $processed++;
            error_log("Processed:" . $processed . "|" . $product->partNumber);
        }

        $inserted = 0;

        /** Insert into the database */

        $data = [];
        $count = 0;
        foreach ($products as $product) {
            $query = $db->prepare("DELETE FROM technical_specs WHERE product_id = ?");
            $query->execute(array($product->product_id));
            $displayOrder = 0;
            foreach ($keys as $key) {
                if (isset($product->specifications[$key])) {
                    array_push($data, array($product->product_id, $key, $product->specifications[$key], $displayOrder));
                    $displayOrder++;
                }
            }
            $count++;
            if ($count % 10 == 0) {
                error_log("processed: " . $count);
            }
        }

        $count = 0;
        $stmt = $db->prepare("INSERT INTO technical_specs (product_id, spec, stat, display_order)
                                           VALUES (?, ?, ?, ?)");
        $db->beginTransaction();
        foreach ($data as $t) {
            $stmt->execute($t);
            $count++;
            if ($count % 100 == 0) {
                error_log("inserted: " . $count);
            }
        }
        $db->commit();


        /** Generate the CSV */
        $csv = fopen(getcwd() . $_SERVER['DOCUMENT_ROOT'] . "/app/Console/Commands/SpecificationGenerators/specOutputs/" . date('Y-m-d') . '_' . partName . ".csv", 'w');
        // The headers first
        $headers = $keys;
        array_unshift($headers, "Product Number");
        fputcsv($csv, $headers);
        //Then the body
        foreach ($products as $product) {
            $outputLine = array();
            array_push($outputLine, $product->partNumber);

            for ($i = 0; $i < count($keys) - 1; $i++) {
                if (isset($product->specifications[$keys[$i]])) {
                    array_push($outputLine, $product->specifications[$keys[$i]]);
                } else {
                    array_push($outputLine, "-");
                }
            }
            if (isset($product->specifications[$keys[$i]])) {
                array_push($outputLine, $product->specifications[$keys[$i]]);
            }
            fputcsv($csv, $outputLine);
        }
        $download = true;
    }
}

class Rule
{
    public $consume_start;
    public $consume_end;
    public $catalogOptions;
    public $hasVolts;
    public $volts;
    public $header;
}

class Product
{
    public $product_id;
    public $unseparatedPartNumber;
    public $partNumber;
    public $specifications;
}
