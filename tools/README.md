# tools/

Deploy and seeding helpers for the WooCommerce build. No SSH is available from
the coding environment, so everything goes through git + Cloudways.

## Deploy

1. Commit and push to `main`.
2. Cloudways → luma-store → Deployment via GIT → **Pull**.
3. The `luma-bootstrap` plugin (installed once by hand from
   `wp-content/plugins/luma-bootstrap/`) registers
   `wp-content/luma-src/wp-content/themes` as a theme directory and loads
   `luma-core` from the pulled source, so a Pull is a full deploy.

Cache: Cloudways → Breeze → Purge after theme/CSS changes.

## Seeding (WP-CLI, run from Cloudways SSH once an SFTP/SSH user exists)

`seed.sh` — imports products and lots from `js/products.js` (`LUMA_PRODUCTS`,
`LUMA_LOTS`). To be written in build step 2.
