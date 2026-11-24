# Feature — {{FEATURE_NAME}}

<!-- playbook: v3.0.4. One per feature, at docs/features/{{name}}/FEATURE.md
     beside its sprints/. Read whole at the start of every step. Shape,
     triggers and what does not belong here: docs/CONTRACTS.md →
     `### docs/features/{feature}/FEATURE.md`. -->

## Scope
{{Two to four sentences: what this feature does, for whom.}}

## Fit
- **Code path:** `{{as in the CLAUDE.md Features table}}`
- **Entry point:** {{the one registration in shared code — an import, a route registration, a hook call}}
- **Depends on:** {{shared code it reads — a change there affects this feature; or "—"}}
- **Exposes:** {{what other features may rely on — endpoints, events, components; a change here "touches shared code"; or "—"}}

## Structure
| Module | Where | Responsibility |
|---|---|---|
| {{name}} | `{{path}}` | {{a phrase}} |
{{A single-feature project: "see ARCHITECTURE.md → Modules".}}

## Data
{{Tables this feature owns, by name — no other feature writes them; or "—".}}

## UI
<!-- Delete this section if the feature has no screens. A screen is named
     exactly as its artboard. -->
| Screen | Route | In code | Artboard |
|---|---|---|---|
| {{Dashboard}} | `{{/…}}` | `{{page class or view}}` | `design/{{name}}/{{Screen}}--*` |

## Roadmap
<!-- One entry per sprint; every entry has a full sprints/SPRINT-N.md, all
     written by discovery in one pass. A not-yet-started sprint is rewritten
     by a re-planning chat only if its scope changed. -->
- Sprint 1 — {{goal as a user-visible outcome}} (`sprints/SPRINT-1.md`)
- Sprint N — {{goal}} (`sprints/SPRINT-N.md`)
