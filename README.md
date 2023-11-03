# php-odoo-jsonrpc-client

A simple Odoo JSON-RPC client you can understand - and with examples. Written in PHP.

Let's just put the truth out there - Odoo's API documentation is terrible... if you can even find any! But with this library I'll try
to make it a little less terrible to work with its API.

It's currently a work in progress. Feel free to help by doing pull requests.


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require winternet-studio/odoo-jsonrpc-client
```

or add

```
"winternet-studio/odoo-jsonrpc-client": "^1.0"
```

to the require section of your `composer.json` file.


## Usage

Connecting to Odoo:

```php
$baseUri = 'https://yourodooserver.com';
$database = 'databaseName';
$username = 'johndoe@sample.com';
$password = 'mypassword';
$client = new \winternet\odoo\JsonRpcClient($baseUri, $database, $username, $password);

echo $client->version('major');

$userID = $client->authenticate();
```

Continue with examples below.


## Examples

A very useful way of figuring out the possible requests and fields is to look at the requests the browser
makes when navigating the Odoo system. Open the developer tools and look at the Network requests.

### Get records by ID

```php
$recordIDs = [74049];
$fields = ['name', 'create_date', 'amount_total_signed'];
$invoices = $client->read('account.move', $recordIDs, $fields);
```

### Get invoices

```php
$invoices = $client->searchRead('account.move', [
	'where' => [
		[
			'move_type',
			'=',
			'out_invoice',
		],
	],
	'limit' => 3,
	'fields' => ['name', 'create_date', 'amount_total_signed'],
]);
```

### Create invoice

Depending of Odoo version the fields might differ. This example works for v14.

This invoice example is originally a copy from the network request in the browser.

```php
$createdInvoice = $client->create('account.move', [
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
    'invoice_payment_term_id' => 26,  //ADJUST TO YOUR INSTANCE
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
    'auto_post' => false,  //don't know if this is needed - or if it defaults to true.....?!
    // 'to_check' => false,
    // 'campaign_id' => false,
    // 'medium_id' => false,
    // 'source_id' => false,
    // 'message_follower_ids' => [],
    // 'activity_ids' => [],
    // 'message_ids' => [],
]);
```
