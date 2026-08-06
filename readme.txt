=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: activities, registration, enrollment, check-in, forms
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
* Public registration form with shortcode `[convoca_form_inscripcion id="X"]`
* Activities page shortcode `[convoca_inscripcion_page]`
* Booking panel shortcode `[convoca_panel_reservas]`
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

== Screenshots ==

1. Activities list
2. Activity creation form
3. Public registration form (shortcode)
4. QR check-in screen
5. Registrations statistics

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Enroll requires Convoca Core to be active.

= Can I limit capacity? =

Yes. Each activity has a capacity setting. When full, new registrations go to the wait list automatically.

= Does Google Calendar sync require a Google account? =

Yes. Google Calendar/Photos sync uses OAuth 2.0 and requires a Google Cloud project with the appropriate APIs enabled.

== Upgrade Notice ==

= 2.6.1 =
* Compatibility and stability improvements. Recommended update.
