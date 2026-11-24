# {{CODE_AREA_NAME}} — code-area conventions

<!-- playbook: v3.0.4. Code-area CLAUDE.md: ONE per code directory (an
     app, package, or service), even when
     several features live inside it. Delta only — everything shared (core
     rules, step protocol, git model) stays in the ROOT CLAUDE.md.
     Claude Code loads this file automatically when working inside this
     directory. Feature docs do NOT live here — they are in
     docs/features/{feature}/ (see the Features table in root CLAUDE.md). -->

{{One or two sentences: what this area is (e.g. "the API service — hosts
shared middleware plus feature modules under {{path}}/features/").}}

## Feature isolation (if this area hosts several features)
- Each feature lives in `{{path/features/{name}/}}` with its own bootstrap file.
- `{{the area's entry file}}` contains exactly one registration per feature — nothing else feature-specific.
- Shared code ({{helpers, base styles, common components}}) is changed ONLY as
  an explicit plan task marked **"touches shared code — may affect other
  features"**; the plan must list which features consume it.
- A feature never writes to data owned by another feature (see each FEATURE.md → Data).

## Area conventions
<!-- Rules of this area only, one sentence each, in the shape of
     `docs/TECH-STACK.md` → CONVENTIONS (`X is Y (pointer)`); a rule for the
     whole project goes there instead. Written by /close-step when a step
     sets a rule of this area (docs/CONTRACTS.md → TECH-STACK.md). -->
- {{… is … (`…`)}}

## Local commands
```bash
{{only if they differ from root commands, e.g. this area's asset build}}
```
