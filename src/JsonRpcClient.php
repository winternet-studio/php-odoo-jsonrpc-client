<?php
namespace winternet\odoo;

class JsonRpcClient {
	use \winternet\odoo\traits\CurrencyTrait;

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
			$httpMethod = 'POST';
			$endpoint = '/jsonrpc';
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
				$logPayload = $payload;
				if (isset($logPayload['json']['params']['args'][1]) && $logPayload['json']['params']['args'][1] == $this->username) {
					$logPayload['json']['params']['args'][1] = '...USERNAME...';
				}
				if (isset($logPayload['json']['params']['args'][2]) && $logPayload['json']['params']['args'][2] == $this->password) {
					$logPayload['json']['params']['args'][2] = '...PW...';
				}
				$this->debugLogging('REQUEST', [
					'url' => $httpMethod .' '. (string) $this->client->getConfig()['base_uri'] . $endpoint,
					'body' => $logPayload,
				]);
			}

			$response = $this->client->request($httpMethod, $endpoint, $payload);
		} catch (\GuzzleHttp\Exception $e) {
			$this->error('Guzzle HTTP request to Odoo failed: '. $e->getMessage(), ['GuzzleHttp\Exception' => $e]);
		}
		$this->lastResponse = $response;

		if ($this->isDebug) {
			$body = (string) $response->getBody();
			$bodyDecoded = json_decode($body);
			$this->debugLogging('RESPONSE', [
				'httpStatus' => $response->getStatusCode(),
				'headers' => array_map(function($item) {
					return implode(' ', $item);
				}, $response->getHeaders()),
				'body' => ($bodyDecoded !== null ? $bodyDecoded : $body),
			]);
		}

		if ($response->getStatusCode() == 200) {
			$responseData = $this->processResponse($response);
			if ($service === 'common' && $method === 'authenticate') {
				$this->uid = $responseData;
			}

			return $responseData;
		} else {
			$this->error($response);
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
			$this->error($message);
		}
		return $json->result;
	}

	public function error($message, $internalInfo = []) {
		$this->debugLogging($message, $internalInfo);
		throw new \Exception($message);
	}

	public function debugLogging($name, $data) {
		$logString = static::class .' debug: '. $name .':'. (is_string($data) ? ' '. $data : PHP_EOL . json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_SLASHES));
		$logFile = 'winternetOdooPhpJsonRpcClient.log';
		touch($logFile);
		if (is_writable($logFile)) {
			file_put_contents($logFile, date('Y-m-d H:i:sO') ."\t". $logString . PHP_EOL, FILE_APPEND);  // use custom log in current working folder if possible
		} else {
			error_log($logString);  // use PHP's error log
		}
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

	public function version($format = 'major') {
		$version = $this->postRequest('common', 'version', []);
		if ($format == 'major') {
			return (int) $version->server_version_info[0];
		} elseif ($format == 'full') {
			return $version->server_version;
		} else {
			return $version;  //return raw result
		}
	}

	/**
	 * @param array $options : Available options:
	 *   - `indexBy` : field name to index the returned array by
	 *   - `single` : set true to return a single record, or null if nothing found. Or set string 'require' to throw Exception if nothing found
	 */
	public function execute($model, $method, $args, $options = []) {
		$newArgs = [
			$this->database,
			$this->uid,
			$this->password,
			$model,
			$method,
		];
		array_push($newArgs, ...$args);

		$result = $this->postRequest('object', 'execute', $newArgs);

		if (!empty($options['single'])) {
			if (empty($result)) {
				if ($options['single'] === 'require') {
					$this->error('Single '. $model .' record not found.', $args);
				} else {
					return null;
				}
			} else {
				return $result[0];
			}
		}

		if (empty($options['indexBy'])) {
			return $result;
		} else {
			$new = [];
			foreach ($result as $row) {
				$new[ $row->{ $options['indexBy'] } ] = $row;
			}
			return $new;
		}
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
	 *
	 * @param array $options : Available options:
	 *   - `indexBy` : field name to index the returned array by
	 *   - `single` : set true to return a single record, or null if nothing found. Or set string 'require' to throw Exception if nothing found
	 */
	public function searchRead($model, $args = [], $options = []) {
		if (empty($args)) $args = [];

		return $this->execute($model, 'search_read', [
			(isset($args['domain']) ? $args['domain'] : $args['where']),  //make `where` an alias for `domain`
			$args['fields'],
			$args['offset'],
			$args['limit'],
			$args['order'],
		], $options);
		// Order of arguments for the different methods: https://www.cybrosys.com/odoo/odoo-books/odoo-15-development/ch14/json-rpc/
	}

	/**
	 * @param array|integer $IDs : array of record IDs to read or single ID (integer) to read
	 * @param array $options : Available options:
	 *   - `indexBy` : field name to index the returned array by
	 *   - `single` : set true to return a single record, or null if nothing found. Or set string 'require' to throw Exception if nothing found
	 */
	public function read($model, $IDs, $fields = [], $options = []) {
		if (!is_array($IDs)) $IDs = [$IDs];
		return $this->execute($model, 'read', [
			$IDs,
			$fields,
		], $options);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array $fields : associative array with fieldname/value pairs, eg. `['date' => '2023-11-03', 'partner_id' => 6060, ...]`
	 */
	public function create($model, $fields) {
		return $this->execute($model, 'create', [
			$fields,
		]);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array $fields : associative array with fieldname/value pairs, eg. `['date' => '2023-11-03', 'partner_id' => 6060, ...]`
	 */
	public function update($model, $recordID, $fields) {
		return $this->execute($model, 'write', [
			$recordID,
			$fields,
		]);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array|integer $IDs : array of record IDs to delete or single ID (integer) to delete
	 */
	public function delete($model, $IDs) {
		if (!is_array($IDs)) $IDs = [$IDs];
		return $this->execute($model, 'unlink', [
			$IDs,
		]);
	}

	// Can we use more of these methods? https://www.cybrosys.com/blog/orm-methods-in-odoo-15

	/**
	 * Post a record that is currently a draft
	 *
	 * @param string $model : name of model, eg. `account.move`
	 * @param array|integer $IDs : array of record IDs to post or single ID (integer) to post
	 * @throws Exception on failure, eg. if record(s) are already posted
	 * @return null
	 */
	public function actionPost($model, $IDs) {
		if (!is_array($IDs)) $IDs = [$IDs];
		return $this->execute($model, 'action_post', [
			$IDs,
		]);
	}

	public function fieldsGet($model) {
		return $this->execute($model, 'fields_get', []);
	}

	/**
	 * Change the active company
	 */
	public function changeActiveCompany($companyID, $userID = null) {
		$this->authenticate();

		if (!$userID) {
			$userID = $this->uid;
		}

		return $this->update('res.users', $userID, ['company_id' => $companyID]);
	}

}
