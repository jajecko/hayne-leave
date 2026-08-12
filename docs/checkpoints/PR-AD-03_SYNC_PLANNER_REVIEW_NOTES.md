# PR-AD-03 review notes

Reviewer focus:

1. Default invocation must remain read-only.
2. `--migrate` and `--apply` must require `HAYNE_AD_APPLY_ENABLED=TRUE`.
3. `--apply` must require the exact current plan SHA256.
4. New disabled AD Employees must remain `SKIP_DISABLED_NEW`.
5. Local users without objectGUID linkage must never be auto-adopted by login.
6. `jadmin` must remain protected and preserved.
7. Existing linked users missing from AD scope must be preserved, not deleted or auto-deactivated.
8. Existing roles must not be overwritten.
9. Dictionary quality issues must block instead of silently normalizing AD source text.
10. There must be no user delete operation.
