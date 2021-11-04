<?php
require('/var/www/html/v2/src/init.php');

ini_set('max_execution_time', '1024');
ini_set('memory_limit', '1024M');

global $capsule;

$doSpecs =
	$capsule
		::table('technical_specs_do')
		->get();

$data = [];

foreach ($doSpecs as $spec) {

	$partNumber = $capsule::table('products_do')->where('id', '=', $spec->product_id)->first()->part_number;
	$asProductId = $capsule::table('products')->where('part_number', 'LIKE', $partNumber)->first()->id;

	array_push($data, ['id' => 0,
		'product_id' => $asProductId,
		'spec' => $spec->spec,
		'stat' => $spec->stat,
		'display_order' => $spec->display_order
	]);
}

foreach (array_chunk($data, 1000) as $d) {
	$capsule::table('technical_specs')->insertOrIgnore($d);
}