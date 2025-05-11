<?php
namespace winternet\odoo;

/**
 * Odoo REST client
 *
 * But it's very similar to JSON-RPC as it uses it as its payload.
 *
 * This is originally a copy of the JsonRpcClient class. Compare these two files when developing to keep them "synchronized" where applicable (until we make a base class for common functionality).
 */
class RestClient {

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

		// Automatically attempt to authenticate
		if ($this->username && $this->password) {
			$this->authenticate();
		}
	}

	public function postRequest(string $endpoint, array $arguments) {
		try {
			$httpMethod = 'POST';
			$payload = [
				'json' => [
					'jsonrpc' => '2.0',
					'method' => 'call',
					'params' => $arguments,
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
			$this->error('Guzzle REST HTTP request to Odoo failed: '. $e->getMessage(), ['GuzzleHttp\Exception' => $e]);
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
		$logFile = 'winternetOdooPhpRestClient.log';
		touch($logFile);
		if (is_writable($logFile)) {
			file_put_contents($logFile, date('Y-m-d H:i:sO') ."\t". $logString . PHP_EOL, FILE_APPEND);  // use custom log in current working folder if possible
		} else {
			error_log($logString);  // use PHP's error log
		}
	}

	public function authenticate() {
		return $this->postRequest('/web/dataset/search_read',
			[
				'db' => $this->database,
				'login' => $this->username,
				'password' => $this->password,
				'args' => [],
			]
		);
	}

	public function version($format = 'major') {
		$version = $this->postRequest('/web/webclient/version_info', []);
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
	 *   - `expandFields` : Expand a field with an array of record IDs into a new property called `_expanded`. Eg. `['invoice_line_ids' => ['model' => 'account.move.line']]´
	 */
	public function execute($endpoint, $args, $options = []) {

		$result = $this->postRequest($endpoint, $args);

		if (!empty($options['expandFields'])) {
			foreach ($result as &$row) {
				foreach ($options['expandFields'] as $fieldToExpand => $expandParams) {
					if (is_array($row->$fieldToExpand) && !empty($row->$fieldToExpand)) {  //look for an array of IDs
						if (!property_exists($row, '_expanded')) {
							$row->_expanded = (object) [];
						}
						$row->_expanded->$fieldToExpand = $this->read($expandParams['model'], $row->$fieldToExpand);
					}
				}
			}
		}

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
	 *  - `sort` : Eg. `'name'` or `'name DESC'`
	 *  - `context` : Eg. `['lang' => 'nb_NO', 'tz' => 'Europe/Oslo']`
	 *
	 * @param array $options : Available options:
	 *   - `indexBy` : field name to index the returned array by
	 *   - `single` : set true to return a single record, or null if nothing found. Or set string 'require' to throw Exception if nothing found
	 *   - `expandFields` : Expand a field with an array of record IDs into a new property called `_expanded`. Eg. `['invoice_line_ids' => ['model' => 'account.move.line']]´
	 */
	public function searchRead($model, $args = [], $options = []) {
		if (empty($args)) $args = [];

		$args['model'] = $model;
		if (!isset($args['domain']) && isset($args['where'])) {  //make `where` an alias for `domain`
			$args['domain'] = $args['where'];
		}

		return $this->execute('/web/dataset/search_read', $args, $options);
	}

	/**
	 * @param array|integer $IDs : array of record IDs to read or single ID (integer) to read
	 * @param array $fields : if set, the result will only include these fields
	 * @param array $options : Available options:
	 *   - `kwArgs` : array/object according to Odoo, eg. `['context' => ['lang' => 'nb_NO', 'tz' => 'Europe/Oslo']]`
	 *   - `indexBy` : field name to index the returned array by
	 *   - `single` : set true to return a single record, or null if nothing found. Or set string 'require' to throw Exception if nothing found
	 *   - `expandFields` : Expand a field with an array of record IDs into a new property called `_expanded`. Eg. `['invoice_line_ids' => ['model' => 'account.move.line']]´
	 */
	public function read($model, $IDs, $fields = [], $options = []) {
		if (!is_array($IDs)) $IDs = [$IDs];
		if (!is_array($fields)) $fields = [];
		return $this->execute('/web/dataset/call_kw/'. $model .'/read', [
			'method' => 'read',
			'model' => $model,
			'args' => [
				$IDs,
				$fields,
			],
			'kwargs' => (!empty($options['kwArgs']) ? $options['kwArgs'] : []),
		], $options);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array $fields : associative array with fieldname/value pairs, eg. `['date' => '2023-11-03', 'partner_id' => 6060, ...]`
	 * @param array $options : Available options:
	 *   - `kwArgs` : array/object according to Odoo, eg. `['context' => ['lang' => 'nb_NO', 'tz' => 'Europe/Oslo']]`
	 */
	public function create($model, $fields, $options = []) {
		return $this->execute('/web/dataset/call_kw/'. $model .'/create', [
			'method' => 'create',
			'model' => $model,
			'args' => [
				$fields,
			],
			'kwargs' => (!empty($options['kwArgs']) ? $options['kwArgs'] : []),
		], $options);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array $fields : associative array with fieldname/value pairs, eg. `['date' => '2023-11-03', 'partner_id' => 6060, ...]`
	 * @param array $options : Available options:
	 *   - `kwArgs` : array/object according to Odoo, eg. `['context' => ['lang' => 'nb_NO', 'tz' => 'Europe/Oslo']]`
	 */
	public function update($model, $recordID, $fields, $options = []) {
		return $this->execute('/web/dataset/call_kw/'. $model .'/write', [
			'method' => 'write',
			'model' => $model,
			'args' => [
				[$recordID],
				$fields,
			],
			'kwargs' => (!empty($options['kwArgs']) ? $options['kwArgs'] : []),
		], $options);
	}

	/**
	 * @param string $model : name of model, eg. `account.move`
	 * @param array|integer $IDs : array of record IDs to delete or single ID (integer) to delete
	 * @param array $options : Available options:
	 *   - `kwArgs` : array/object according to Odoo, eg. `['context' => ['lang' => 'nb_NO', 'tz' => 'Europe/Oslo']]`
	 */
	public function delete($model, $IDs, $options = []) {
		if (!is_array($IDs)) $IDs = [$IDs];
		return $this->execute('/web/dataset/call_kw/'. $model .'/unlink', [
			'method' => 'unlink',
			'model' => $model,
			'args' => [
				$IDs,
			],
			'kwargs' => (!empty($options['kwArgs']) ? $options['kwArgs'] : []),
		], $options);
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
