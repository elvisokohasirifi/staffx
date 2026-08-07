---
paths:
  - app/Http/Controllers/Admin/TaskCrudController.php
---

# Admin

## Notify staff on task assignment and approval
Whenever admins create tasks (single or bulk) or approve completed tasks, notify each affected staff member. Bulk operations should group multiple tasks per assignee into one notification instead of sending one message per task.
