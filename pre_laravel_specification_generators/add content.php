<?php

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
    public $partNumber;
    public $unseparatedPartNumber;
    public $specifications;
    public $writtenDescription;
    public $keyfeatures;
}

//for testing development purposes
error_reporting(E_ALL);

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost:3306");

define("partName", "add content");
define("partDBPattern", "");
define("partPattern", "");
define("rulePattern", "/[A-Z]\d+\s--\s\d+-\d+|[A-Z]\s--\s\d+-\d+/");
define("specialRulePattern", "/\*/");

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

if (isset($_FILES['productCSV'])) {
    /** Try to open the file */
    try {
        $file = fopen($_FILES['productCSV']['tmp_name'], 'r');
    } catch (Exception $e) {
        echo("Failed to open file -- " . $e->getMessage());
        exit("Script stopped running\n");
    }

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

    for ($i = 1; $i < count($table); $i++) {
        $product = new Product();
        $product->keyfeatures = array();
        $product->partNumber = $table[$i][0];
        $product->writtenDescription = utf8_encode($table[$i][1]);
        $product->keyfeatures[0] = utf8_encode($table[$i][2]);
        $product->keyfeatures[1] = utf8_encode($table[$i][3]);
        $product->keyfeatures[2] = utf8_encode($table[$i][4]);
        $product->keyfeatures[3] = utf8_encode($table[$i][5]);
        $product->keyfeatures[4] = utf8_encode($table[$i][6]);
        array_push($products, $product);
    }

    foreach ($products as $product) {
        $query = $db->prepare("UPDATE products SET written_description = ? WHERE part_number = ?");
        $query->execute([$product->writtenDescription, $product->partNumber]);

        $query = $db->prepare("SELECT * FROM products WHERE part_number = ?");
        $query->execute([$product->partNumber]);
        $DBProduct = $query->fetch(PDO::FETCH_ASSOC);

        if ($DBProduct['id'] == null) {
            continue;
        }

        $query = $db->prepare("DELETE FROM product_key_features WHERE product_id = ?");
        $query->execute([$DBProduct['id']]);

        $query = $db->prepare("INSERT INTO product_key_features (id, product_id, display_order, key_feature) VALUES (?,?,?,?)");
        $query->execute([0, $DBProduct['id'], 1, $product->keyfeatures[0]]);

        $query = $db->prepare("INSERT INTO product_key_features (id, product_id, display_order, key_feature) VALUES (?,?,?,?)");
        $query->execute([0, $DBProduct['id'], 2, $product->keyfeatures[1]]);

        $query = $db->prepare("INSERT INTO product_key_features (id, product_id, display_order, key_feature) VALUES (?,?,?,?)");
        $query->execute([0, $DBProduct['id'], 3, $product->keyfeatures[2]]);

        $query = $db->prepare("INSERT INTO product_key_features (id, product_id, display_order, key_feature) VALUES (?,?,?,?)");
        $query->execute([0, $DBProduct['id'], 4, $product->keyfeatures[3]]);

        $query = $db->prepare("INSERT INTO product_key_features (id, product_id, display_order, key_feature) VALUES (?,?,?,?)");
        $query->execute([0, $DBProduct['id'], 5, $product->keyfeatures[4]]);
    }

    /** Generate the CSV */
    $csv = fopen($_SERVER['DOCUMENT_ROOT'] . "/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".csv", 'w');
    $keys = array();
    array_push($keys, "Written Description");
    array_push($keys, "Key Features 1");
    array_push($keys, "Key Features 2");
    array_push($keys, "Key Features 3");
    array_push($keys, "Key Features 4");
    array_push($keys, "Key Features 5");
    $headers = $keys;
    // The headers first
    array_unshift($headers, "Product Number");
    fputcsv($csv, $headers);
    //Then the body
    foreach ($products as $product) {
        $outputLine = array();
        array_push($outputLine, $product->partNumber);
        array_push($outputLine, $product->writtenDescription);
        fputcsv($csv, $outputLine);
    }
    $download = true;

    $sample = $products;

    /** Small piece of code to generate table used for checking the correctness of the script */
    $htmlBody = "<table class='tg' style='width: 100%;><tr><th class='tg-baqh' colspan='50'>Random Sample of Processed Products -- Check for Correctness</th></tr><tr>";
    $htmlBody = $htmlBody . "<td class='tg-buh4'>Product Number</td>";
    foreach ($keys as $key) {
        $htmlBody = $htmlBody . "<td class='tg-buh4'>" . $key . "</td>";
    }
    $htmlBody = $htmlBody . "</tr>";

    foreach ($sample as $product) {
        $htmlBody = $htmlBody . "<tr>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->partNumber . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->writtenDescription . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->keyfeatures[0] . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->keyfeatures[1] . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->keyfeatures[2] . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->keyfeatures[3] . "</td>";
        $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->keyfeatures[4] . "</td>";
        $htmlBody = $htmlBody . "</tr>";
    }
    $htmlBody = $htmlBody . "</table>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, initial-scale=1, maximum-scale=1">
    <title>Admin | PHD Supply</title>

    <link rel="stylesheet" href="https://use.fontawesome.com/5e444247d9.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
</head>
<style type="text/css">
    .tg {
        border-collapse: collapse;
        border-color: #ccc;
        border-spacing: 0;
    }

    .tg td {
        background-color: #ffffff;
        border-color: #ccc;
        border-style: solid;
        border-width: 1px;
        color: #333;
        font-family: Arial, sans-serif;
        font-size: 14px;
        overflow: hidden;
        padding: 10px 5px;
        word-break: normal;
    }

    .tg th {
        background-color: #f0f0f0;
        border-color: #ccc;
        border-style: solid;
        border-width: 1px;
        color: #333;
        font-family: Arial, sans-serif;
        font-size: 14px;
        font-weight: normal;
        overflow: hidden;
        padding: 10px 5px;
        word-break: normal;
    }

    .tg .tg-baqh {
        text-align: center;
        vertical-align: top
    }
</style>
<body>
<div class="container">
    <div class="row">
        <div class="col-lg-12" id="mainBody">
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="<?php echo partName ?>.php"
                  style="text-align: center">
                <h2 class="ui dividing header"><?php echo partName ?> Specification Generation</h2>
                <h3 class="ui dividing header">Upload product 'rule' file</h3>
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000"/>
                <div class="row">
                    <label for="file" class="ui icon button">
                        <i class="fa fa-file" aria-hidden="true"></i>
                        Open File
                    </label>
                    <br/>
                    <input type="file" id="file" name="productCSV" style="display: none;"/>
                    <input type="radio" id="<?php echo partName ?>" name="series" value="<?php echo partName ?>">
                    <label for="<?php echo partName ?>"><?php echo partName ?></label><br/>
                </div>
                <button class="ui button" type="submit">Submit</button>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-11" style="text-align: left;">
            <?php if ($errorCount > 0) {
                echo($errorCount . " errors of " . $processed . " processed products.");
            } ?>
        </div>
        <div class="col-lg-1">
            <a href="<?php echo("/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".csv"); ?>"
               download="<?php echo(date('Y-m-d') . '_' . $_POST['series'] . ".csv"); ?>">
                <button class="btn btn-primary <?php if (!isset($download)) {
                    echo("hidden");
                } ?>">.CSV
                </button>
            </a>
        </div>
    </div>
</div>
<div class="col-lg-12" style="overflow-x: scroll">
    <?php if (isset($htmlBody)) {
        echo($htmlBody);
    } ?>
</div>

</body>

</html>