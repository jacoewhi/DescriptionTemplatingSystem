<?php

ini_set('max_execution_time', '60');

$content = "";

if (isset($_POST['generateContent'])) {

	$host = '172.31.62.194';
	$db = 'vfdsupply';
	$user = 'web';
	$pass = '7wdNFs53ZV^xWN#g';
	$port = "3306";
	$charset = 'utf8';

	$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";
	$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

	$vfdpdo = new PDO($dsn, $user, $pass, $options);

	$host = '172.31.4.189';
	$db = 'catalog_service';
	$user = 'remote-read';
	$pass = 'vN5vG##uv^VmJ8T6!RHt!X';
	$port = "3306";
	$charset = 'utf8';

	$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";
	$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

	$cspdo = new PDO($dsn, $user, $pass, $options);

	$stmt = $cspdo->prepare("SELECT `partNumber` FROM `part_numbers` WHERE `partNumber` LIKE '20%' OR `partNumber` LIKE '1305%' OR `partNumber` LIKE '22%' OR `partNumber` LIKE '25%' OR `partNumber` LIKE '1336%'");
	$stmt->execute();
	$csPartNumbers = $stmt->fetchAll();

	try {
		$vfdpdo->beginTransaction();

		$count = 0;
		foreach ($csPartNumbers as $csPartNumber) {
			$length = 0;
			if (strpos($csPartNumber['partNumber'], '20A') == 0 ||
				strpos($csPartNumber['partNumber'], '20B') == 0 ||
				strpos($csPartNumber['partNumber'], '20D') == 0) {
				$length = 7;
			} elseif (strpos($csPartNumber['partNumber'], '22A') == 0 ||
				strpos($csPartNumber['partNumber'], '22B') == 0 ||
				strpos($csPartNumber['partNumber'], '22C') == 0 ||
				strpos($csPartNumber['partNumber'], '22D') == 0 ||
				strpos($csPartNumber['partNumber'], '22F') == 0 ||
				strpos($csPartNumber['partNumber'], '25A') == 0 ||
				strpos($csPartNumber['partNumber'], '25B') == 0 ||
				strpos($csPartNumber['partNumber'], '25C') == 0) {
				$length = 8;
			} elseif (strpos($csPartNumber['partNumber'], '1305') == 0 ||
				strpos($csPartNumber['partNumber'], '20F') == 0 ||
				strpos($csPartNumber['partNumber'], '20G') == 0 ||
				strpos($csPartNumber['partNumber'], '21G') == 0 ||
				strpos($csPartNumber['partNumber'], '1336-') == 0) {
				$length = 9;
			} elseif (strpos($csPartNumber['partNumber'], '1336T') == 0) {
				$length = 10;
			} elseif (strpos($csPartNumber['partNumber'], '1336E') == 0 ||
				strpos($csPartNumber['partNumber'], '1336F') == 0 ||
				strpos($csPartNumber['partNumber'], '1336S') == 0 ||
				strpos($csPartNumber['partNumber'], '1336VT') == 0) {
				$length = 11;
			}

			$substring = null;

			if ($length !== 0) {
				$substring = substr($csPartNumber['partNumber'], 0, $length);
			}

			if ($substring !== null && $substring !== false) {
				$stmt = $vfdpdo->prepare("UPDATE `listings` SET enabled = 1 WHERE `name` LIKE '$substring%'");
				$stmt->execute();
				$count++;
				if ($count % 100 == 0) {
				    error_log($count . "|" . $substring);
                }
			}
		}

		$vfdpdo->commit();

	} catch (Exception $e) {
		echo("error during update");
		$vfdpdo->rollBack();
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, initial-scale=1, maximum-scale=1">
    <title>Admin | PDF Supply</title>

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
            <form class="col-lg-12" enctype="multipart/form-data" method="post" action="script.php"
                  style="text-align: center">
                <h2 class="ui dividing header">Revisions Report</h2
                <input type="hidden" name="MAX_FILE_SIZE" value="10000000"/>
                <div class="row">
                    <label for="generateContent">Generate Content</label>
                    <input type="radio" id="generateContent" name="generateContent">
                </div>
                <button class="ui button" type="submit">Submit</button>
            </form>
        </div>
    </div>
	<?php echo $content; ?>
</div>

</body>

</html>