---
paths:
  - 'app/Services/PerformanceInsights/**'
---

# Performance Insights

## Performance insights use safe task aggregates
Interpret performance questions only into known, aggregate task metrics. Do not execute user-authored SQL or pass database credentials/data to an LLM. Use approved completed tasks as confirmed performance, preserve tenant scope through Eloquent, and retain each query history only for its originating admin.
