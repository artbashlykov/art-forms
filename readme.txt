=== ART Forms ===
Contributors: artbashlykov
Tags: forms, quiz, survey, leads, email
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.38
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build form logic (quizzes, surveys), export code for design, collect submissions, and send them by email.

== Description ==

ART Forms helps you define the logic of a WordPress form (linear steps and fields), export a code skeleton for ChatGPT-based design, validate the designed markup against the form contract, and collect submissions with email delivery.

Features:

* Linear multi-step form schema in the admin
* Copy form code and ChatGPT prompt
* Admin code checker (no storage)
* Frontend runtime that captures `data-art-form-id` forms
* Submissions table with UTM and contact fields
* After-submit actions: admin email, client email, delayed redirect
* Email templates with placeholders
* Delivery log, CSV export, form duplication
* Consent field type for privacy agreement

== Installation ==

1. Upload the `art-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open ART Forms in the admin menu and create a form.

== Frequently Asked Questions ==

= Does ART Forms render the designed form by itself? =

No. You paste the designed HTML/CSS/JS on a page. ART Forms provides the schema, export, checker, and submission backend.

= How does the frontend send data? =

Forms with `data-art-form-id` are handled by the ART Forms runtime and posted to the REST endpoint.

== Changelog ==

= 1.0.38 =
* Fix $wpdb->prepare() argument unpacking for dynamic list queries.

= 1.0.37 =
* Harden submit endpoint: length limits, choice-field whitelist, payload size cap, email header injection guard.

= 1.0.36 =
* Plugin Check: remove discouraged load_plugin_textdomain(); harden custom-table SQL with esc_sql().

= 1.0.35 =
* Elementor-style after-submit actions (admin email, client email, redirect with delay).
* Submissions list: sortable columns, Russian date format, status badges.
* Settings page tabs, admin menu highlight on form edit.
* Form builder UX: schema stats badge, collapsible panels.

= 1.0.1 =
* Transliteration of Russian labels into field keys.
* Russian UI labels in the form editor.
* Unsaved changes warning on the form edit screen.

= 1.0.0 =
* Initial release.
