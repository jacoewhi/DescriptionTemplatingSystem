<?php

class Rule {
	public $consume_start;
	public $consume_end;
	public $catalogOptions;
	public $hasVolts;
	public $volts;
	public $header;
}

class Product {
	public $product_id;
	public $partNumber;
	public $unseparatedPartNumber;
	public $specifications;
}

error_reporting(E_ALL);

define("DB_USER", "root");
define("DB_PASSWORD", "astop1@");
define("DB_NAME", "automationstop");
define("DB_HOST", "localhost:3306");

define("partName", "100-D");
define("partDBPattern", "10_-D%");
define("partSimplePattern", "(100-D|104-D)");
define("partPattern", "(100-D|104-D)(\d{3})([A-Z]{1,3})(\d{2}L?)");

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

if (isset($_POST['series'])) {
	if ($_POST['series'] == partName) {
		$query = $db->prepare("SELECT * FROM products WHERE part_number LIKE '" . partDBPattern . "'");
		$query->execute();
		$query = $query->fetchAll();
	}

	foreach ($query as $result) {
		if ($_POST['series'] == partName) {
			if (!preg_match('/' . partSimplePattern . '/', $result['part_number'])) {
				continue;
			}
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
}

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
	}

	/** Insert into the database */
	foreach ($products as $product) {

		$product->partNumber = $product->unseparatedPartNumber;

		/** First delete all existing specifications for that particular product */
		$query = $db->prepare("DELETE FROM technical_specs WHERE product_id = ?");
		$query->execute(array($product->product_id));

		$query = $db->prepare("DELETE FROM dimensions WHERE product_id = ?");
		$query->execute(array($product->product_id));

		$displayOrderSpecs = 0;
		$displayOrderDimensions = 0;
		$specs = array_keys($product->specifications);

		foreach ($specs as $spec) {
			if (isset($product->specifications[$spec])) {
				if (in_array($spec, $keys)) {
					$query = $db->prepare("INSERT INTO technical_specs (product_id, spec, stat, display_order)
                                           VALUES (?, ?, ?, ?)");
					if (!$query->execute(array($product->product_id, $spec, $product->specifications[$spec], $displayOrderSpecs++))) {
						echo("There was an error!");
					}
				} else if (in_array($spec, $dimensions)) {
					$query = $db->prepare("INSERT INTO dimensions (product_id, spec, stat, display_order)
                                           VALUES (?, ?, ?, ?)");
					if (!$query->execute(array($product->product_id, $spec, $product->specifications[$spec], $displayOrderDimensions++))) {
						echo("There was an error!");
					}
				}
			}
		}
	}


	/** Generate the CSV */
	$csv = fopen($_SERVER['DOCUMENT_ROOT'] . "/console/specOutputs/" . date('Y-m-d') . '_' . $_POST['series'] . ".csv", 'w');
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

		for ($i = 0; $i < count($dimensions) - 1; $i++) {
			if (isset($product->specifications[$dimensions[$i]])) {
				array_push($outputLine, $product->specifications[$dimensions[$i]]);
			} else {
				array_push($outputLine, "-");
			}
		}
		if (isset($product->specifications[$dimensions[$i]])) {
			array_push($outputLine, $product->specifications[$dimensions[$i]]);
		} else {
			array_push($outputLine, "-");
		}
		fputcsv($csv, $outputLine);
	}
	$download = true;


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
		foreach ($keys as $key) {
			if (isset($product->specifications[$key])) {
				$htmlBody = $htmlBody . "<td class='tg-p50z'>" . $product->specifications[$key] . "</td>";
			} else {
				$htmlBody = $htmlBody . "<td class='tg-p50z'> </td>";
			}
		}
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