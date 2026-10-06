# harvesthub_mysql
harvesthub with mysql database

For an existing database, apply `migrations/20261007_resource_lifecycle_events.sql` before using the lifecycle records page. Fresh installations get the event table from `schema.sql`. Lifecycle events are recorded from the migration onward; older item additions cannot be backfilled because the previous schema did not store their timestamp or creator.
