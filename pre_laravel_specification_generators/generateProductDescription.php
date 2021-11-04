<?php

//include($_SERVER["DOCUMENT_ROOT"] . "/dynamicProductPageLibrary/productDescriptionFunctions.php");
//require __DIR__ . '/vendor/autoload.php';
//
//putenv("GOOGLE_APPLICATION_CREDENTIALS=" . __DIR__ . "/googleNaturalLanguageAPI/soy-coast-298614-3dc2e2d7408e.json");
//
//use Google\Cloud\Language\V1\Document;
//use Google\Cloud\Language\V1\Document\Type;
//use Google\Cloud\Language\V1\LanguageServiceClient;
//use Google\Cloud\Language\LanguageClient;

class Product
{
    public $product_info;
    public $techSpecs_info;
    public $displayDescription_info;
    public $oldDescriptionContent;
    public $newDescription;
    public $newDescriptionContent;
}

$start = time();
$sample = array();
$htmlBody = null;
$errorCount = 0;
$betterCount = 0;
$processed = 0;
$products = array();

//for testing development purposes
error_reporting(E_ALL);

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost:3306");

define("partName", "Product");
define("partPattern", "2715-");

$specBlacklist = ["Company", "Type", "Series", "Manufacturer", "Safe-Off", "MPN", "w/Brake IGBT", "w/Resistor", "Filtering", "Type"]; // types of specs not to list

//$projectId = 'googleCloudTranslationAPI';
//$language = new LanguageServiceClient(['projectId' => $projectId]);

/** Try to connect to the database */
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
    global $primaryDatabase;
    $primaryDatabase = $db;
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo("Connection failed - " . $e->getMessage());
}
if (isset($_POST['series'])) {
    if ($_POST['series'] == partName) {
        ini_set('memory_limit', '16000M');
        ini_set('max_execution_time', 1240);
        //$query = $db->prepare("SELECT * FROM products WHERE part_number LIKE '104-E116%' OR part_number LIKE '1756-TIME' OR part_number LIKE '2715-%' OR part_number LIKE '1760-L12%'");
        $query = $db->prepare("SELECT * FROM products LIMIT 163000 OFFSET 326000");
        $query->execute();
        $query = $query->fetchAll(PDO::FETCH_CLASS);
    }

    foreach ($query as $result) {
        $product = new Product();
        $product->product_info = $result;
        $specsQuery = $db->prepare("SELECT * FROM technical_specs WHERE product_id = {$product->product_info->id}");
        $specsQuery->execute();
        $product->techSpecs_info = $specsQuery->fetchAll();
        $basicSpecs = 0;
        $extraSpecs = 0;
        foreach ($product->techSpecs_info as $techSpec) {
            if ($techSpec['spec'] == 'MPN') {
                $mpn = $techSpec['stat'];
            } else if ($techSpec['spec'] == 'Series') {
                $series = $techSpec['stat'];
                $basicSpecs++;
            } else if ($techSpec['spec'] == 'Manufacturer') {
                $manufacturer = $techSpec['stat'];
                $basicSpecs++;
            } else if ($techSpec['spec'] == 'Product Type') {
                $productType = $techSpec['stat'];
                $product->product_type = $productType;
                $basicSpecs++;
            } else if ($techSpec['display_order'] >= 3) {
                $topSpecs[$techSpec['display_order']][0] = $techSpec['spec'];
                $topSpecs[$techSpec['display_order']][1] = $techSpec['stat'];
                $extraSpecs++;
                if ($extraSpecs >= 3) {
                    break;
                }
            }
        }

        $description = "";
        $descriptionContent = "";
        $vowels = ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'];
        $modules = ['module', 'unit', 'device', 'component', 'part', 'item'];
        $hasA = ['has', 'comes with', 'boasts', 'includes', 'presents', 'incorporates', 'offers', 'features'];
        shuffle($modules);
        shuffle($hasA);

        $description = "This " . $manufacturer . " " . rtrim($series, 's') . " " . rtrim($productType, 's') . " " . $hasA[0] . " a";
        if (in_array($topSpecs[$basicSpecs][0][0], $vowels)) {
            $description .= "n";
        }
        $description .= " ";
        $description .= rtrim($topSpecs[$basicSpecs][0], 's') . " of " . $topSpecs[$basicSpecs][1] . ". ";
        $description .= "This " . $modules[0] . " " . $hasA[1] . " a";
        if (in_array($topSpecs[$basicSpecs + 1][0][0], $vowels)) {
            $description .= "n";
        }
        $description .= " ";
        $description .= rtrim($topSpecs[$basicSpecs + 1][0], 's') . " of " . $topSpecs[$basicSpecs + 1][1] . ". ";
        $description .= "This " . $modules[1] . " also " . $hasA[2] . " a";
        if (in_array($topSpecs[$basicSpecs + 2][0][0], $vowels)) {
            $description .= "n";
        }
        $description .= " ";
        $description .= rtrim($topSpecs[$basicSpecs + 2][0], 's') . " of " . $topSpecs[$basicSpecs + 2][1] . ".";

        if (isset($result->written_description) && $result->written_description != null) {
            $product->newDescription = $result->written_description;
        } else {
            $product->newDescription = $description;
        }
        $product->newDescription = str_replace("…", "...", $product->newDescription);
        $product->newDescriptionContent = null;
//        try {
//            $document = (new Document())
//                ->setContent($product->newDescription)
//                ->setType(Type::PLAIN_TEXT);
//            foreach ($language->classifyText($document)->getCategories() as $category) {
//                $product->newDescriptionContent .= $category->getName();
//            }
//        } catch (\Google\ApiCore\ApiException $e) {
//            $product->newDescriptionContent = null;
//        }
//        try {
//            $document = (new Document())
//                ->setContent($product->product_info->description)
//                ->setType(Type::PLAIN_TEXT);
//            foreach ($language->classifyText($document)->getCategories() as $category) {
//                $product->oldDescriptionContent .= $category->getName();
//            }
//        } catch (\Google\ApiCore\ApiException $e) {
//            $product->oldDescriptionContent = null;
//        }

        $processed++;
        if (strpos($product->newDescription, "of .") === false && strpos($product->newDescription, "a  of") === false) {
            $betterCount++;
            array_push($products, $product);
        }
    }

    $sql = "";

    foreach ($products as $product) {
        $query = $db->prepare("DELETE FROM product_display_descriptions WHERE product_id = ?");
        $query->execute(array($product->product_info->id));

        $query = $db->prepare("INSERT INTO product_display_descriptions (id, product_id, description, description_content) VALUES (?,?,?,?)");
        error_log($product->product_info->part_number);
        $query->execute(array(0, $product->product_info->id, $product->newDescription, $product->newDescriptionContent));

//        $sql .= "INSERT INTO product_display_descriptions (id, product_id, description, description_content)
//                                           VALUES (" . $product->product_info->id . "," . $product->product_info->id . ",`" . $product->newDescription . "`,`" . $product->newDescriptionContent . "`); ";
    }
//    /** Generate the SQL */
//    $sqlFile = fopen($_SERVER['DOCUMENT_ROOT'] . "/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".sql", 'w');
//    fwrite($sqlFile, $sql);
//    echo $sql;
//    $query = $db->prepare($sql);
//    $query->execute();
}

/** Generate the CSV */
$csv = fopen($_SERVER['DOCUMENT_ROOT'] . "/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".csv", 'w');
// The headers first
$keys = array();
array_push($keys, "Old Description");
array_push($keys, "Old Description Content");
array_push($keys, "New Description");
array_push($keys, "New Description Content");
$headers = $keys;
array_unshift($headers, "Product Number");
fputcsv($csv, $headers);
//Then the body
foreach ($products as $product) {
    $outputLine = array();
    array_push($outputLine, $product->product_info->part_number);
    array_push($outputLine, $product->product_info->description);
    array_push($outputLine, $product->oldDescriptionContent);
    array_push($outputLine, $product->newDescription);
    array_push($outputLine, $product->newDescriptionContent);
    fputcsv($csv, $outputLine);
}
$download = true;

//$sample = $products;

/** Small piece of code to generate table used for checking the correctness of the script */
$htmlBody = "<table class='tg' style='width: 100%;><tr><th class='tg-baqh' colspan='50'>Random Sample of Processed Products -- Check for Correctness</th></tr><tr>";
$htmlBody = $htmlBody . "<td class='tg-buh4'>Product Number</td>";
foreach ($keys as $key) {
    $htmlBody = $htmlBody . "<td class='tg-buh4'>" . $key . "</td>";
}
$htmlBody = $htmlBody . "</tr>";

foreach ($sample as $product) {
    $htmlBody = $htmlBody . "<tr>";
    $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->product_info->part_number . "</td>";
    $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->product_info->description . "</td>";
    $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->oldDescriptionContent . "</td>";
    $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->newDescription . "</td>";
    $htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->newDescriptionContent . "</td>";
    $htmlBody = $htmlBody . "</tr>";
}
$htmlBody = $htmlBody . "</table>";
echo(time() - $start);
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
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="generateProductDescription.php"
                  style="text-align: center">
                <h2 class="ui dividing header">Product Description Generation</h2>
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000"/>
                <div class="row">
                    <input type="radio" id="Product" name="series" value="Product">
                    <label for="Product">Product</label><br/>
                </div>
                <button class="ui button" type="submit">Submit</button>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-11" style="text-align: left;">
            <?php echo($betterCount . " improved out of " . $processed . " processed products."); ?>
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
        <div class="col-lg-1">
            <a href="<?php echo("/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".sql"); ?>"
               download="<?php echo(date('Y-m-d') . '_' . $_POST['series'] . ".sql"); ?>">
                <button class="btn btn-primary <?php if (!isset($download)) {
                    echo("hidden");
                } ?>">.SQL
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