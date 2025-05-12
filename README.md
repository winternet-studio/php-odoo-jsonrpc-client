# php-odoo-jsonrpc-client

A simple Odoo JSON-RPC client you can understand - and with examples. Written in PHP.

Let's just put the truth out there - Odoo's API documentation is terrible... if you can even find any! But with this library I'll try
to make it a little less terrible to work with its API.

It's currently a work in progress. Feel free to help by doing pull requests.


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
composer require winternet-studio/odoo-jsonrpc-client
```


## Usage

Connecting to Odoo:

```php
$baseUri = 'https://yourodooserver.com';
$database = 'databaseName';
$username = 'johndoe@sample.com';
$password = 'mypassword';
$odoo = new \winternet\odoo\JsonRpcClient($baseUri, $database, $username, $password);

echo $odoo->version('major');

$userID = $odoo->authenticate();
```

Or to use the higher level helper classes:

```php
$companyID = 5;
$odoo = new \winternet\odoo\helpers\Core($baseUri, $database, $username, $password, $companyID);
$account = $odoo->accounting->getAccount($accountID);

$filters = [];
$filters[] = ['company_id', '=', $companyID];
$filters[] = ['account_id', '=', 45805];
$filters[] = ['date', '>=', '2025-01-01'];
$filters[] = ['date', '<=', '2025-12-31'];
$order = 'account_id, date DESC';
$lines = $odoo->accounting->getMoveLines($filters, null, $order);
```

Continue with examples below and see the documentation for each method in the JsonRpcClient class file itself.


## Examples

These examples are all for the `JsonRpcClient` even though there is now also a `RestClient`.
Also, no examples are yet available for the higher level helper classes other than in Usage above. For now investigate the classes themselves.

A very useful way of figuring out the possible requests, methods, fields and possible values is to look at the requests the browser
makes when navigating the Odoo system. Open the developer tools and look at the Network requests.

Depending of Odoo version the fields might differ. These examples work for v14.


### Get all models

```php
$models = $odoo->searchRead('ir.model', ['fields' => ['id', 'model', 'name']]);
```

### Get all fields for a model

```php
$fields = $odoo->fieldsGet('account.move');
```

### Get records by ID

```php
$recordIDs = [74049, 74590];
$fields = ['name', 'create_date', 'amount_total_signed'];
$invoices = $odoo->read('account.move', $recordIDs, $fields);

$recordID = 75098;
$invoice = $odoo->read('account.move', $recordID, [], ['single' => true]);  // return a single record or null if it isn't found
$invoice = $odoo->read('account.move', $recordID, [], ['single' => 'require']);  // return a single record or throw exception if it isn't found
```

### Post a record that is currently a draft

Eg. post a payment (`account.payment`) or invoice (`account.move`).

```php
$recordIDs = [17113];
$odoo->actionPost('account.payment', $recordIDs);
```

### Get invoices

```php
$invoices = $odoo->searchRead('account.move', [
	'where' => [
		[
			'move_type',
			'=',
			'out_invoice',
		],
		[
			'partner_id',
			'=',
			(int) $partner_id,  //must be an integer!!
		],
	],
	'limit' => 3,
	'fields' => ['name', 'create_date', 'amount_total_signed'],
], [
	// 'expandFields' => ['invoice_line_ids' => ['model' => 'account.move.line']],  //enable this line to include invoice lines - see expandFields in execute() method in JsonRpcClient class file
]);
```

### Create invoice

This invoice example is originally a copy from the network request in the browser.

It is created as a draft and must be posted using the `actionPost()` method as in the example above.
Seems not possible to post it at the same time as creating it.

```php
$createdInvoice = $odoo->create('account.move', [
	'move_type' => 'out_invoice',
	'date' => '2023-10-31',
	// 'show_name_warning' => false,
	// 'posted_before' => false,
	// 'payment_state' => 'not_paid',
	// 'name' => false,
	'partner_id' => 6060,
	'partner_shipping_id' => 6060,  //to set shipping address (in UI they default it to same as customer)
	'ref' => '',
	'payment_reference' => '',
	// 'partner_bank_id' => 55,
	// 'invoice_vendor_bill_id' => false,
	'invoice_date' => '2023-10-31',
	'invoice_date_due' => '2023-11-07',
	'invoice_payment_term_id' => 26,  //ADJUST TO YOUR INSTANCE. Set to false for no payment term, eg. if setting a date instead.
	'journal_id' => 136,  //ADJUST TO YOUR INSTANCE
	'currency_id' => 3,  //ADJUST TO YOUR INSTANCE
	'invoice_line_ids' => [
		[
			0,
			'virtual_848',  // what is this?
			[
				// 'sequence' => 10,
				// 'product_id' => false,
				'name' => 'Line 1',  //invoice line description
				'account_id' => 5262,    //ADJUST TO YOUR INSTANCE
				// 'analytic_account_id' => false,
				// 'analytic_tag_ids' => [
				//     [
				//         6,
				//         false,
				//         [],
				//     ],
				// ],
				// 'asset_profile_id' => false,
				// 'asset_id' => false,
				'quantity' => 1,
				// 'product_uom_id' => false,
				'price_unit' => 55.00,
				// 'discount' => 0,
				'tax_ids' => [
					[
						6,
						false,
						[366],  //ADJUST TO YOUR INSTANCE. Empty array for no tax. Add entry with integer of tax_id to apply tax.
					],
				],
				// 'partner_id' => 6060,
				// 'amount_currency' => -55,
				// 'currency_id' => 3,
				// 'debit' => 0,  /// isn't this always automatically determined?
				// 'credit' => 614.98,  /// isn't this always automatically determined?
				// 'date_maturity' => false,
				// 'tax_tag_ids' => [
				//     [
				//         6,
				//         false,
				//         [],
				//     ],
				// ],
				// 'recompute_tax_line' => false,
				// 'display_type' => false,
				// 'is_rounding_line' => false,
				// 'exclude_from_invoice_tab' => false,
			],
		],
	],
	'narration' => 'This is the comments field',
	// // Journal Elements which are automatically created:
	// 'line_ids' => [
	//     [
	//         0,
	//         'virtual_945',
	//         [
	//             'analytic_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'account_id' => 5242,
	//             'sequence' => 10,
	//             'name' => '',
	//             'quantity' => 1,
	//             'price_unit' => -11255,
	//             'discount' => 0,
	//             'debit' => 125846.36,
	//             'credit' => 0,
	//             'amount_currency' => 11255,
	//             'date_maturity' => '2023-11-07',
	//             'currency_id' => 3,
	//             'partner_id' => 6060,
	//             'product_uom_id' => false,
	//             'product_id' => false,
	//             'tax_base_amount' => 0,
	//             'tax_exigible' => true,
	//             'tax_repartition_line_id' => false,
	//             'recompute_tax_line' => false,
	//             'display_type' => false,
	//             'is_rounding_line' => false,
	//             'exclude_from_invoice_tab' => true,
	//             'asset_profile_id' => false,
	//             'asset_id' => false,
	//             'analytic_account_id' => false,
	//         ],
	//     ],
	//     [
	//         0,
	//         '',
	//         [
	//             'analytic_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'account_id' => 5262,
	//             'sequence' => 10,
	//             'name' => 'Line 1',
	//             'quantity' => 1,
	//             'price_unit' => 55,
	//             'discount' => 0,
	//             'debit' => 0,
	//             'credit' => 614.98,
	//             'amount_currency' => -55,
	//             'date_maturity' => false,
	//             'currency_id' => 3,
	//             'partner_id' => 6060,
	//             'product_uom_id' => false,
	//             'product_id' => false,
	//             'tax_base_amount' => 0,
	//             'tax_exigible' => true,
	//             'tax_repartition_line_id' => false,
	//             'recompute_tax_line' => false,
	//             'display_type' => false,
	//             'is_rounding_line' => false,
	//             'exclude_from_invoice_tab' => false,
	//             'asset_profile_id' => false,
	//             'asset_id' => false,
	//             'analytic_account_id' => false,
	//         ],
	//     ],
	//     [
	//         0,
	//         'virtual_922',
	//         [
	//             'analytic_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'tax_tag_ids' => [
	//                 [
	//                     6,
	//                     false,
	//                     [],
	//                 ],
	//             ],
	//             'account_id' => 5255,
	//             'sequence' => 10,
	//             'name' => 'Line 2',
	//             'quantity' => 70,
	//             'price_unit' => 160,
	//             'discount' => 0,
	//             'debit' => 0,
	//             'credit' => 125231.38,
	//             'amount_currency' => -11200,
	//             'date_maturity' => '2023-11-07',
	//             'currency_id' => 3,
	//             'partner_id' => 6060,
	//             'product_uom_id' => false,
	//             'product_id' => false,
	//             'tax_base_amount' => 0,
	//             'tax_exigible' => true,
	//             'tax_repartition_line_id' => false,
	//             'recompute_tax_line' => false,
	//             'display_type' => false,
	//             'is_rounding_line' => false,
	//             'exclude_from_invoice_tab' => false,
	//             'asset_profile_id' => false,
	//             'asset_id' => false,
	//             'analytic_account_id' => false
	//         ],
	//     ],
	// ],
	// 'user_id' => 42,
	// 'invoice_user_id' => 42,
	// 'team_id' => 11,
	// 'invoice_origin' => false,
	// 'qr_code_method' => false,
	// 'invoice_incoterm_id' => false,
	// 'fiscal_position_id' => false,
	// 'invoice_cash_rounding_id' => false,
	// 'invoice_source_email' => false,
	// 'auto_post' => false,  //schedule the record to be automatically posted on the invoice date? Defaults to false
	// 'to_check' => false,
	// 'campaign_id' => false,
	// 'medium_id' => false,
	// 'source_id' => false,
	// 'message_follower_ids' => [],
	// 'activity_ids' => [],
	// 'message_ids' => [],
]);
```

### Create payment

```php
$paymentID = $odoo->create('account.payment', [
	// 'name' => false,
	'payment_type' => 'inbound',
	'partner_type' => 'customer',
	'partner_id' => 6197,
	// 'destination_account_id' => 560,
	// 'is_internal_transfer' => false,
	'company_id' => 4,
	'journal_id' => 22,
	'payment_method_id' => 3,
	// 'payment_token_id' => false,
	// 'partner_bank_id' => false,
	'amount' => 100,
	'currency_id' => 15,
	'date' => '2023-11-05',
	// 'ref' => false,
	// 'message_follower_ids' => [],
	// 'activity_ids' => [],
	// 'message_ids' => [],
]);
```

### Get currencies

Get currencies, with array index values being the currency code.

```php
$currencies = $odoo->searchRead('res.currency', [], ['indexBy' => 'name']);
```

### Update currencies with today's rates

Update rates with data from European Central Bank.

```php
$odoo->updateExchangeRates();
```
