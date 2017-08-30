<?php

namespace Zotlabs\Module;


class Apschema extends \Zotlabs\Web\Controller {

	function init() {

		$base = z_root();

		$arr = [
			'@context' => [
				'zot'              => z_root() . '/apschema#',
				'id'               => '@id',
				'type'             => '@type',
				'meData'           => 'zot:meData',
				'meDataType'       => 'zot:meDataType',
				'meEncoding'       => 'zot:meEncoding',
				'meAlgorithm'      => 'zot:meAlgorithm',
				'meCreator'        => 'zot:meCreator',
				'meSignatureValue' => 'zot:meSignatureValue',

				'magicEnv' => [
					'@id'   => 'zot:magicEnv',
					'@type' => '@id'
				]
			]
		];

		header('Content-Type: application/ld+json');
		echo json_encode($arr,JSON_UNESCAPED_SLASHES);
		killme();

	}




}