# Persistent runtime users

Runtime users are stored in `xray_runtime_users`. Their UUID values are
encrypted with Laravel's `APP_KEY`. The database is the desired state; Xray is
reconciled with it after a restart.

List users without modifying Xray:

```bash
php artisan xray:users:list inbound-20180
```

Write and automatic reconciliation are disabled by default. Enable them after
validating the patched Xray user API:

```dotenv
XRAY_USER_WRITES_ENABLED=true
XRAY_USER_RECONCILE_ENABLED=true
```

Clear cached configuration:

```bash
php artisan config:clear
```

Run the migration, then add a persistent VLESS user:

```bash
php artisan migrate --force
```

```bash
php artisan xray:users:add \
  inbound-20180 \
  vless \
  11111111-1111-4111-8111-111111111111 \
  joy:test-user-a \
  --port=20180
```

For tags ending in `inbound-PORT`, such as `inbound-20180`, `--port` is
inferred automatically. Passing it explicitly is recommended for the first
Portal validation.

Confirm that the runtime count increased:

```bash
php artisan xray:users:count inbound-20180
```

For VMess, use `vmess` and pass `--alter-id=N` when required.

If a user already exists in the current Xray runtime and only needs to be
imported into the persistent roster, use the same add command with
`--persist-only`.

Manually verify or restore the desired state:

```bash
php artisan xray:users:reconcile
php artisan xray:users:reconcile --email=joy:test-user-a
```

With Laravel's once-per-minute scheduler running, reconciliation is retried
automatically. A temporary Xray/API failure is stored in `last_error`; it does
not delete or disable the desired user.

Remove the test user:

```bash
php artisan xray:users:remove inbound-20180 joy:test-user-a
```

The remove command first records the persistent desired state as disabled and
then removes the runtime user. If Xray is temporarily unavailable, the next
scheduled reconciliation retries the removal.
