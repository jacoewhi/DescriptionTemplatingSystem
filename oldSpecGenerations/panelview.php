<?php
class Rule
{
    //Each rule has options (characters consumed will match one of these options)
    //Each option has specifications
    public $consume_start;
    public $consume_end;
    public $catalogOptions;
    public $header;
}

class Product
{
    public $product_id;
    public $partNumber;
    public $specifications;
}

/** Various Globals -- Grouped Together by Relevance */

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost");

define("specialRulePattern", "/\*( -- \w*)+/");
define("rulePattern", "/[A-Z]\d+\s--\s\d+-\d+|[A-Z]\s--\s\d+-\d+/");

/** Try to connect to the database */
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo("Connection failed - " . $e->getMessage());
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
$products = array();

if (isset($_POST['series'])) {

    $query = $db->prepare("SELECT * FROM products WHERE part_number LIKE '2711P%'");
    $query->execute();
    $query = $query->fetchAll();

    $products = array();
    foreach ($query as $result) {
        if ($_POST['series'] === 'pv6') {
            if (!preg_match("/2711P-[A-Z0-9]{6,7}\b/", $result['part_number'])) {
                continue;
            }
        } else if ($_POST['series'] === 'pv7') {
            if (!preg_match("/2711P-[A-Z0-9]{6,}[P,S]\b/", $result['part_number'])) {
                continue;
            }
        }
        $product = new Product();
        $product->partNumber = $result['part_number'];
        $product->id = $result['id'];
        $product->specifications = array();
        array_push($products, $product);
    }
}

if (isset($_FILES['productCSV'])) {
    try  {
        $file = fopen($_FILES['productCSV']['tmp_name'], 'r');
    } catch (Exception $e) {
        echo("Failed to open file -- ".$e->getMessage());
        exit("Script stopped running.\n");
    }

    $headers = fgetcsv($file);
    for ($i = 0; $i < count($headers); $i++) {
        $table[0][$i] = $headers[$i];
    }

    $lines = array();
    $line = fgetcsv($file);
    while ($line) {
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
    $specRuleCols = array();
    for ($i = 0; $i < count($table[0]); $i++) {
        $ruleFound = preg_match(rulePattern, $table[0][$i]);
        $specRuleFound = preg_match(specialRulePattern, $table[0][$i]);
        if ($ruleFound === false || $specRuleFound === false) {
            echo("Error in regex matching of headers to rules -- exiting.");
            exit();
        } else if ($ruleFound) {
            array_push($ruleCols, $i);
        } else if ($specRuleFound) {
            array_push($specRuleCols, $i);
        } else {
            continue;
        }
    }

    $keys = array();
    array_push($keys, 'MPN');
    for ($i = 0; $i < count($headers); $i++) {
        if (in_array($i, $ruleCols, true) || in_array($i, $specRuleCols, true) || in_array($headers[$i], $keys, true)) {
            continue;
        }
        array_push($keys, $headers[$i]);
    }

    for ($i = 0; $i < count($table[0]); $i++) {
        if (in_array($i, $ruleCols, true)) {
            $rangeFound = preg_match("/\d+-\d+/", $table[0][$i], $rangeMatch, PREG_OFFSET_CAPTURE);
            if ($rangeFound === false) {
                echo("Error in regex matching; looking for Catalog Number consumption range -- exiting");
                exit();                
            }
            
            $range = substr($table[0][$i], $rangeMatch[0][1]);
            $range = explode('-', $range);
            $start = intval($range[0]) - 1;
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
                    for ($k = $i + 1; $k < count ($table[$j]); $k++) {
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

    for ($i = 0; $i < count($table[0]); $i++) {
        if (in_array($i, $specRuleCols, true)) {
            $rule = new Rule();
            $catalogOptions = array();
            for ($j = 1; $j < count($table); $j++) {
                if ($table[$i][$j] == '') {
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
                    $catalogOptions[$table[$j][$k]] = $catalogOptionSpecification;
                }
            }
            $rule->catalogOptions = $catalogOptions;
            $rule->header = $table[0][$i];
        } else {
            continue;
        }
    }

    foreach ($products as $product) {
        // Keep track of the offset
        $offset = 0;
        foreach ($rules as $rule) {
            $diff = $rule->consume_end - $rule->consume_start;

            // Try to find a match at the current offset
            $catalogOption = substr($product->partNumber, $rule->consume_start - $offset, ($rule->consume_end - $rule->consume_start));

            // Check if the current catalog option matches an existing option
            if (isset($rule->catalogOptions[$catalogOption])) {
                $specs = $rule->catalogOptions[$catalogOption];
                foreach ($specs as $spec) {
                    $specExplode = explode(' -- ', $spec);
                    $product->specifications[$specExplode[0]] = $specExplode[1];
                }
                // If it does continue onto the next rule
                continue;
            } else {
                // If we do not have a match, try to find a match by changing how much of the part number is read in 
                for ($i = $diff; $i > 0; $i--) {
                    $catalogOption = substr($product->partNumber, $rule->consume_start, $i);

                    if (isset($rule->catalogOptions[$catalogOption])) {
                        $specs = $rule->catalogOptions[$catalogOption];
                        foreach ($specs as $spec) {
                            $specExplode = explode(' -- ', $spec);
                            $product->specifications[$specExplode[0]] = $specExplode[1];
                        }

                        $offset = $diff - $i;

                        break;
                    }
                }
            }
        }

        foreach ($specialRules as $rule) {
            // do nothing -- no special rules for this one just yet 
        }

        $product->specifications['MPN'] = $product->partNumber;
        $x = mt_rand(0, 1000);
        if ($x >= 975) {
            array_push($sample, $product);
        }
        $processed++;
    
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
    fwrite($csv, "Product Number;");
    for ($i = 0; $i < count($keys) - 1; $i++) {
        fwrite($csv, $keys[$i].";");
    }
    fwrite($csv, $keys[$i]);
    fwrite($csv, "\n");
    //Then the body
    foreach ($products as $product) {
        fwrite($csv, $product->partNumber.";");
        for ($i = 0; $i < count($keys) - 1; $i++) {     //Although this might seem janky, it solves the fence post problem
            if (isset($product->specifications[$keys[$i]]) && $product->specifications[$keys[$i]] !== "BLANK") {
                fwrite($csv, $product->specifications[$keys[$i]].";");
            } else {
                fwrite($csv, ";");
            }
        }
        if (isset($product->specifications[$keys[$i]])) {
            fwrite($csv, $product->specifications[$keys[$i]]);
        }
        fwrite($csv, "\n");
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
            if (isset($product->specifications[$key]) && $product->specifications[$key] !== "BLANK") {
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
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="panelview.php" style="text-align: center">
                <h2 class="ui dividing header">IMPACT Drive Specification Generation</h2>
                <h3 class="ui dividing header">Upload product 'rule' file</h3>
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000"/>
                <div class="row">
                    <label for="file" class="ui icon button">
                        <i class="fa fa-file" aria-hidden="true"></i>
                        Open File
                    </label>
                    <br />
                    <input type="file" id="file" name="productCSV" style="display: none;"/>
                    <input type="radio" id="pv6" name="series" value="pv6">
                    <label for="pv6">PanelView 6 Plus Series</label><br />
                    <input type="radio" id="pv7" name="series" value="pv7">
                    <label for="pv7">PanelView 7 Plus Series</label><br />
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