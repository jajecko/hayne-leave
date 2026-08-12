# PR-AD-01/02 — LDAPS plumbing and read-only AD preview

## Scope

This slice adds the deployment plumbing and a standalone **read-only** Active Directory preview for HAYNE Leave.

It does not:

- write to Active Directory,
- write to the Jorani/MySQL database,
- create/update/delete Jorani users,
- enable Jorani native LDAP authentication,
- change login behaviour,
- change leave balances, requests, history, roles or entitlements.

## Source of truth and scope

The directory source is:

```text
OU=O365 Users,OU=HayneUsers,DC=hayne,DC=pl
```

Only objects matching the configured employee type are selected. The deployment value is:

```text
employeeType=Employee
```

Semantics agreed for HAYNE:

- `Employee` — employee covered by HAYNE Leave,
- `Contractor` — external contractor, excluded from HAYNE Leave,
- empty `employeeType` — technical/shared/room/display account, excluded.

Disabled former employees remain `employeeType=Employee`; account enabled/disabled state is audited separately.

## Confirmed directory checkpoint before implementation

Both domain controllers were verified over LDAPS and returned the same replicated state:

```text
USERS in OU:             49
Employee total:          45
 Employee ENABLED:       30
 Employee DISABLED:      15
Contractor:               1
Empty employeeType:       3
AD01 <-> AD02:          PASS
```

Domain controllers:

```text
HAYNE-SRV-AD01.hayne.pl
HAYNE-SRV-AD02.hayne.pl
```

The service bind account is read-only. Its password is deployment secret material and must never be committed.

## Deployment configuration

Keep native Jorani LDAP login disabled:

```env
LDAP_ENABLED=FALSE
```

Add to the deployment `.env`:

```env
HAYNE_AD_SYNC_ENABLED=TRUE
HAYNE_AD_HOSTS=hayne-srv-ad01.hayne.pl,hayne-srv-ad02.hayne.pl
HAYNE_AD_PORT=636
HAYNE_AD_BASE_DN=OU=O365 Users,OU=HayneUsers,DC=hayne,DC=pl
HAYNE_AD_BIND_DN=HAYNE\svc_jorani_ldap
HAYNE_AD_BIND_PASSWORD=<deployment secret>
HAYNE_AD_CA_FILE=/opt/hayne/certs/hayne-ad-ca-chain.pem
HAYNE_AD_EMPLOYEE_TYPE=Employee
HAYNE_AD_NETWORK_TIMEOUT=5
HAYNE_AD_TIME_LIMIT=10
LDAP_CA_HOST_DIR=/share/Container/jorani/certs
```

The existing QNAP directory must contain:

```text
/share/Container/jorani/certs/hayne-ad-ca-chain.pem
```

Docker Compose mounts this directory read-only at `/opt/hayne/certs`.

## CLI

After the image is rebuilt, run inside the application container:

```bash
docker exec -it app-app-1 php /opt/hayne/ad-sync-preview.php --check
```

This verifies both configured domain controllers and compares the selected `login|objectGUID` identity set.

Then run:

```bash
docker exec -it app-app-1 php /opt/hayne/ad-sync-preview.php
```

The preview reports:

- total selected employees,
- enabled/disabled counts,
- missing name/surname/mail/manager/department/title/objectGUID,
- unique departments and titles with counts,
- managers outside the selected employee scope,
- duplicate login/mail/objectGUID,
- AD01/AD02 parity and selected failover source.

Machine-readable output is available with:

```bash
docker exec -it app-app-1 php /opt/hayne/ad-sync-preview.php --json
```

## Failover behaviour

Hosts are tried independently. If AD01 fails but AD02 succeeds, preview remains usable and reports `PASS WITH FAILOVER`. If both healthy hosts return different identity sets, result is `REVIEW REQUIRED` with a non-zero exit code. If every configured host fails, the command stops.

## TLS guardrails

The tool uses only `ldaps://`, requires the configured CA bundle and sets `LDAP_OPT_X_TLS_REQUIRE_CERT` to `LDAP_OPT_X_TLS_HARD`. It does not fall back to plaintext LDAP.

## Write guardrails

`hayne/tools/ad-sync-preview.php` contains no MySQL/PDO connection and no LDAP add/modify/delete operation. It is an inventory/validation tool only.

The apply phase is intentionally deferred to PR-AD-03 after the read-only output is verified on QNAP.

## Authentication guardrail

AD password authentication is intentionally out of scope. The current browser-to-application endpoint is HTTP; native/AD login must not be enabled until HTTPS/reverse-proxy work is explicitly completed.
