---
paths:
  - 'app/Models/**'
---

# Models

## Department tenant boundaries
Departments are available only when IS_TENANT is enabled. They belong to an organization, users may have one department, and department administrators are existing organization admins connected through the department_administrators pivot. Keep all department reads tenant-scoped.
