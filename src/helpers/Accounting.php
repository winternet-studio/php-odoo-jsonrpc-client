<?php
namespace winternet\odoo\helpers;

class Accounting {

	protected $core;

	public function __construct(Core $core) {
		$this->core = $core;
	}

	public function getAccount($account) {
		return $this->core->client->searchRead('account.account', [
			'where' => [
				['code', '=', $account],
				['company_id', '=', (int) $this->core->companyID],
			],
		]);
	}

	public function getInvoices($where, $fields = null, $order = null, $limit = null, $offset = null) {
		$where[] = ['move_type', '=', 'out_invoice'];
		$where[] = ['company_id', '=', (int) $this->core->companyID];
		return $this->core->client->searchRead('account.move', [
			'where' => $where,
			'fields' => $fields,
			'offset' => $offset,
			'limit' => $limit,
			'order' => $order,
		]);

		return $this->core->client->searchRead('account.account', [
			'where' => [
				['code', '=', $account],
				['company_id', '=', (int) $this->core->companyID],
			],
		]);
	}

	/**
	 * @param array $where : See https://github.com/winternet-studio/php-odoo-jsonrpc-client
	 * @param array $fields : See https://github.com/winternet-studio/php-odoo-jsonrpc-client
	 * @param string $order : Eg. `account_id, date DESC`
	 * @param integer $limit : Limit to this number of records
	 * @param integer $offset
	 */
	public function getMoveLines($where, $fields = null, $order = null, $limit = null, $offset = null) {
		return $this->core->client->searchRead('account.move.line', [
			'where' => $where,
			'fields' => $fields,
			'offset' => $offset,
			'limit' => $limit,
			'order' => $order,
		]);
	}

}
