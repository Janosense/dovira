# Features

One folder per feature, resolved via the Features table in root CLAUDE.md:

    docs/features/{feature}/
    |-- FEATURE.md          # feature mini-architecture (+ UI section if the feature has screens)
    |-- design/             # {name}/: brief.md + the copy of the Claude Design design (canvas.json, .dc.html per artboard); the design system's copy is docs/design-system/{namespace}/
    |-- sprints/            # SPRINT-N.md (goal, fixed decisions, steps — ticked by /close-step) + SPRINT-N-PLAN.md
    `-- verification/       # sprint-N/step-M.md — one folder per sprint; written by /do-step (the user verifies by it before /close-step)

The verification
folder is the feature's growing manual regression suite. Files in design/
are design artifacts, never application code: nothing there is copied into
a code area; the shared tokens and components they use are indexed in
docs/DESIGN.md.
