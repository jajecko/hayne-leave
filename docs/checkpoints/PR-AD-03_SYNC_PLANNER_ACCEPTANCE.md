# PR-AD-03 acceptance checklist

- [ ] CI planner lint passes.
- [ ] Planner self-test passes.
- [ ] Fresh DB image contains `hayne_ad_identity` with unique `object_guid`.
- [ ] Default migration write gate refuses writes while `HAYNE_AD_APPLY_ENABLED=FALSE`.
- [ ] QNAP runtime dry-run sees AD01/AD02 parity and the expected 45/30/15 baseline.
- [ ] QNAP runtime dry-run classifies 30 active Employees for create, 15 disabled new Employees for skip, and preserves `jadmin`.
- [ ] QNAP runtime dry-run reports zero delete operations.
- [ ] Any dictionary/manager data-quality blockers are reviewed before migration/apply.
- [ ] Existing QNAP `compose.yaml`, `Dockerfile.hayne-local`, `app.env`, and MySQL volume are preserved; deployment is adapted manually because QNAP has no Git.
- [ ] No `--apply` is executed as part of PR validation.
