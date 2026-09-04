# Release artifact policy

## Source branch

`main` contains source, tests and release tooling only. Generated plugin ZIPs, checksums, logs and build directories are release outputs and must not be committed to the default branch.

## Required release flow

1. Start from an exact reviewed commit.
2. Run the full static, compatibility and packaging checks.
3. Build the plugin ZIP twice from the same source.
4. Compare SHA-256 checksums for determinism.
5. Test the exact ZIP on a representative staging site.
6. Test cache activation/deactivation, purge/preload, public/logged-in behavior, forms, builder previews and relevant WooCommerce flows.
7. Create a tagged GitHub Release and attach the verified ZIP plus checksum.
8. Publish only after staging, rollback and monitoring gates pass.

## Existing root ZIP

The currently tracked root ZIP predates this source-only policy. Do not delete it until an equivalent, checksum-verified GitHub Release asset is available. After the release asset is verified, remove the root ZIP in a dedicated cleanup PR.

## Safety

A successful source build or checksum comparison is not production proof. Caching, CSS/JS rewriting, object-cache drop-ins and WooCommerce behavior remain staging-first and require a tested rollback path.
