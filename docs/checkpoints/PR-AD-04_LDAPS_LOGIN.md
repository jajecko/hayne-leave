# PR-AD-04 — guarded HAYNE AD login over LDAPS

Date: 2026-08-12

## Goal

Add interactive Active Directory authentication for already provisioned HAYNE employees without enabling Jorani's native LDAP login path.

## Authentication boundary

- `hayne_ad_identity` is the authority boundary for AD-managed accounts.
- An active Jorani user with an identity row authenticates only against AD.
- The exact AD distinguished name is read from `hayne_ad_identity.distinguished_name`.
- A failed AD bind, unreachable DC, missing CA, or TLS failure does **not** fall back to the password hash stored in Jorani.
- Only logins explicitly listed in `HAYNE_AD_AUTH_LOCAL_LOGINS` may authenticate with a local Jorani password while HAYNE AD auth is enabled.
- Default local break-glass account: `jadmin`.
- Jorani's own `LDAP_ENABLED` remains `FALSE`.

## Transport

- LDAPS only: `ldaps://<host>:636`.
- Hosts come from the existing `HAYNE_AD_HOSTS` list and are tried sequentially for DC failover.
- The existing CA file `HAYNE_AD_CA_FILE` is required and must be readable.
- Certificate verification is required with `LDAP_OPT_X_TLS_REQUIRE_CERT=LDAP_OPT_X_TLS_DEMAND`.
- Protocol version 3 is required; referrals are disabled for the bind.
- Existing network/time limits are reused.

## Session/profile behavior

After a successful AD bind, Jorani's existing `Users_model::checkCredentialsLDAP()` is used. It requires a local user record with `active=TRUE` and loads the normal Jorani session/profile. Roles, manager relationships, leave balances, and authorization remain local Jorani data.

## Configuration

Repository defaults remain safe/off:

```env
LDAP_ENABLED=FALSE
HAYNE_AD_AUTH_ENABLED=FALSE
HAYNE_AD_AUTH_LOCAL_LOGINS=jadmin
```

The deployment must enable `HAYNE_AD_AUTH_ENABLED=TRUE` only after the patched image, CA mount, LDAP PHP extension, and identity table are verified.

## Scope

In scope:
- interactive web login in `Connection::login()`;
- LDAPS certificate validation;
- two-DC failover;
- fail-closed behavior for synchronized users;
- local `jadmin` break-glass login.

Out of scope:
- changes to AD provisioning/planning;
- automatic AD writes;
- password synchronization/storage;
- deleting or disabling Jorani users;
- REST API LDAP authentication;
- SAML/OAuth changes;
- MySQL rebuild/restart;
- production activation before a controlled QNAP smoke test.

## Deployment gates

Before enabling auth on QNAP:
1. merge PR and rebuild/recreate only `app`;
2. keep `HAYNE_AD_AUTH_ENABLED=FALSE` during first runtime verification;
3. verify `ldap` extension, CA file, identity table, and both AD DCs;
4. verify local `jadmin` login still works;
5. set only `HAYNE_AD_AUTH_ENABLED=TRUE`, recreate only `app`;
6. smoke-test one synchronized active employee with a valid AD password;
7. verify the same employee is rejected with an invalid AD password;
8. verify `jadmin` still authenticates locally;
9. verify MySQL container was not restarted.

Rollback: set `HAYNE_AD_AUTH_ENABLED=FALSE` and recreate only the application container.
