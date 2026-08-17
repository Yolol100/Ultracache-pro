# Security policy

## Supported release

Security fixes are provided for the latest published UltraCache Pro release. The installed version and public update channel should both show as verified in **Tools > Site Health** before broad deployment.

## Reporting a vulnerability

Do not publish exploitable details in a public issue. Use the private **Report a vulnerability** option in the GitHub repository Security tab:

`https://github.com/Yolol100/Ultracache-pro/security/advisories/new`

Include the affected version, WordPress/PHP versions, required permissions, reproduction steps, impact, and a minimal proof of concept. Remove secrets, customer data, order data, and production URLs.

## Release integrity

Official update releases must contain both:

- `ultracache-pro.zip`
- `ultracache-pro-release.json`

The updater verifies the GitHub asset digests, the manifest digest, the package SHA-256, repository path, release state, version and compatibility metadata before WordPress receives an update package.
