<?php
namespace winternet\odoo\helpers;

class Core {

	public $client;
	public $modules = [];

	function __construct(
		public string $url, 
		public string $database, 
		public string $user, 
		public string $password,
		public string $companyID,
	) {
		$this->client = new \winternet\odoo\JsonRpcClient($this->url, $this->database, $this->user, $this->password);
	}

	public function majorVersion() {
		return $this->client->version('major');
	}

	public function getModels($options = []) {
		if (empty($options['allFields'])) {
			return $this->client->searchRead('ir.model', ['fields' => ['id', 'model', 'name']]);
		} else {
			return $this->client->searchRead('ir.model');
		}
	}

	public function __get($property) {
		if ($property == 'accounting') {
			if (empty($modules[$property])) {
				$modules[$property] = new Accounting($this);
			}
			return $modules[$property];
		} else {
			return null;
		}
	}

}
