=== ART Forms ===
Contributors: artbashlykov
Tags: forms, quiz, survey, leads, email
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.34
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
* CRM-style submissions inbox per form (stages, table/board, lead card, comments)
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

= How do I move a form to another site? =

On the Forms list, export a JSON pack, then import it on the destination site. Submissions are not copied. After import, update `data-art-form-id` (and the honeypot field name) in the page markup to the new form ID.

== Changelog ==

= 1.1.34 =
* Settings: hide the empty Integrations tab until integrations are ready.

= 1.1.33 =
* GitHub update checker: notifications on the Plugins screen without ART Master Install. Public repository, no GitHub token required.

= 1.1.32 =
* Export and import forms between sites (JSON pack: schema, settings, CRM stages; submissions are not copied).
* Form field type “name”: shown on the CRM card and in the table; search includes name.
* Consent: full agreement text in CRM, emails, and the form editor list.
* Admin submission email: HTML “Open in CRM” link after the page URL; test mail links to the latest real lead card.
* Skip the CRM “new lead” email when the same recipients already get the form’s admin email.
* Outgoing mail uses the site title as the From name (not “WordPress”).

= 1.1.31 =
* CRM “Hide columns”: include all table columns (star, priority, tags, ID, date, stage, and form fields).

= 1.1.30 =
* Fix duplicate Forms list when parent and submenu share the same admin slug.
* Kanban: load cards per stage column (no silent global 200-card cut).
* CRM filters: priority and tag; archive stage excluded from “All”.
* Lead card activity history (stage, fields, star, priority/tags).
* CSV export includes stage, star, priority, tags and respects current filters.
* CRM managers, notifications badge/email, field editing, board card reorder.

= 1.1.3 =
* CRM table: drag-and-drop column reordering (order saved per user).

= 1.1.2 =
* CRM table: sort by columns (including form fields), resize columns, overflow tooltip on hover.

= 1.1.1 =
* CRM Answers: hide “Visible columns” panel by default; use full-width layout for the submissions table.

= 1.1.0 =
* CRM mode for Answers: form hub, per-form stages, table and kanban views, lead modal, comments, contacts tab, bulk actions, column visibility, starred leads.

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
