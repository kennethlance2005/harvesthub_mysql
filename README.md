# harvesthub_mysql
harvesthub with mysql database

For an existing database, apply `migrations/20261007_resource_lifecycle_events.sql` and then `migrations/20261007_plot_request_timeline.sql` once. Fresh installations get both event tables and the request processing timestamp from `schema.sql`. Lifecycle events are recorded from the migrations onward; older item additions cannot be backfilled because the previous schema did not store their timestamp or creator.

For an existing database, also apply `migrations/20261007_add_login_attempt_lockout.sql` once. It adds the `FailedLoginAttempts` column required by login; fresh installations already include it in `schema.sql`.

## Email delivery and registration verification

Email verification codes for new registrations and password-reset links are sent through Gmail SMTP using PHPMailer. Install the dependency from this directory with `composer install`.

For local XAMPP, copy `secrets.local.example.php` to `secrets.local.php` and enter your Gmail address as both `smtp_username` and `smtp_from_email`, plus a Google App Password as `smtp_password`. Do not use your regular Google password or commit `secrets.local.php`. On a hosted server, configure the same values as `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, and `SMTP_SECURE` environment variables. Gmail uses `smtp.gmail.com`, port `587`, and `tls`.

For an existing database, apply `migrations/20261010_email_verification.sql` once. Fresh installations get the verification table and password-reset email throttle field from `schema.sql`. Codes expire after 10 minutes, allow five verification attempts, and can be resent once per minute. Password-reset messages are also limited to one per email per minute.

For existing databases missing the status fields from the signup request table, apply `migrations/20261011_signup_request_status_fields.sql` once. It ensures `RejectionReason` and the unique `StatusToken` field required by registration status links are present.
