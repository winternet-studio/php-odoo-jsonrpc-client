<?php
namespace winternet\odoo;

class JsonRpcClient {
	public $database;
	public $username;
	public $password;
	public $uid;

	private \GuzzleHttp\Client $client;
	private $lastResponse = null;

	public $isDebug = false;

	public function __construct(string $baseUri, $database, $username, $password) {
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;

		$this->client = new \GuzzleHttp\Client([
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'base_uri' => $baseUri,
		]);
	}

	public function postRequest(string $service, string $method, array $arguments) {
		try {
			$method = 'POST';
			$endpoint = 'jsonrpc';
			$payload = [
				'json' => [
					'jsonrpc' => '2.0',
					'method' => 'call',
					'params' => [
						'service' => $service,
						'method' => $method,
						'args' => $arguments,
					],
					'id' => rand(0, 1000000000),
				],
			];
			if ($this->isDebug) {
				$this->debugLogging('HTTP Request', [
					'url' => $method .' '. $this->client->getConfig()->get('base_uri') . $endpoint,
					'body' => $payload,
				]);
			}
			$response = $this->client->request($method, $endpoint, $payload);
		} catch (\GuzzleHttp\Exception $e) {
			throw new \Exception($e);
		}
		$this->lastResponse = $response;
		if ($this->isDebug) {
			$this->debugLogging('HTTP Response', [
				'httpStatus' => $response->getStatusCode(),
				'headers' => $response->getHeaders(),
				'body' => (string) $response->getBody(),
			]);
		}

		if ($response->getStatusCode() == 200) {
			$responseData = $this->processResponse($response);
			if ($service === 'common' && $method === 'authenticate') {
				$this->uid = $responseData;
			}

			return $responseData;
		} else {
			throw new \Exception($response);
		}
	}

	public function lastResponse() {
		return $this->lastResponse;
	}

	private function processResponse($response) {
		$json = json_decode($response->getBody());
		if (isset($json->error)) {
			$message = 'Odoo Exception';
			if (isset($json->error->message)) {
				$message = $json->error->message;
			}
			if (isset($json->error->data) && isset($json->error->data->message)) {
				$message .= ': '. $json->error->data->message;
			}
			// $response
			// $json->error->code
			throw new \Exception($message);
		}
		return $json->result;
	}

	public function debugLogging($name, $data) {
		error_log('Odoo debug: '. $name .':'. (is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
	}

	public function authenticate() {
		return $this->postRequest('common', 'authenticate',
			[
				$this->database,
				$this->username,
				$this->password,
				['empty' => 'false'],
			]
		);
	}

	public function version() {
		return $this->postRequest('common', 'version', []);
	}

	public function execute($model, $method, $args) {
		$newArgs = [
			$this->database,
			$this->uid,
			$this->password,
			$model,
			$method,
		];
		array_push($newArgs, ...$args);
		return $this->postRequest('object', 'execute', $newArgs);
	}

	/**
	 * @param array $args : Available keys:
	 *  - `where` : Filter/conditions (called "domain" in Odoo terms). Eg.: `[
	 *  	[
	 *  		'move_type',
	 *  		'=',    // docs: https://stackoverflow.com/questions/29442993/which-are-the-available-domain-operators-in-openerp-odoo
	 *  		'out_invoice',
	 *  	],
	 *  ]`
	 *  - `fields` : array of fields to return, eg. `['date', 'sequence_number']`
	 *  - `offset` : numeric, eg. `0`
	 *  - `limit` : numeric, eg. `20`
	 *  - `order` : Eg. `'name'` or `'name DESC'`
	 */
	public function searchRead($model, $args) {
		return $this->execute($model, 'search_read', [
			(isset($args['domain']) ? $args['domain'] : $args['where']),  //make `where` an alias for `domain`
			$args['fields'],
			$args['offset'],
			$args['limit'],
			$args['order'],
		]);
		// Order of arguments for the different methods: https://www.cybrosys.com/odoo/odoo-books/odoo-15-development/ch14/json-rpc/
	}

	/**
	 * @param array $IDs : record IDs to read
	 */
	public function read($model, $IDs, $fields = []) {
		return $this->execute($model, 'read', [
			$IDs,
			$fields,
		]);
	}

	/**
	 * @param array $fields : associative array with fieldname/value pairs, eg. `['date' => '2023-11-03', 'partner_id' => 6060, ...]`
	 */
	public function create($model, $fields) {
		return $this->execute($model, 'create', [
			$fields,
		]);
	}

	/**
	 * @param array $fields : array of fields to return eg. `['date', 'partner_id']`
	 */
	public function update($model, $recordID, $fields) {
		return $this->execute($model, 'write', [
			$recordID,
			$fields,
		]);
	}

	/**
	 * @param array $IDs : record IDs to delete
	 */
	public function delete($model, $IDs) {
		return $this->execute($model, 'unlink', [
			$IDs,
		]);
	}

	// Can we use more of these methods? https://www.cybrosys.com/blog/orm-methods-in-odoo-15

}
