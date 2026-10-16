# Search indexing explicit opt-in — 0.10.48

## Why

Using `APP_ENV=production` alone is not sufficient to distinguish the public production site from a staging installation that uses production-mode framework settings. Previously, such a staging copy could emit an allowing `robots.txt` and a populated sitemap.

## Change

- `ORVIONART_PUBLIC_INDEXING_ENABLED` is an explicit environment switch and defaults to `false`.
- `/robots.txt` allows crawling and advertises the sitemap only when both `APP_ENV=production` and the explicit switch are enabled.
- Otherwise, `robots.txt` returns `Disallow: /` and the sitemap is an empty XML urlset.
- This is a crawler directive, not access control. Staging still requires password/access protection and separate data/configuration.
- Before the approved public launch, set `ORVIONART_PUBLIC_INDEXING_ENABLED=true` only in the intended production environment and verify `APP_URL`, canonical URLs, robots, and sitemap.

## Validation boundary

A Feature regression source covers the disabled-by-default and explicit-enable branches. The current source-only environment lacks Composer/vendor, so Laravel Feature tests are not claimed as executed; static checks are run by the release gate.
