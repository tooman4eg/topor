<?php

return [
	'bestmt' => [
		'env' => \Topor\Bestmt::ENV_STAGE
	],

	'mbank' => [
		'env' => \Topor\Mbank::ENV_DEV,
		'credentials' => ['+79012345678', 'password']
	],

	'mserver' => [
		'env' => \Topor\Mserver::ENV_STAGE,
		'credentials' => ['mbank_storefront', 'iYo7eeLe']
	],

	'services' => [

		'storage' => [
			//'type' => 'local',
			'dir' => __DIR__.'/vendor/nebo15/topor/services',
		],

		'transfer' => [
			'service_id' => 1330,
		],

		'to_card' => [
			'service_id' => 1310,
		],

		'to_bank' => [
			'personal_service_id' => 1331,
			'company_service_id' => 1350,
		]
	]
];
