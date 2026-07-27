# Database Connection

The connection is configured entirely through environment variables. Every value is
validated at boot: a misconfiguration fails with a message naming the key, rather than
surfacing later as a driver error.

## Variables

| Variable | Default | Notes |
|---|---|---|
| `DB_NAME` | — | Required |
| `DB_USERNAME` | — | Required, non-empty |
| `DB_PASSWORD` | — | Required, may be empty |
| `DB_HOST` | `127.0.0.1` | Not permitted alongside `DB_SOCKET` |
| `DB_PORT` | `3306` | 1–65535, not permitted alongside `DB_SOCKET` |
| `DB_SOCKET` | — | Unix socket path; excludes host and port |
| `DB_CHARSET` | `utf8mb4` | `utf8mb4` is the only accepted value |
| `DB_CONNECT_TIMEOUT` | driver default | 1–60 seconds |
| `DB_SSL_CA` | — | Enables TLS with server verification |
| `DB_SSL_CERT` | — | Requires `DB_SSL_KEY` and `DB_SSL_CA` |
| `DB_SSL_KEY` | — | Requires `DB_SSL_CERT` and `DB_SSL_CA` |

`DB_MIGRATION_USERNAME` and `DB_MIGRATION_PASSWORD` are described in
[database-privileges.md](database-privileges.md).

## Transport

The database is reached over TCP by default. When the server runs on the same machine,
a unix socket is both faster and simpler to secure — there is no network path to
protect:

```dotenv
DB_SOCKET=/run/mysqld/mysqld.sock
DB_NAME=oezcms
DB_USERNAME=oezcms_runtime
DB_PASSWORD=…
```

`DB_HOST` and `DB_PORT` must then be absent. Configuring both transports is rejected
rather than resolved by precedence: whichever lost would sit in the file looking like
it takes effect.

## TLS

Required whenever the database is reached across a network the deployment does not
control.

```dotenv
DB_HOST=db.internal
DB_SSL_CA=/etc/ssl/mariadb-ca.pem
```

Configuring `DB_SSL_CA` enables TLS **and** server certificate verification. The
verification is not a separate setting and cannot be turned off: encryption without it
protects against nobody in a position to attack the connection, so an encrypted
unverified connection is a false sense of security rather than a weaker one.

Client certificates authenticate this application to the server:

```dotenv
DB_SSL_CERT=/etc/ssl/client-cert.pem
DB_SSL_KEY=/etc/ssl/client-key.pem
```

Certificate and key must both be set, and both require `DB_SSL_CA`. Presenting a
client certificate to a server nobody verified authenticates the wrong direction.

Paths are checked for being non-empty, not for existing. A wrong path fails the
connection attempt with the driver's own message, which is more accurate than a second
opinion about what counts as a readable file.

## Connect timeout

```dotenv
DB_CONNECT_TIMEOUT=5
```

Bounds how long a connection attempt may hang before failing. It is opt-in: no default
is imposed, because changing connection behaviour for every deployment needs a reason
more concrete than caution.

Read and write timeouts are deliberately absent. PDO's MySQL driver does not expose
them, and reaching them through `net_read_timeout` in the session init command would
mean a second mechanism serving a third purpose.

## Recommended setups

**Single server.** Socket, no TLS, separate deploy account:

```dotenv
DB_SOCKET=/run/mysqld/mysqld.sock
DB_NAME=oezcms
DB_USERNAME=oezcms_runtime
DB_PASSWORD=…
DB_MIGRATION_USERNAME=oezcms_deploy
DB_MIGRATION_PASSWORD=…
```

**Separate database host.** TCP, TLS with verification, bounded connect attempt:

```dotenv
DB_HOST=db.internal
DB_PORT=3306
DB_NAME=oezcms
DB_USERNAME=oezcms_runtime
DB_PASSWORD=…
DB_SSL_CA=/etc/ssl/mariadb-ca.pem
DB_CONNECT_TIMEOUT=5
```
