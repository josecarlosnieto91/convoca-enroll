=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: activities, registration, enrollment, QR, check-in, forms, associations, NGOs
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.6.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Activity and registration management with forms, QR, Google Calendar, and check-in.

== Description ==

Manage the complete activity lifecycle: creation, registrations with capacity control, wait list, QR check-in, evaluations, automatic reminders, and synchronization with Google Calendar and Google Photos.

Free features:
* Activity creation and management with slots and pricing
* Public registration form with shortcode
* Capacity control and automatic wait list
* QR check-in for attendees
* CSV export of registrants
* CRM with registration statistics
* REST API for integrations

PRO features (license required):
* PWA check-in (progressive web app)
* Automatic post-event PDF reports
* Automatic evaluations
* Registration webhooks

= External services =

This plugin can connect to external Google Calendar and Google Photos services via OAuth 2.0, under explicit administrator authorization. It may also contact getconvoca.app to validate PRO licenses, only when a license key is entered.

== Installation ==

1. Make sure Convoca Core is active
2. Upload the convoca-enroll folder to /wp-content/plugins/
3. Activate the plugin from the Plugins menu

== Changelog ==

= 2.6.1 =
* Improvement: Unit tests — fixed bootstrap, 42 tests, 91 assertions
* Fix: Registration engine with improved DNI/NIE validation
