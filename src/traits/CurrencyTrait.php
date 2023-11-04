<?php
namespace winternet\odoo\traits;

trait CurrencyTrait {

	/**
	 * Update rates with data from European Central Bank
	 */
	public function updateExchangeRates() {
		// Possible alternatives: https://github.com/yelizariev/addons-yelizariev/blob/8.0/currency_rate_update/currency_rate_update.py
		$sources = [
			'ecb' => [
				'url' => 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
				'base_currency' => 'EUR',
			],
		];

		$effSource = $sources['ecb'];

		$updatedCurrencies = [];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $effSource['url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		if (!ini_get('safe_mode') && !ini_get('open_basedir')) {  //CURLOPT_FOLLOWLOCATION is not allowed in safe mode and when open basedir is set
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  //automatically follow redirects
		}

		$xml = curl_exec($ch);

		if ($xml) {
			$data = json_decode(json_encode(simplexml_load_string($xml)), true);

			if ($data) {
				$currRates = $this->searchRead('res.currency');
				if ($currRates) {

					$ratesDate = $data['Cube']['Cube']['@attributes']['time'];
					if (!$ratesDate) {
						throw new \Exception('Did not found date of the retrieved exchange rates.');
					}

					$newRates = [$effSource['base_currency'] => '1'];
					foreach ($data['Cube']['Cube']['Cube'] as $rate) {
						$newRates[$rate['@attributes']['currency']] = $rate['@attributes']['rate'];
					}

					foreach ($currRates as $currRate) {
						if ($newRates[$currRate->name]) {  //if we have a new exchange rate for this currency...
							if (!$currRate->date || strtotime($currRate->date) < strtotime($ratesDate) ) {
								// Update rate when we have a newer one
								$fields = [
									'currency_id' => $currRate->id,
									'name' => $ratesDate .' 00:00:00',
									'rate' => $newRates[$currRate->name],
								];
								$result = $this->create('res.currency.rate', $fields);

								$updatedCurrencies[$currRate->name] = $newRates[$currRate->name];
							} else {
								// already up-to-date
							}
						}
					}
				}
			} else {
				throw new \Exception('Failed parsing the XML with exchange rates.');
			}
		} else {
			throw new \Exception('Response with exchange rates was empty.');
		}

		return $updatedCurrencies;
	}

}
