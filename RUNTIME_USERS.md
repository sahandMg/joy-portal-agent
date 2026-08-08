# Runtime user validation

List users without modifying Xray:

```bash
php artisan xray:users:list inbound-20180
```

Write operations are disabled by default. Enable them only on an isolated test
inbound:

```dotenv
XRAY_USER_WRITES_ENABLED=true
```

Clear cached configuration:

```bash
php artisan config:clear
```

Add a test VLESS user:

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

Remove the test user:

```bash
php artisan xray:users:remove inbound-20180 joy:test-user-a
```

HandlerService changes are runtime-only. Xray restart removes them unless the
agent re-synchronizes the desired users.
