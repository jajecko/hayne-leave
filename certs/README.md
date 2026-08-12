# Deployment-local AD certificates

This directory exists only as the default Docker Compose bind-mount source used by CI and local development.

Do **not** commit HAYNE internal CA certificates or LDAP credentials to this public repository.

On the QNAP deployment set in `.env`:

```env
LDAP_CA_HOST_DIR=/share/Container/jorani/certs
```

The host directory must contain:

```text
hayne-ad-ca-chain.pem
```

The container mounts the directory read-only at `/opt/hayne/certs`, and the AD preview reads `/opt/hayne/certs/hayne-ad-ca-chain.pem` by default.
