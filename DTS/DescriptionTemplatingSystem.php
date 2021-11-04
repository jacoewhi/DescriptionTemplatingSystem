<?php

namespace App\Console\Commands\DTS;

use App\Console\Commands\DTS\Exceptions\CouldNotLogErrorException;
use App\Console\Commands\DTS\Exceptions\MissingEntryException;
use App\Console\Commands\DTS\Exceptions\MissingTemplateException;
use App\Console\Commands\DTS\Templates\Template;
use App\Console\Commands\DTS\ValueConversion\SpecValueConverter;
use App\Models\DoSupply\Product;
use App\Models\DoSupply\ProductKeyFeature;
use Illuminate\Console\Command;

class DescriptionTemplatingSystem extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'dts';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @throws CouldNotLogErrorException
	 */
	public function handle() {

		ini_set('memory_limit', '16000M');

//        $directoryName = "powerflex-70";
//        $templateFileName = "series_description_generation_config_PowerFlex70.csv";
//        $valueConversionFileName = "powerflex70-assignments.csv";
//        $databasePartNumberPrefix = "20A";
//        $prefixLength = 3;

//        $directoryName = "powerflex-700";
//        $templateFileName = "series_description_generation_config_PowerFlex700.csv";
//        $valueConversionFileName = "powerflex700-assignments.csv";
//        $databasePartNumberPrefix = "20B";
//        $prefixLength = 3;

        $directoryName = "powerflex-750";
//        $templateFileName = "series_description_generation_config_PowerFlex753.csv";
//        $databasePartNumberPrefix = "20F1";
        $templateFileName = "series_description_generation_config_PowerFlex755.csv";
        $databasePartNumberPrefix = "20G1";
//        $databasePartNumberPrefix = "21G1";
        $valueConversionFileName = "value_conversions_powerflex_750.csv";
        $prefixLength = 4;

//        $directoryName = "1336S";
//        $templateFileName = "series_description_generation_config_1336S.csv";
//        $valueConversionFileName = "1336S-assignments.csv";
//        $databasePartNumberPrefix = "1336S";
//        $prefixLength = 5;

//        $directoryName = "1336F";
//        $templateFileName = "series_description_generation_config_1336F.csv";
//        $valueConversionFileName = "1336F-assignments.csv";
//        $databasePartNumberPrefix = "1336F";
//        $prefixLength = 5;

//		$directoryName = "MPL";
//		$templateFileName = "series_description_generation_config_MPL.csv";
//		$valueConversionFileName = "MPL-assignments.csv";
//		$databasePartNumberPrefix = "MPL";
//		$prefixLength = 3;

		//receive and process csv into glossary, sentence templates, and spec templates
		error_log("starting command");
		$template = new Template("C://Users/DOUser/Documents/DTS/$directoryName/$templateFileName");
		$specValueConverter = new SpecValueConverter("C://Users/DOUser/Documents/DTS/$directoryName/$valueConversionFileName");
		$descriptionFile = fopen("C://Users/DOUser/Documents/DTS/$directoryName/results/" . date('Y-m-d') . "_" . $directoryName . "_descriptions.csv", 'w');
		fputcsv($descriptionFile, ["product id", "Part Number", "Description"]);
		$errorFile = fopen("C://Users/DOUser/Documents/DTS/$directoryName/results/" . date('Y-m-d') . "_" . $directoryName . "_errors.csv", 'w');
		fputcsv($errorFile, ["product id", "Part Number", "Description", "Error Message"]);
		$LQProductsFile = fopen("C://Users/DOUser/Documents/DTS/generated_low_quality_description_report.csv", "r");
		error_log("file open success");

		//remove headers
		fgetcsv($LQProductsFile);
		$line = fgetcsv($LQProductsFile);

		$count = 0;
		$multiSpecs = 0;
		$keyFeatureCount = 0;
		$productNotFound = 0;

		$products = Product::query()
            ->where("part_number", "LIKE", $databasePartNumberPrefix . "%")
            ->get([
            'id',
            'manufacturers_id',
            'series_id',
            'part_number',
            'description',
            'secondary_description'
        ]);
        error_log("products retrieved");
		foreach ($products as $product) {

//		while (!empty($line)) {
//			$product = null;
//			if (substr($line[1], 0, $prefixLength) == $databasePartNumberPrefix) {
//				$product = Product::query()
//					->where('part_number', '=', trim($line[1]))
//					->first([
//						'id',
//						'manufacturers_id',
//						'series_id',
//						'part_number',
//						'description',
//						'secondary_description'
//					]);
//			}
			if (!empty($product)) {
				srand($product->id);
				try {
					$description = "";
					$sentences = [];
					$template->getSpecTemplates()->resetUsedSpecs();
					$template->getSpecTemplates()->shuffleSpecs();
					$renderer = new Renderer($product->id, $product->part_number, $template, $specValueConverter, Renderer::DOSUPPLY);
					$description .= $renderer->composeIntro() . ". ";
					$rand = rand(0, 2);
					for ($i = 0; $i < 3; $i++) {
						if ($i != $rand) {
							$sentences[$i] = $renderer->composeSupporting() . ". ";
						} else {
							$sentences[$i] = $renderer->composeMulti() . ". ";
						}
					}
					try {
						array_push($sentences, $renderer->composeSupporting() . ". ");
					} catch (MissingEntryException | MissingTemplateException $exception) {
						//do nothing
					}
					shuffle($sentences);
					while (!empty($sentences)) {
						$description .= array_shift($sentences);
					}
					$description = preg_replace("/\.+/", ".", $description);
//                    $product->secondary_description = $product->description;
//					$product->description = $description;
//					$product->timestamps = false;
//					$product->save();
					fputcsv($descriptionFile, [$product->id, $product->part_number, $description]);
					error_log($description);
					try {
						ProductKeyFeature::where('product_id', '=', $product->id)->delete();
						for ($i = 0; $i < 10; $i++) {
							$keyfeature = new ProductKeyFeature(
								[
									'product_id' => $product->id,
									'display_order' => $i,
									'key_feature' => $renderer->generateKeyFeature()
								]
							);
							$keyfeature->save();
							$keyFeatureCount++;
						}
					} catch (MissingTemplateException | MissingEntryException $exception) {
						//do nothing
					}
					$count++;
					error_log($count);
					$multiSpecs++;
				} catch (MissingTemplateException | MissingEntryException $exception) {
					try {
						$description = "";
						$sentences = [];
						$template->getSpecTemplates()->resetUsedSpecs();
						$template->getSpecTemplates()->shuffleSpecs();
						$renderer = new Renderer($product->id, $product->part_number, $template, $specValueConverter, Renderer::DOSUPPLY);
						$description .= $renderer->composeIntro() . ". ";
						for ($i = 0; $i < 3; $i++) {
							$sentences[$i] = $renderer->composeSupporting() . ". ";
						}
						try {
							array_push($sentences, $renderer->composeSupporting() . ". ");
						} catch (MissingEntryException | MissingTemplateException $exception) {
							//do nothing
						}
						shuffle($sentences);
						while (!empty($sentences)) {
							$description .= array_shift($sentences);
						}
						$description = preg_replace("/\.+/", ".", $description);
//                        $product->secondary_description = $product->description;
//						$product->description = $description;
//						$product->timestamps = false;
//						$product->save();
						fputcsv($descriptionFile, [$product->id, $product->part_number, $description]);
						error_log($description);
						$count++;
						error_log($count);
					} catch (MissingTemplateException | MissingEntryException $exception) {
						shuffle($sentences);
						while (!empty($sentences)) {
							$description .= array_shift($sentences);
						}
						error_log($description);
						$logged = fputcsv($errorFile, [$product->id, $product->part_number, $description, $exception->getMessage()]);
						if (!$logged) {
							throw new CouldNotLogErrorException("could not log error in error csv file", 0, $exception);
						}
						$template->getSpecTemplates()->resetUsedSpecs();
						$template->getSpecTemplates()->shuffleSpecs();
						$renderer = new Renderer($product->id, $product->part_number, $template, $specValueConverter, Renderer::DOSUPPLY);
						try {
							ProductKeyFeature::where('product_id', '=', $product->id)->delete();
							for ($i = 0; $i < 10; $i++) {
								$keyfeature = new ProductKeyFeature(
									[
										'product_id' => $product->id,
										'display_order' => $i,
										'key_feature' => $renderer->generateKeyFeature()
									]
								);
								$keyfeature->save();
								$keyFeatureCount++;
							}
						} catch (MissingTemplateException | MissingEntryException $exception) {
							//do nothing
						}
						$count++;
						error_log($count);
					}
				}
			} else {
				error_log("could not find product: " . $line[1]);
				$productNotFound++;
			}
			$line = fgetcsv($LQProductsFile);
		}
		error_log("products processed: " . $count);
		error_log("descriptions with multi spec sentences: " . $multiSpecs);
		error_log("key features generated: " . $keyFeatureCount);
		error_log("products not processed: " . $productNotFound);
		error_log("products don't get processed if their part number does not fit the database pattern specified");
		error_log("or if the part number is not in the database");
		error_log("program finished - exiting");
	}
}
