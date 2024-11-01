=== Deliver via Shipos for WooCommerce ===
Contributors: amitrotem , wupendra, sarikk
Tags: tapuz, shipment, cargo, HFD , Chita , delivery system, matat delivery, ecommerce delivery system, ecommerce shipment, ecommerce shipping
Requires at least: 5.8.0
Tested up to: 6.5.2
Stable tag: 2.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ShipOS - Auto Sync your WooCommerce store orders to all delivery companies and Automate your shipping

Plugin options:
1. Regular delivery (door to door)
2. Reverse delivery, from the customer back to the store
3. Double shipping
4. Create Bulk shipment
5. Collection from pickup points - selection from a map (google maps support)
6. Collection from pickup points - selection from a list
7. Printing shipping labels
8. Receive delivery status
9. Canceling delivery


How open account? [Shipos Delivery](https://app.shipos.co.il/register)
[Plugin installation guide](https://bit.ly/3V41yuc)

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/shipos-delivery` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Settings->Shipos Delivery screen to configure the plugin

How to open account? [Shipos Delivery](https://app.shipos.co.il/register)
[Plugin installation guide](https://bit.ly/3V41yuc)


== Description ==
ShipOS - Auto Sync your WooCommerce store orders to all delivery companies and Automate your shipping

Plugin options:
1. Regular delivery (door to door)
2. Reverse delivery, from the customer back to the store
3. Double shipping
4. Create Bulk shipment
5. Collection from pickup points - selection from a map (google maps support)
6. Collection from pickup points - selection from a list
7. Printing shipping labels
8. Receive delivery status
9. Canceling delivery

How open account? [Shipos Delivery](https://app.shipos.co.il/register)
[Plugin installation guide](https://bit.ly/3V41yuc)

Shipping companies are supported:
* Baldar
* Run
* Chita delivery
* Hfd
* YDM
* Cargo
* Rimon delivery
* Negev group
* Tamnon
* Ratz plus
* L.a delivery
* Shir delivery
* Rom express
* Done
* Isgav
* Tapuz
* a.s delivery
* fix delivery
* focus logistics
* LAN deliveries
* Gal delivery
* GetPackage

Didn't find your courier company in the list? Send us a message and we will add your courier company

== Frequently Asked Questions ==

== Screenshots ==


== Changelog ==

= 2.1.1 =
* Add some hooks and filters
* Change button label to "Create shipping" if there is only one license

= 2.1.0 =
* Added support for GetPackage
* UI updates and bug fixes
* Option added to show free shipping by price before or after discount

= 2.0.12 =
* Automatically update missing shipping meta for orders

= 2.0.11 =
* Fix issue where shipping label was not generated in some cases

= 2.0.10 =
* Fix issue where shipping label was not generated in some cases

= 2.0.9 =
* Add Woocommerce HPOS support
* Add shipos button in title bar in admin
* Show company through which shipment was created
* Support for multiple license

= 2.0.8 =
* Send shipping lines when creating shipment

= 2.0.7 =
* Fix bug where license was not activated when automatically updated

= 2.0.6 =
* Open bulk shipment option in new tab
* Add REST route for adding license key automatically

= 2.0.5 =
* [feat] Allow Shop Manager to Access the Plugin Menu

= 2.0.4 =
* Add support for ignoring coupon discounts for conditional shipping price
* Add support for shipping phone number

= 2.0.3 =
* [bug] Handle multiple shipping point inputs issue caused by other plugins

= 2.0.2 =
* [bug] Fixed a bug where billing phone number was not sent

= 2.0.1 =
* Send Variation SKU data

= 2.0.0 =
* Add new menu item under Woocommerce "Ship OS Shipping" - Users can now create and manage their shipments from this menu item
* Implement usage of multiple licenses
* Edit order data before creating shipment
* Flag orders and shipments
* Advanced filtering options for orders and shipments
* Add notes to orders and shipments
* Create manual shipping

= 1.1.5 =
* Add support for orders created with WooCommerce's 'High-performance order storage' feature

= 1.1.4 =
* [bug] After deactivate and activate the plugin change status to test mode

= 1.1.3 =
* Remove unneeded code and features
* UI updates
* Bug fix: Fixed certain elements being hidden when cancelling shipment
* Added support for custom shipping phone number
* Code Refactor

= 1.1.2 =
* Product variations synced from Woocommerce to Shipos
* Feature: Add option for choosing whether to show order notes in label or not
* Bug fix: Fixed an issue where Open Shipment button would not be re-enabled after an error

= 1.1.1 =
* prevent multiple button clicks while shipment request is being executed.

= 1.1.0 =
* Add support for generating shipment labels in bulk
* Display product SKUs in ShipOs generated shipment labels

= 1.0.13 =
* Add shipment type, horizontal rule and remove repeated title
* Add custom order comment field parameter

= 1.0.12 =
* Update order item data sent to Shipos API when creating shipping
** Send image and gallery urls to api when creating shipping
** Send only required data of order items to the api

= 1.0.11 =
* Testmode bug fixed
* While pickup points are disabled, extra fields are hidden
* Error handling added
* unwanted files removed

= 1.0.10 =
* Testmode - disabled by default

= 1.0.9 =
* Added option for overriding license url when needed
* Fixed bug in bulk shipping where post meta was updated even when the shipping was not successfully created
* Fixed bug in bulk shipping where error messages were not displayed properly in admin notices


= 1.0.8 =
* Fixed bug related to shipping method id

= 1.0.7 =
* Plugin setting page Improvement
* Css update

= 1.0.6 =
* Shipping method - fixed issue related to collection point discount

= 1.0.5 =
* Improved settings page
* Shipping method - add option to discount for collection points above an order amount
* Added pickup point to client email
* Fix auto create shipping after order change status

= 1.0.4 =
* Translations to hebrew added
* Fixed bug few minor bugs

= 1.0.3 =
* First Public version.
