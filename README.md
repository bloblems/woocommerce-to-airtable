# WooCommerce To Airtable

WooCommerce To Airtable is a WordPress plugin that synchronizes WooCommerce order processing with Airtable. This plugin sends new processing orders and their updates to Airtable, maintaining a seamless order tracking system. It populates corresponding columns in Airtable, allowing a convenient overview of order details. A meta box is added to the WooCommerce order dashboard indicating the status of data transmission to Airtable, keeping the WooCommerce admin aware of the synchronization status.

## Features
- Synchronize WooCommerce orders with Airtable
- Supports both the creation of new orders and the update of existing orders in Airtable
- Reflects order details in corresponding Airtable columns
- Shows the status of order transmission to Airtable through a meta box in WooCommerce order dashboard
- Error handling for unsuccessful data transmission
- Added Resend to Airtable Action: A new action button on the admin order page that allows the order data to be manually resent to Airtable.
- Enhanced Data Formatting: Send SKU instead of the product name, and the order quantity shows the SKU and quantity in brackets.
- Capitalization of Names: The customer's first and last names are now being capitalized before being sent to Airtable.
- Unique SKU Filtering: Send specific product SKUs to a seperate table

## Installation
1. Clone this repository to your local machine or download the zip file.
2. If downloaded as a zip, unzip the file.
3. Place the `woocommerce-to-airtable` folder in your `wp-content/plugins/` directory.
4. Activate WooCommerce To Airtable from the plugins page in your WordPress admin panel.
5. Make sure to replace the `$token`, `$baseId`, and `$tableName` variables in the `send_order_to_airtable` function with your actual Airtable API token, base ID, and table name.

## Usage
Once the plugin is activated, it automatically sends order data to Airtable when an order changes to processing status. It also updates Airtable if the order status changes. The WooCommerce order dashboard will contain a meta box indicating the status of data transmission.

## Support
For support, please create an issue on this GitHub repository.

## Author

- [Byron Jacobs](https://byronjacobs.co.za) - hello@byronjacobs.co.za

## License
This project is licensed under the terms of the GPLv2 or later license.
