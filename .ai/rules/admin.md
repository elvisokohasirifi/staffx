---
paths:
  - app/Http/Controllers/Admin/TaskCrudController.php
  - app/Http/Controllers/Admin/OrganizationCrudController.php
---

# Admin

## Notify staff on task assignment and approval
Whenever admins create tasks (single or bulk) or approve completed tasks, notify each affected staff member. Bulk operations should group multiple tasks per assignee into one notification instead of sending one message per task.

## Default organization rename boundary
When tenancy is enabled, the configured ADMIN_EMAIL user may rename only the organization marked is_default. Keep the Organization CRUD create/delete operations disabled and expose only the name field on update; direct edit/update requests for non-default organizations must be forbidden.
