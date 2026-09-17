---
paths:
  - '{config/backpack/base.php,routes/web.php}'
---

# Backpack

## StaffX public home and admin workspace routes
Keep the public StaffX feature page at `/`. All Backpack workspace, authentication, organization-registration, and Google OAuth routes live beneath the configured `/admin` prefix. Generate internal URLs with `backpack_url()` and named routes so the prefix remains consistent.
