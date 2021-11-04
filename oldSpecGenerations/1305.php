<?php
class Rule {
    //Each rule has options (characters consumed will match one of these options)
    //Each option has specifications
    public $consume_start;
    public $consume_end;
    public $catalogOptions;
    public $hasVolts;         //Powerflex specific
    public $volts;            //Powerflex specific
    public $header;
}

class Product {
    public $product_id;
    public $partNumber;
    public $specifications;
    public $volts;            //Powerflex specific
}
/** Various Globals -- Grouped Together by Relevance */

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost");

define("rulePattern", "/[A-Z]\d+\s--\s\d+-\d+|[A-Z]\s--\s\d+-\d+/");
define("specialRulePattern", "/\*/");
define("testProduct", "20AB2P2A3AYYNNC0");

/** Various Globals -- End */

/** Try to connect to the database */
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo("Connection failed - ".$e->getMessage());
}

/** Variables to be used throughout the script */
$file = null;
$table = array();
$rules = array();
$specialRules = array();
$sample = array();
$htmlBody = null;
$errorCount = 0;
$processed = 0;

/** Check if a series was selected and query the selected series */
if (isset($_POST['series'])) {
    if ($_POST['series'] === "1305seriesC") {
        $query = $db->prepare("SELECT * FROM products WHERE part_number LIKE '1305-%'");
        $query->execute();
        $query = $query->fetchAll();
    } 

    /** Create array of products to be processed - omit parts which make up the product i.e omit
     *  parts which do not meet the catalog number set by the manual
     *  Powerflex 70 - https://literature.rockwellautomation.com/idc/groups/literature/documents/um/20a-um001_-en-p.pdf
     *  Powerflex 700 - https://lvmcc-pubs.rockwellautomation.com/pubs/20B-UM002G-EN-P.pdf
     *  Powerflex 700s - https://literature.rockwellautomation.com/idc/groups/literature/documents/td/20d-td002_-en-p.pdf
     *  Powerflex 753 - https://literature.rockwellautomation.com/idc/groups/literature/documents/td/750-td001_-en-p.pdf
     *  Powerflex 755 - https://literature.rockwellautomation.com/idc/groups/literature/documents/td/750-td001_-en-p.pdf
     */
    $products = array();
    foreach ($query as $result) {
        $product = new Product();
        $product->partNumber = $result['part_number'];
        $product->id = $result['id'];
        $product->specifications = array();
        array_push($products, $product);
    }
}

/** Check if a file has been passed */
if (isset($_FILES['productCSV'])) {

    /** Try to open the file */
    try {
        /** Proper error checking on the file is omitted for the sake of time, because this tool is internal
         *  we can expect all given files to be properly formatted, if not, the errors will be caught later ¯\_(ツ)_/¯
         */
        $file = fopen($_FILES['productCSV']['tmp_name'],'r');
    } catch (Exception $e) {
        echo("Failed to open file -- ".$e->getMessage());
        exit("Script stopped running.\n");
    }

    /** Get the headers and build the first line of the table */
    $headers = fgetcsv($file);
    for( $i = 0; $i < count($headers); $i++) {
        $table[0][$i] = $headers[$i];
    }

    /** Get the subsequent lines and fill the table with them */
    $lines = array();
    $line = fgetcsv($file);
    while($line) {
        array_push($lines, $line);
        $line = fgetcsv($file);
    }
    for ($i = 0; $i < count($lines); $i++) {
        for ($j = 0; $j < count($headers); $j++) {
            if ( $lines[$i][$j] == '' ) {
                $table[$i + 1][$j] = null;
            }
            $table[$i + 1][$j] = $lines[$i][$j];
        }
    }

    /**
     * Now that we have a table and a working regex matcher, here comes the hard part
     * We need to construct rules. Each rule will consume some amount of characters from the
     * product number. This will be matched to one of the rules options. Each option will the specify some
     * specifications. Because different products will have different rules and will specify different amounts
     * of specifications, this needs to be done dynamically, hence building the table first.
     *
     * General structure is this:
     *   Rule[ Option   => [ Spec n, Spec n + 1, Spec n + 2, ... ]
     *         Option   => [ Spec n, Spec n + 1, Spec n + 2, ... ]
     *         Option   => [ Spec n, Spec n + 1, Spec n + 2, ... ]
     *         Option   => [ Spec n, Spec n + 1, Spec n + 2, ... ]
     *         ...    ]
     *
     *
     * How to do this?
     * 1) Process headers first, see which ones are rules and which are not
     *      > Keep track of rule columns to reference back to later
     * 2) Once you find a rule, read the options directly below it
     *      > Process how much of the catalog number it will consume
     *      > See if this particular rule has a prerequisite (i.e. Powerflex series have rules that only apply to products with certain voltages)
     *      > Read an option, the cells directly to right of it are the specifications it declares
     *      > Map these specifications to this option until you hit a rule
     *      > Check if you hit a rule by checking the col number against rule column numbers
     *      > Continue onto the option below until you hit null, then onto the next rule
     * 3) Repeat until you are out of rules
     */

    /** Array to keep track of rule columns -- used throughout to determine what is and is not a specification */
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
            array_push($specRuleCols, $i);
        } else {
            //Specification, skip this
            continue;
        }
    }

    /** Very quickly before building the rule objects, let take out all the specification headers (keys)
     *  to use later when building $htmlBody and for generating the CSV
     */
    $keys = array();
    $dimensions = array();
    array_push($keys, 'MPN');
    for ($i = 0; $i < count($headers); $i++) {
        if ((in_array($i, $ruleCols, true)) || (in_array($i, $specRuleCols, true)) ||(in_array($headers[$i], $keys, true))) {
            continue;
        }
        // Handle dimensions
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
            //Find the offset of the Voltage signifying the usage of this rule (Powerflex Specific)
            $voltFound = preg_match("/\(\d+V\)/", $table[0][$i], $voltMatch, PREG_OFFSET_CAPTURE);
            if ($voltFound === false) {
                echo("Error in regex matching; looking for Volt rules -- exiting");
                exit();
            } else if ($voltFound) {
                $range = substr($table[0][$i], $rangeMatch[0][1], ($voltMatch[0][1] - $rangeMatch[0][1]));
            } else {
                $range = substr($table[0][$i], $rangeMatch[0][1]);
            }
            //Prepare the range
            $range = explode('-', $range);
            $start = intval($range[0]) - 1; //Minus 1 because indexing starts at 0
            $end = intval($range[1]);

            $rule = new Rule();
            $rule->consume_start = $start;
            $rule->consume_end = $end;
            if ($voltFound) {
                $rule->hasVolts = true;
                $rule->volts = substr($table[0][$i], $voltMatch[0][1] + 1, strlen($table[0][$i]) - $voltMatch[0][1] - 2); //Plus 1 because we move past the first parentheses, and then minus 2 because we do not count the last parentheses
            } else {
                $rule->hasVolts = false;
                $rule->volts = null;
            }
            $catalogOptions = array();
            for ($j = 1; $j < count($table); $j++) {
                if ($table[$j][$i] == '') {
                    break;
                } else {
                    $catalogOptionSpecification = array();
                    for ($k = $i + 1; $k < count($table[$j]); $k++) {
                        if ($table[$j][$k] == '' || in_array($k, $ruleCols, true) || in_array($k, $specRuleCols, true)) {
                            break;
                        } else {
                            array_push($catalogOptionSpecification, $table[0][$k]." -- ".$table[$j][$k]);
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

    for ($i =0; $i < count($table[0]); $i++) {
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
                            array_push($catalogOptionSpecification, $table[0][$k]." -- ".$table[$j][$k]);
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

    /** Build the products from their catalog numbers using the rules */
    foreach ($products as $product) {
        foreach ($rules as $rule) {
            if ($rule->hasVolts && !(strcmp($product->volts, $rule->volts) === 0)) {
                continue;
            } else {
                $catalogOption = substr($product->partNumber, $rule->consume_start, ($rule->consume_end - $rule->consume_start));
                if (!isset($rule->catalogOptions[$catalogOption])) {
                    $errorCount++;
                    continue;
                }
                $specs = $rule->catalogOptions[$catalogOption];
                foreach ($specs as $spec) {
                    $specExplode = explode(" -- ", $spec);
                    $product->specifications[$specExplode[0]] = $specExplode[1];
                    $voltFound = preg_match("/\d+V\sAC|\d+V\sDC/", $spec, $voltMatch, PREG_OFFSET_CAPTURE);
                    if ($voltFound === false) {
                        echo("Error in regex matching; looking for Volt rules -- exiting");
                        exit();
                    } else if ($voltFound) {
                        $voltage = substr($spec, $voltMatch[0][1]);
                        $voltage = explode(" ", $voltage);
                        $voltage = $voltage[0];
                        $product->volts = $voltage;
                    }
                }
            }
        }

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

            foreach(array_keys($rule->catalogOptions) as $option) {
                $match = true;
                $specOptions = explode(" -- ", $option);
                for ($i = 0; $i < count($specOptions); $i++) {

                    $rangeFound = preg_match("/\d+-\d+/", $specsI[$i], $rangeMatch, PREG_OFFSET_CAPTURE);

                    if ($rangeFound) {
                        $range = substr($specsI[$i], $rangeMatch[0][1]);
                        $range = explode('-', $range);
                        $start = intval($range[0]) - 1;
                        $end = intval($range[1]);

                        $catalogOption = substr($product->partNumber, $start, $end - $start);
                    } else {
                        $catalogOption = $product->specifications[$specsI[$i]];
                    }
                    if ($catalogOption !== $specOptions[$i]) {
                        $match = false;
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

        $product->specifications['MPN'] = $product->partNumber;
        $x = mt_rand(0, 1000);
        if ($x >= 750) {
            array_push($sample, $product);
        }
        $processed++;
    }

    /** Insert into the database */
    foreach ($products as $product) {
        /** First delete all existing specifications for that particular product */
        $query = $db->prepare("DELETE FROM technical_specs WHERE product_id = ?");
        $query->execute(array($product->product_id));

        $query = $db->prepare("DELETE FROM dimensions WHERE product_id = ?");
        $query->execute(array($product->product_id));

        $count = 0;
        foreach ($keys as $key) {
            if (isset($product->specifications[$key])) {
                $query = $db->prepare("INSERT INTO technical_specs (product_id, spec, stat, display_order)
                                       VALUES (?, ?, ?, ?)");
                if (!$query->execute(array($product->product_id, $key, $product->specifications[$key], $count++))) {
                    echo("There was an error!");
                }
            }
        }

        $count = 0;
        foreach($dimensions as $dimension) {
            if (isset($product->specifications[$dimension])) {
                $query = $db->prepare("INSERT INTO dimensions (product_id, spec, stat, display_order)
                                       VALUES (?, ?, ?, ?)");
                if (!$query->execute(array($product->product_id, $dimension, $product->specifications[$dimension], $count++))) {
                    echo("There was an error!");
                }
            }
        }
    }

    /** Generate the CSV */
    $csv = fopen($_SERVER['DOCUMENT_ROOT']."/console/specOutputs/".date('Y-m-d').'_'.$_POST['series'].".csv", 'w');
    // The headers first
    fwrite($csv, "Product Number;");
    for ($i = 0; $i < count($keys) - 1; $i++) {
        fwrite($csv, $keys[$i].";");
    }
    fwrite($csv, $keys[$i]);
    fwrite($csv, "\n");
    //Then the body
    foreach ($products as $product) {
        fwrite($csv, $product->partNumber.";");
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (isset($product->specifications[$keys[$i]])) {
                fwrite($csv, $product->specifications[$keys[$i]].";");
            } else {
                fwrite($csv, ";");
            }
        }
        if (isset($product->specifications[$keys[$i]])) {
            fwrite($csv, $product->specifications[$keys[$i]]);
        }

        for ($i = 0; $i < count($dimensions) - 1; $i++) {
            if (isset($product->specifications[$dimensions[$i]])) {
                fwrite($csv, $product->specifications[$dimensions[$i]].";");
            } else {
                fwrite($csv, ";");
            }
        }
        if (isset($product->specifications[$dimensions[$i]])) {
            fwrite($csv, $product->specifications[$dimensions[$i]]);
        }
        
        fwrite($csv, "\n");
    }
    $download = true;


    /** Small piece of code to generate table used for checking the correctness of the script */
    $htmlBody = "<table class='tg' style='width: 100%;><tr><th class='tg-baqh' colspan='50'>Random Sample of Processed Products -- Check for Correctness</th></tr><tr>";
    $htmlBody = $htmlBody."<td class='tg-buh4'>Product Number</td>";
    foreach ($keys as $key) {
        $htmlBody = $htmlBody."<td class='tg-buh4'>".$key."</td>";
    }
    $htmlBody = $htmlBody."</tr>";

    foreach ($sample as $product) {
        $htmlBody = $htmlBody."<tr>";
        $htmlBody = $htmlBody."<td class='tg-p50z'>".$product->partNumber."</td>";
        foreach ($keys as $key) {
            if (isset($product->specifications[$key])) {
                $htmlBody = $htmlBody."<td class='tg-p50z'>".$product->specifications[$key]."</td>";
            } else {
                $htmlBody = $htmlBody."<td class='tg-p50z'> </td>";
            }
        }
        $htmlBody = $htmlBody."</tr>";
    }
    $htmlBody = $htmlBody."</table>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, initial-scale=1, maximum-scale=1">
    <title>Admin | PHD Supply</title>

    <link rel="stylesheet" href="https://use.fontawesome.com/5e444247d9.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
</head>
<style type="text/css">
    .tg  {border-collapse:collapse;border-color:#ccc;border-spacing:0;}
    .tg td{background-color:#fff;border-color:#ccc;border-style:solid;border-width:1px;color:#333;
        font-family:Arial, sans-serif;font-size:14px;overflow:hidden;padding:10px 5px;word-break:normal;}
    .tg th{background-color:#f0f0f0;border-color:#ccc;border-style:solid;border-width:1px;color:#333;
        font-family:Arial, sans-serif;font-size:14px;font-weight:normal;overflow:hidden;padding:10px 5px;word-break:normal;}
    .tg .tg-baqh{text-align:center;vertical-align:top}
</style>
<body>
<div class="container">
    <div class="row">
        <div class="col-lg-12" id="mainBody">
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="1305.php" style="text-align: center">
                <h2 class="ui dividing header">Powerflex Specification Generation</h2>
                <h3 class="ui dividing header">Upload product 'rule' file</h3>
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000"/>
                <div class="row">
                    <label for="file" class="ui icon button">
                        <i class="fa fa-file" aria-hidden="true"></i>
                        Open File
                    </label>
                    <br />
                    <input type="file" id="file" name="productCSV" style="display: none;"/>
                    <input type="radio" id="1305seriesC" name="series" value="1305seriesC">
                    <label for="1305seriesC">1305-Series C</label><br />
                </div>
                <button class="ui button" type="submit">Submit</button>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-11" style="text-align: left;">
            <?php if ($errorCount > 0) { echo($errorCount." errors of ".$processed." processed products."); } ?>
        </div>
        <div class="col-lg-1">
            <a href="<?php echo("/console/specOutputs/".date('Y-m-d').'_'.$_POST['series'].".csv"); ?>" download="<?php echo(date('Y-m-d').'_'.$_POST['series'].".csv"); ?>">
                <button class="btn btn-primary <?php if (!isset($download)) { echo("hidden"); } ?>">.CSV</button>
            </a>
        </div>
    </div>
</div>
<div class="col-lg-12" style="overflow-x: scroll">
    <?php if (isset($htmlBody)) { echo($htmlBody); } ?>
</div>

</body>

</html>
