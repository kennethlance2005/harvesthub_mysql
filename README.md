# harvesthub_mysql
harvesthub with mysql database

For an existing database, apply `migrations/20261007_resource_lifecycle_events.sql` and then `migrations/20261007_plot_request_timeline.sql` once. Fresh installations get both event tables and the request processing timestamp from `schema.sql`. Lifecycle events are recorded from the migrations onward; older item additions cannot be backfilled because the previous schema did not store their timestamp or creator.

For an existing database, also apply `migrations/20261007_add_login_attempt_lockout.sql` once. It adds the `FailedLoginAttempts` column required by login; fresh installations already include it in `schema.sql`.
