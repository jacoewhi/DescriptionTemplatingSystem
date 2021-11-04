<?php
class Rule {
    //Each rule has options (characters consumed will match one of these options)
    //Each option has specifications
    public $consume_start;
    public $consume_end;
    public $catalogOptions;
    public $header;
}

class Product {
    public $product_id;
    public $partNumber;
    public $specifications;
}
/** Various Globals -- Grouped Together by Relevance */

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost");

define("rulePattern", "/[A-Z]\d+\s--\s\d+-\d+|[A-Z]\s--\s\d+-\d+/");

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
$sample = array();
$htmlBody = null;
$errorCount = 0;
$processed = 0;
$products = array();

/** Check if a series was selected and query the selected series */
if (isset($_POST['series'])) {
    if ($_POST['series'] === "mpm") {
        $query = $db->prepare("SELECT * FROM products WHERE manufacturers_id = 46 AND Length(part_number) = 17 AND part_number LIKE 'MPM%'");
        $query->execute();
        $query = $query->fetchAll();
    } else if ($_POST['series'] === "mpl") {
        $query = $db->prepare("SELECT * FROM products WHERE manufacturers_id = 46 AND Length(part_number) >= 16 AND part_number LIKE 'MPL%'");
        $query->execute();
        $query = $query->fetchAll();
    } else if ($_POST['series'] === "1326") {
        $query = $db->prepare("SELECT * FROM products WHERE manufacturers_id = 46 AND Length(part_number) = 16 AND part_number LIKE '1326AB%'");
        $query->execute();
        $query = $query->fetchAll();
    } else if ($_POST['series'] === "vpl") {
        $query = $db->prepare("SELECT * FROM products WHERE manufacturers_id = 46 AND Length(part_number) >= 17 AND part_number LIKE 'VPL%'");
        $query->execute();
        $query = $query->fetchAll();
    }

    /** Create array of products to be processed - omit parts which do not meet the catalog number set by the manual
     *  MPM Series - https://literature.rockwellautomation.com/idc/groups/literature/documents/in/mpm-in001_-en-p.pdf
     *  MPL Series - https://literature.rockwellautomation.com/idc/groups/literature/documents/in/mp-in001_-en-p.pdf
     *  1326 Series - https://s3.amazonaws.com/Icarus/DOCUMENTS/Allen-Bradley_Manuals_1098.pdf
     */
    foreach ($query as $result) {
        if ($_POST['series'] === 'mpm') {
            if (!(preg_match("/MPM-[A-Z0-9]{6}-[A-Z0-9]{6}/", $result['part_number']))) {
                continue;
            }
        } else if ($_POST['series'] === 'mpl') {
            if (!(preg_match("/MPL-[A-Z0-9]{5}-[A-Z0-9]{6}|MPL-[A-Z0-9]{6}-[A-Z0-9]{6}/", $result['part_number'])) || (strlen($result['part_number']) > 17 )) {
                continue;
            }
        } else if ($_POST['series'] === '1326') {
            if (!preg_match("/1326AB-[A-Z0-9]{3}-[A-Z0-9]{2}-[A-Z0-9]{2}/", $result['part_number'])) {
                continue;
            }
        } else if ($_POST['series'] === 'vpl') {
            if (!preg_match("/VPL-[A-Z0-9]{6}-[A-Z0-9]{6}/", $result['part_number'])) {
                continue;
            }
        }
        $product = new Product();
        $product->product_id = $result['id'];
        $product->partNumber = $result['part_number'];
        $product->specifications = array();
        array_push($products, $product);
    }
}

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

    $ruleCols = array();
    for ($i = 0; $i < count($table[0]); $i++) {
        //Check if it is a rule, otherwise specification
        $ruleFound = preg_match(rulePattern, $table[0][$i]);
        if ($ruleFound === false) {
            //Error occurred, exit before the script has a chance to run for too long
            echo("Error in regex matching of headers to rules -- exiting");
            exit();
        } else if ($ruleFound) {
            //Add the index to the array keeping track of rule columns
            array_push($ruleCols, $i);
        } else {
            //Specification, skip this
            continue;
        }
    }

    /** Very quickly before building the rule objects, let take out all the specification headers (keys)
     *  to use later when building $htmlBody and for generating the CSV
     */
    $keys = array();
    array_push($keys, 'MPN');
    for ($i = 0; $i < count($headers); $i++) {
        if (in_array($i, $ruleCols, true) || in_array($headers[$i], $keys, true)) {
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
                        if ($table[$j][$k] == '' || in_array($k, $ruleCols, true)) {
                            break;
                        } else {
                            array_push($catalogOptionSpecification, $table[0][$k]." -- ".$table[$j][$k]);
                        }
                    }
                    $catalogOptions[$table[$j][$i]] = $catalogOptionSpecification;
                }
            }
            $rule->catalogOptions = $catalogOptions;
            array_push($rules, $rule);
        } else {
            continue;
        }
    }

    /**
     * Build the products from their catalog numbers using the rules
     *
     * Despite having one of the more simple catalog numbers, the MPL servo motor series has a lot of moving
     * parts, as such it is easier to write some custom code for handling it rather than trying to write generic
     * code that will handle all servo motors equally
     */
    if ($_POST['series'] === "mpl") {
        foreach ($products as $product) {
            for ($i = 0; $i < count($rules); $i++) {
                $rule = $rules[$i];
                if (strlen($product->partNumber) > 16 && $i == 1) {
                    $catalogOption = substr($product->partNumber, $rule->consume_start, ($rule->consume_end + 1 - $rule->consume_start));
                } else if (strlen($product->partNumber) > 16 && $i > 1) {
                    $catalogOption = substr($product->partNumber, $rule->consume_start + 1, ($rule->consume_end - $rule->consume_start));
                } else {
                    $catalogOption = substr($product->partNumber, $rule->consume_start, ($rule->consume_end - $rule->consume_start));
                }
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
            $product->specifications['MPN'] = $product->partNumber;
            $x = mt_rand(0, 1000);
            if ($x >= 975) {
                array_push($sample, $product);
            }
            $processed++;
        }
    } else {
        foreach ($products as $product) {
            foreach ($rules as $rule) {
                $catalogOption = substr($product->partNumber, $rule->consume_start, ($rule->consume_end - $rule->consume_start));
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
            $product->specifications['MPN'] = $product->partNumber;
            $x = mt_rand(0, 1000);
            if ($x >= 975) {
                array_push($sample, $product);
            }
            $processed++;
        }
    }

    /** Insert into the database */
    foreach ($products as $product) {
        /** First delete all existing specifications for that particular product */
        $query = $db->prepare("DELETE FROM technical_specs WHERE product_id = ?");
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
    }

    /** Generate the CSV */
    $csv = fopen($_SERVER['DOCUMENT_ROOT']."/console/specOutputs/".date('Y-m-d').'_'.$_POST['series'].".csv", 'w');
    // The headers first
	$headers = $keys;
	array_unshift($headers, "Product Number");
	fputcsv($csv, $headers);
    //Then the body
    foreach ($products as $product) {
	    $outputLine = array();
	    array_push($outputLine, $product->partNumber);

        for ($i = 0; $i < count($keys) - 1; $i++) {     //Although this might seem janky, it solves the fence post problem
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

    /** Small piece of code to generate table used for checking the correctness of the script */
    $htmlBody = "<table class='tg' style='width: 100%'><tr><th class='tg-baqh' colspan='50'>Random Sample of Processed Products -- Check for Correctness</th></tr><tr>";
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
    .tg .tg-buh4{background-color:#f9f9f9;text-align:left;vertical-align:top}
    .tg .tg-lqy6{text-align:right;vertical-align:top}
    .tg .tg-0lax{text-align:left;vertical-align:top}
    .tg .tg-p5oz{background-color:#f9f9f9;text-align:right;vertical-align:top}
</style>
<body>
<div class="container">
    <div class="row">
        <div class="col-lg-12" id="mainBody">
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="servomotors.php" style="text-align: center">
                <h2 class="ui dividing header">Servo Motor Specification Generation</h2>
                <h3 class="ui dividing header">Upload product 'rule' file</h3>
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000"/>
                <div class="row">
                    <label for="file" class="ui icon button">
                        <i class="fa fa-file" aria-hidden="true"></i>
                        Open File
                    </label>
                    <br />
                    <input type="file" id="file" name="productCSV" style="display: none;"/>
                    <input type="radio" id="mpm" name="series" value="mpm">
                    <label for="mpm">MPM Series</label><br />
                    <input type="radio" id="mpl" name="series" value="mpl">
                    <label for="mpl">MPL Series</label><br />
                    <input type="radio" id="1326" name="series" value="1326">
                    <label for="1326">1326 Series</label><br />
                    <input type="radio" id="vpl" name="series" value="vpl">
                    <label for="vpl">VPL Series</label><br />
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
<div class="col-lg-12">
    <?php if (isset($htmlBody)) { echo($htmlBody); } ?>
</div>

</body>

</html>