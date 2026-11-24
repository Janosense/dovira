# Documentation contracts — Dovira

<!-- playbook: v3.0.4. The one source for three decisions about project
     documents: what to read before a task (Read index), which change in the
     code obliges which record (Write index), and the exact shape of that
     record (Contracts). Entered from CLAUDE.md → Documentation.
     Units a Record uses — never "a line", which measures nothing:
       a sentence — one full stop, no clauses chained by semicolons;
       a phrase   — a table cell or a label, no full stop;
       a bullet   — one list item of one sentence;
       a row      — one table row; a block — one `###` with its fixed bullets.
     A document not listed here is not written; `docs/ops/` is the one
     exception — step-by-step material for a person (server set-up,
     dashboard menus, launch day), outside these contracts and never read
     by a step. A state document is read by section and replaced in its
     section — never appended; no provenance (sprint, step, date, "as
     built", "FIXED —"), no future tense. A journal document gets a new
     entry of the fixed shape where its contract says; an entry is never
     edited except where its contract names the one permitted edit.
     Three kinds of trigger in the Write index: by the diff (checkable);
     *judgement* — by the plan's tasks and `### Mid-step decisions`;
     *event* — something that happened, written at once (INCIDENTS only).
     Who reads what, when:
       /plan-step step 2 → Read index, then the named sections only
       /adhoc step 2     → Read index, the same way, before the mini-plan
       /plan-step step 5 → Write index against the step's tasks → the plan's
                           `### Docs to update` (file → record, one sentence each)
       /do-step step 5   → the Contract of each file the plan names, before
                           writing the record, in the task's commit
       /close-step step 2 → Write index against `git diff --name-only`:
                           every trigger hit must have its file in the diff
       /question step 2  → Read index
       discovery (Question mode) → Read index, then the named sections
                           only; it writes nothing
       discovery (Init / Adoption / Feature) → Contracts, when it fills or
                           extends a document; adds the project-specific
                           documents to all three parts below -->

## Read index
| Task kind | File → sections |
|---|---|
| a module, a webhook, a realtime path, a queued or scheduled job | `ARCHITECTURE.md` → Modules, Flows, Jobs |
| anything that queues, caches, stores files, sends mail, emits events, signs in, reads settings | `ARCHITECTURE.md` → Platform |
| work with an external service | `ARCHITECTURE.md` → Integrations, then the provider's documentation its Docs link gives; `THIRD-PARTY-SERVICES.md` → Rules, that service |
| an environment, a deploy, a process or cron entry | `ARCHITECTURE.md` → Environments & deploy |
| any task that writes code | `TECH-STACK.md` → whole, ANTI-PATTERNS and CONVENTIONS first |
| choosing an approach, a library or a version | `TECH-STACK.md` → Stack; `DECISIONS.md` → the entry |
| "Tests to write" in a plan; writing or changing a test | `TESTING.md` → whole |
| a migration, a query, a model, a response shape | `DATA-MODEL.md` → Conventions, the tables the step touches |
| any domain logic — a rule, a state, a term | `DOMAIN.md` → Glossary, Lifecycles, Rules → the area |
| any UI work — a screen, a component, a style | `DESIGN.md` → whole; the screen's artboard; the feature's `FEATURE.md` → UI |
| an endpoint, a webhook, a call to an outside service, a payload | `API.md` → Rules, the service or endpoint the step touches |
| every step of a feature, at the start of the plan | `docs/features/{feature}/FEATURE.md` → whole |
| a task in a code area that hosts other features | their `FEATURE.md` → Fit |
| proposing a change of architecture, tooling, data or domain; the sprint's Fixed decisions | `DECISIONS.md` → the entries by title |
| session start (CLAUDE.md core rule 6); the planner's "what changed since"; a question about what happened last or what is owed | `WORKLOG.md` → Open, the newest entry |
| — (no step reads it; the playbook's author does, in an analysis chat) | `INCIDENTS.md` |
| `/fix-step` — the failed item; `/close-step` step 1 — that the guide exists; a question about how a shipped step is verified by hand | the step's verification guide |
| — (discovery chats only, during Init; no step ever sees it) | `DISCOVERY-NOTES.md` |

## Write index
A trigger is matched against `git diff --name-only` of the step; a trigger
marked *judgement* is matched against the plan's tasks and `### Mid-step
decisions` instead — by `/plan-step` when deriving the plan, by `/close-step`
when closing; a trigger marked *event* is written the moment the event
happens, by whoever is running.

| Change in the diff (checkable) | File | Record |
|---|---|---|
| a module directory used by more than one feature or at a framework boundary — new or removed | `ARCHITECTURE.md` | Modules row |
| a scheduled job, queue worker or console command run by cron — new, removed or rescheduled | `ARCHITECTURE.md` | Jobs row |
| a webhook handler, a realtime channel, a signed-link or session flow that other code must not improvise — new or changed | `ARCHITECTURE.md` | Flows block |
| a queue, cache or storage driver, an authentication mechanism, an event bus, a settings store, an error reporter — new or replaced | `ARCHITECTURE.md` | Platform row |
| an integration client class or SDK — new or removed | `ARCHITECTURE.md` | Integrations row, Docs included (and a `THIRD-PARTY-SERVICES.md` block) |
| *judgement:* a task calls a service whose Integrations row has no Docs link, or moves the service to another API version | `ARCHITECTURE.md` | that Integrations row's Docs |
| a name in `.env.example` that belongs to a service — new or removed | `THIRD-PARTY-SERVICES.md` | that service's `.env` table |
| a webhook route — new or changed | `THIRD-PARTY-SERVICES.md` | Webhook address bullet (and an `ARCHITECTURE.md` Flows block when the flow is fixed) |
| how tests empty or fake a service — changed | `THIRD-PARTY-SERVICES.md` | Rules |
| the dependency manifest or lockfile — a layer added, replaced or removed | `TECH-STACK.md` | Stack row |
| the check script or the task gate — changed | `TECH-STACK.md` | Check command |
| *judgement:* a task is the first of its kind and fixes how that kind of code is written | `TECH-STACK.md` | one ANTI-PATTERNS or CONVENTIONS sentence, named by the plan |
| *judgement, at close:* a `### Mid-step decisions` item or a fix diagnosis shows a stack default overridden or switched off, an idiom that contradicted a decision or an invariant, or a new architecture test | `TECH-STACK.md` | one ANTI-PATTERNS or CONVENTIONS sentence |
| a shared test helper, a fake or the test bootstrap file — new or changed | `TESTING.md` | Fakes & helpers bullet |
| a new test directory or kind of test (a layer) | `TESTING.md` | Layers row |
| the test command or the test database set-up — changed | `TESTING.md` | Run |
| the step's hand-over while Layers has the full run's time or a Cost of one test empty, or the step added a layer | `TESTING.md` | Layers: the costs and the full run's time, from the step gate's run |
| *judgement, at close:* a `### Mid-step decisions` item fixes how tests are written here, or names a trap that will recur | `TESTING.md` | one Rules sentence |
| a migration — new or changed | `DATA-MODEL.md` | the table's block, replaced (a new block for a new table; none for a dropped one) |
| a model's type mapping or an enumeration's values — changed | `DATA-MODEL.md` | that column's Notes |
| a polymorphic or logical relation — new or changed | `DATA-MODEL.md` | Relations sentence |
| *judgement:* a task introduces or renames a domain term, adds a state or a transition, or changes a rule | `DOMAIN.md` | Glossary row / Lifecycles row / Rules sentence |
| an enumeration of states in code — new or changed | `DOMAIN.md` | Lifecycles table |
| *judgement, at close:* a `### Mid-step decisions` item states a law of the domain the step uncovered | `DOMAIN.md` | one Rules sentence (a choice → `DECISIONS.md` instead) |
| a `DOMAIN.md` → Rules sentence that root `CLAUDE.md` → Domain invariants copies — rewritten or removed | root `CLAUDE.md` | that Domain invariants sentence, replaced verbatim (removed with its rule); no sentence is added |
| a shared UI component — new or removed | `DESIGN.md` | Components row |
| the design-system file (tokens) — created or moved | `DESIGN.md` | Tokens |
| *discovery only:* a new copy of a Claude Design artifact under `docs/design-system/` or `docs/features/*/design/` | `DESIGN.md` | Source |
| a route the project answers — new, changed or removed | `API.md` | Exposed row |
| a webhook route or its handler — new or changed | `API.md` | Incoming webhooks block (and the `THIRD-PARTY-SERVICES.md` Webhook address bullet) |
| a call in a service client — new or changed (address, fields, statuses mapped) | `API.md` | Consumed row |
| a route and page of the feature — new or removed | `FEATURE.md` | UI row |
| a directory or module inside the feature's code path — new | `FEATURE.md` | Structure row |
| a migration creating or dropping a table of the feature | `FEATURE.md` | Data entry (with the `DATA-MODEL.md` block) |
| *judgement:* a task first reads another feature's code, or exposes something for others | `FEATURE.md` | Fit → Depends on / Exposes |
| *judgement, at close:* the user chose between alternatives in a plan question, or a `### Mid-step decisions` item reversed or refined a fixed decision | `DECISIONS.md` | one entry (and `Superseded by:` on the old one) |
| *judgement, /fix-step:* the cause of a failure was a decision | `DECISIONS.md` | one entry |
| a step, a fix after a close, or an ad-hoc closes | `WORKLOG.md` | one entry; `## Open` replaced |
| *event, at once:* an irreversible action or one outside the step's branch ran without the user's explicit yes before it, or caused a loss despite it — data reset or deleted outside a test database, files the step did not create deleted, git history rewritten, an environment written, something created, changed, sent, charged or deleted at a live outside service, a deploy | `INCIDENTS.md` | one `loss` entry |
| *event, at once:* the user stopped or corrected an action of the agent (not an answer to a plan question) | `INCIDENTS.md` | one `intervention` entry |
| *event, at once:* a step stopped because two playbook rules cannot both hold, or a command requires what the project does not have | `INCIDENTS.md` | one `blocked` entry |
| *event, at close or fix:* a second `/fix-step` on the same step, or the same failure in two consecutive steps | `INCIDENTS.md` | one `repeat` entry |
| the step's build is complete — the hand-over | the step's verification guide | the whole file, once |
| `/fix-step` closes a fix before the step's close | the step's verification guide | a fix note under the failed item |
| *discovery only:* a topic chat of Init discovery settles a topic | `DISCOVERY-NOTES.md` | one entry; removed at assembly |
| an environment, a deploy script, a process-manager or cron config — new or changed | `ARCHITECTURE.md` | Environments & deploy row |

## Contracts

### docs/ARCHITECTURE.md
- **Reader & question:** the planner before a task that adds a module, a
  flow, a job, an integration or an environment; the builder before touching
  one. One reading of the section answers: where new code goes and whom it
  may call; which shared mechanism already does this (queue, cache,
  authentication, storage, mail, events, settings) so none is invented; which flow is fixed
  and how it runs; which module talks to which service, what happens when
  the service fails and where its official documentation is; how each environment runs and deploys. Kept short
  enough to be read whole at `/plan-step`.
- **Type:** state — a section is read whole and replaced; nothing is
  appended. No provenance (sprint, step, date, "as built", "built so far"),
  no future tense: what is not built is not here. `FIXED` marks a flow the
  builder may not deviate from; its description is still replaced when the
  code shows it more precisely — never twinned with an "as built" block.
- **Sections (fixed, this order):** Overview · Modules · Platform · Flows ·
  Jobs · Integrations · Environments & deploy
- **Record:**
  - Overview — one paragraph naming the moving parts and the constraints
    every step must respect (one sentence each); a diagram only if it says
    something the list does not; rewritten only when a part or a constraint
    is added or removed
  - Modules — one table row per entry point (public pages, signed-in
    areas, back office, API, webhooks, command line) and per module that more than one feature uses:
    Module · Where · Responsibility (a phrase) · Must never (a phrase) ·
    Used by. Below the table, **Dependency rules**: the direction between
    layers, how modules talk to each other (direct call, event, contract),
    where cross-module reads go, what enforces it (one sentence each); and one
    sentence on a module's layout that a new module copies
  - Platform — one table row per shared mechanism (queue, cache, sessions,
    authentication, file storage, mail, events and realtime, settings
    store, error reporting): Concern · Mechanism · Where configured · Rule
    for new code (a phrase)
  - Flows — one `### {Flow}` per flow that must not be improvised: Started
    by · Steps (numbered, one sentence each, naming modules and services) ·
    State kept in · `FIXED` when CLAUDE.md → Domain invariants links here
  - Jobs — one table row per scheduled or queued job: Job · Schedule ·
    What it does (a phrase) · Module; a job gets a Flows block only when
    its steps are fixed
  - Integrations — one table row per external service: Service · Used for
    (a phrase) · Module that talks to it · Auth model · On failure · Docs
    (the link to the provider's official reference, for the API version
    used — the one source every step builds the service's calls from)
  - Environments & deploy — one table row per environment: Environment ·
    Tracks (branch) · Runs as · Deploy (manual steps named); one row for
    running locally
- **Not here → where:**
  - a feature's own modules, screens, pages, fields, classes → that
    feature's `FEATURE.md`, or the code
  - what a script, a page or a command does → the script, the page, the
    command
  - tables and columns → `DATA-MODEL.md`; endpoint and payload shapes →
    `API.md`
  - `.env` names, accounts, service set-up, launch day →
    `THIRD-PARTY-SERVICES.md`
  - a domain rule a flow applies → `DOMAIN.md`; a business value → config
  - why a choice was made → `DECISIONS.md`; when it was built →
    `WORKLOG.md`; how it is tested → `TESTING.md`; a rule for code →
    `TECH-STACK.md`
  - server set-up, runbooks, step-by-step guides for a person → `docs/ops/`
    (outside these contracts)
- **Writer & moment:** `/do-step` step 5 — the records the plan's
  `### Docs to update` names, in the task's commit. Discovery fills the
  file at Init and Adoption and, in Feature mode, adds only what the
  feature's decisions put outside its own code.

### docs/THIRD-PARTY-SERVICES.md
- **Reader & question:** the planner or builder before a task that calls,
  configures, fakes or verifies an outside service. One reading of the
  block answers: which `.env` names the service has and what each one is
  for (calls the API, verifies webhooks, signs links, public); what the
  code assumes the service is configured to do; what differs on a
  development machine and how tests avoid the real service; how to see
  that it answers.
- **Type:** state — a block is replaced; no "built", "planned", "spike",
  no sprint or step; a service the code does not call yet has no block.
- **Sections (fixed, this order):** Rules · Services
- **Record:**
  - Rules — three to five sentences for the whole project: where keys live
    (`.env` only; `.env.example` lists every name, values empty); how tests
    neutralise services (which file empties keys, what is faked); the
    health command and which services it does not cover
  - Services — one `### {Service}` per service the code calls, with exactly
    these bullets: `.env` names (table: Name · Role) · Webhook address
    (path, or "none") · Assumed configuration (one sentence per switch the
    code relies on) · Locally (tunnel / mailbox / fake — one sentence) ·
    Check (one sentence: command or action and what must be seen)
- **Not here → where:**
  - which module talks to it, auth model, behaviour on failure →
    `ARCHITECTURE.md` → Integrations
  - request and response shapes, the calls used → `API.md` → Consumed
  - the link to the service's documentation → `ARCHITECTURE.md` →
    Integrations → Docs
  - dashboard menus and set-up steps, account creation, launch day,
    hosting and repository hosting → `docs/ops/`
  - local tooling as such (container set-up, mailbox, tunnel) → `ARCHITECTURE.md` →
    Environments & deploy, the local row
  - key values → nowhere in the repository
  - when or why a service was added → `WORKLOG.md` / `DECISIONS.md`
- **Writer & moment:** `/do-step` step 5, in the commit that adds the
  client, the `.env.example` names or the webhook route; discovery lists
  no planned services here — a block appears with the code.

### docs/TECH-STACK.md
- **Reader & question:** every role before writing code: which major
  versions of which libraries the code is written against; which of the
  stack's defaults are wrong in this project and what to do instead; which
  open choices are already fixed; what command gates a commit. Read whole.
- **Type:** state — a sentence or row that stops being true is edited or removed; no
  provenance, no "installed in", no "verified again", no dates.
- **Sections (fixed, this order):** Stack · Check command · ANTI-PATTERNS ·
  CONVENTIONS
- **Record:**
  - Stack — one table row per layer: Layer · Choice · Major version. Exact
    versions are the lockfile. A package has a row only when it stands for
    a layer (framework, UI, database, realtime, PDF, testing, analysis,
    formatting); a transitive or helper package has none
  - Check command — the command, what it runs in order, and the task gate
    for a commit that changes code: three to six sentences
  - ANTI-PATTERNS — one sentence per rule where the stack's default or idiom is
    wrong here: `Do not X — Y instead (the class, path or test that
    embodies it, if one exists)`
  - CONVENTIONS — one sentence per project-wide rule where the stack leaves the
    choice open: `X is Y (pointer)`, present tense
  - A rule is one sentence about a kind of code steps keep writing (a page,
    a migration, a job, a test, a call to a service). A rule that needs a
    paragraph is several rules, or a detail that lives in the code it
    describes; a rule a test enforces is still written, in one sentence — the
    test is the detail. A trap met once is not a rule.
- **Not here → where:**
  - a rule CLAUDE.md → Domain invariants already states → not repeated
  - a rule of one code area → that area's CLAUDE.md → Area conventions
  - why a choice was made, the alternatives → `DECISIONS.md`
  - what a package brings with it, exact versions → the lockfile
  - approval of a dependency → the step's `WORKLOG.md` entry; CLAUDE.md
    core rule 1 is the policy
  - local tooling, repository hosting → `ARCHITECTURE.md` → Environments &
    deploy
  - fonts, icons, tokens → `DESIGN.md`
  - which module may call which → `ARCHITECTURE.md` → Dependency rules
- **Writer & moment:** three, under one criterion — the next step, written
  by an agent with no memory of this one, would write this kind of code
  differently by following the stack's default.
  1. Discovery at Init: Stack (unverified majors, verified by the bootstrap
     step) and the first rules, from the alternatives `DECISIONS.md`
     rejects.
  2. `/plan-step` step 5: a task is the first of its kind and fixes how it
     is done → the plan's `### Docs to update` names the sentence; `/do-step`
     step 5 writes it in that task's commit.
  3. `/close-step` step 2: the builder never writes here on its own — what
     it finds while building is one item in the plan's `### Mid-step
     decisions`; the closer turns an item into a rule only when it shows a
     stack default overridden or switched off, an idiom that contradicted a
     decision or an invariant, or a new architecture test — otherwise the
     item goes with the plan.

### docs/TESTING.md
- **Reader & question:** the planner at "Tests to write" and the builder
  before a test: which layer proves this kind of rule and what it costs;
  how to run only the step's tests; which project rules every test obeys;
  which fakes and helpers already exist so none is written twice. Read
  whole.
- **Type:** state — the current state of how the project tests; no step,
  no date; a sentence or row that stops being true is edited or removed.
- **Sections (fixed, this order):** Layers · Run · Rules · Fakes & helpers
- **Record:**
  - Layers — one table row per layer: Layer · Where · Proves · Cost of one
    test (roughly); then one sentence: the full run's time on the development
    machine, kept true. The layers carry the project's own names; the
    rows fix the mapping: a rule of a domain class → a test with no
    framework and no database; an operation end to end → one test per
    path through it, with the database; a page → one test per state a
    rule decides; a design-system component → none; module boundaries →
    an architecture test, if the stack has one
  - Run — the full command, the step's tests alone (the task gate), one
    test; where the test database comes from: three to four bullets
  - Rules — one sentence per project-specific rule of writing a test
    (how a test signs in and as which kind of account, how time is moved,
    how a state is reached, how a refusal is read, what only a browser does
    and so is a guide item); a trap that would recur is a rule here, phrased as one
  - Fakes & helpers — one bullet per fake (what it stands in for, where it is
    switched on) and per shared helper (name — what it builds or stands in
    for); one sentence on factories: where they live, how states are named
- **Not here → where:**
  - a rule CLAUDE.md core rule 7 or Test-critical zones already state →
    not repeated
  - what one test proves, a helper's methods, a factory's states → the
    test file, the helper, the factory
  - what the architecture tests guard → `TECH-STACK.md` → ANTI-PATTERNS
    (one sentence per architecture test)
  - what a step leaves to the user's hands → that step's verification guide
  - who verifies what → CLAUDE.md → Project profile
- **Writer & moment:** `/do-step` step 5 in the commit that adds a layer, a
  fake, a shared helper, a factory convention or a rule of writing tests;
  `/do-step` step 9 writes the costs and the full run's time from the step
  gate's run while they are empty or when the step added a layer;
  `/close-step` refreshes the full-run time when the check command ran.
  Discovery writes Layers and Run at Init; Rules and Fakes & helpers start
  empty.

### docs/DATA-MODEL.md
- **Reader & question:** the planner and the builder before a migration, a
  query, a model or a response shape: which columns a table has and with
  which constraints; who writes it; how tables relate where no foreign key
  says so; which rules hold across tables. Read by table block, never
  whole.
- **Type:** state — a table's block is replaced when a migration touches
  it; no "added in", no "as built", no FIXED; a dropped table has no block.
  Mirrors the migrations at every commit (CLAUDE.md core rule 5).
- **Sections (fixed, this order):** Conventions · Tables · Relations
  without a foreign key · Invariants across tables
- **Record:**
  - Conventions — one sentence each: engine and charset; naming of tables,
    keys and models; the id and timestamps every table has (not repeated
    per table); the type legend the column tables use; enumerations
    (a string column + an enumeration in code, values listed in Notes);
    money; time and zone;
    which tables are facts (never deleted — a closing mark instead);
    reserved words avoided
  - Tables — one `### {table_name}` per table, the heading being exactly
    the table name: Purpose (one sentence) · Written by (the module, classes
    or functions that are its only writers — a phrase) · the column table: Column · Type · Notes, where
    Notes is constraints only — null, unique, default, the enum's values,
    the fk's target, "generated by the database"
  - Relations without a foreign key — one sentence per polymorphic or logical
    relation: which column names what, and the cardinality
  - Invariants across tables — one sentence per rule that no single table's
    block can hold
- **Not here → where:**
  - what a writer refuses, in what order, how a model guards itself → the
    writer, the model, their tests
  - who may call the writer → `ARCHITECTURE.md` → Dependency rules;
    `TECH-STACK.md` → ANTI-PATTERNS (and the architecture test)
  - where files live, how paths are made → `ARCHITECTURE.md` → Platform
  - a domain rule → CLAUDE.md → Domain invariants, `DOMAIN.md`
  - a stored projection's query → the code; that it exists → its column's
    Notes
  - why a table is shaped so → `DECISIONS.md`
- **Writer & moment:** `/do-step` step 5 in the commit of the migration or
  of the model change (a cast, an enum's values); discovery writes
  Conventions and the first blocks at Init from DECISIONS, the Adoption
  audit from the live schema.

### docs/DOMAIN.md
- **Reader & question:** the planner and the builder before any domain
  logic: what the customer's word means and what it is called in the
  code; which states an entity passes through and who moves it; which
  rules hold — each one a sentence a test can prove; what a role may do.
  Read by section: Glossary and Lifecycles whole, Rules by area.
- **Type:** state — a rule that changes is rewritten, a term that changes
  is renamed; no "as built", no sprint or step, no FIXED (every rule here
  is fixed). The customer's words, present tense.
- **Sections (fixed, this order):** Glossary · Lifecycles · Rules · Roles
- **Record:**
  - Glossary — one table row per term: Term (the customer's word, with
    the original-language word where the UI uses it) · Meaning (one sentence)
    · In code (the class, module or table that is this thing — one name)
  - Lifecycles — one table per entity that has states: State · How it is
    entered (the operation and who may do it) · What is allowed in it
    (a phrase); the state names are the enumeration's values
  - Rules — `### {Area}` per domain area, one sentence per rule: a sentence in
    the customer's terms that a single test can prove; no "how", no "where
    in the UI"
  - Roles — one table row per role: Role · Can do (a phrase)
- **Not here → where:**
  - how a rule is computed, the algorithm → the code and its tests
  - where in the UI a rule shows, which field, button or list → the
    feature's `FEATURE.md` → UI, `DESIGN.md`
  - which code enforces a rule → `ARCHITECTURE.md` → Modules
  - a choice between alternatives and its consequences → `DECISIONS.md`
  - a data constraint → `DATA-MODEL.md`
  - the words shown to users → the language files
  - CLAUDE.md → Domain invariants holds a selection of these rules,
    copied verbatim: a change is made here first, then there, in the same
    commit, by whoever changes the rule; only discovery adds a rule to
    the selection
- **Writer & moment:** discovery at Init (from the confirmed Vision &
  domain) and in Feature mode (new or changed terms, states, rules);
  `/do-step` step 5 only when the plan names a term or a rule a task
  introduces or changes — a rule found while building goes to
  `### Mid-step decisions`,
  and the closer writes it here if it is a law of the domain, to
  `DECISIONS.md` if it was a choice.

### docs/DESIGN.md
- **Reader & question:** the planner and the builder before any UI work:
  where the design is and how a screen's artboard is found; where the
  tokens live in code and how a token's name maps to a class; which shared
  components already exist. Read whole; then the screen's artboard and the
  feature's `FEATURE.md` → UI. Exists only when a design was made.
- **Type:** state — pointers and an index, no values, no description of a
  screen; no "as built", no "not built yet", no step.
- **Sections (fixed, this order):** Source · Tokens · Components
- **Record:**
  - Source — the design system (title, link) and its copy
    `docs/design-system/{namespace}/` with the date of the copy; one bullet
    per design artifact — its feature, title, link and copy
    `docs/features/{feature}/design/{name}/` with the date; then one bullet
    each: the files left in the artifacts because they are not
    text (fonts, raster images), by name — a step that needs one asks
    the user for it (`/plan-step` → a question); how an artboard is named and
    found (`{Screen}--{state}`, `--{viewport}` appended when a state is
    drawn for several viewports — the artboard's `title` in `canvas.json`);
    the rule for strings (the design brief for what it spells out, the
    artboard for the rest); that both copies are references — code is
    written from them, no file of theirs is copied into a code area
  - Tokens — two bullets: where the tokens live in code (before the
    design-system step: `docs/design-system/{namespace}/tokens.json`) and
    how a design name becomes a class or variable name
  - Components — one table row per shared component: Component · In code
    (tag or path) · Artboard where it is drawn
- **Not here → where:**
  - token values → the code (before it exists: the design system's
    `tokens.json`)
  - a component's variants, props and behaviour → the component's file
  - what a screen looks like → its artboard; which route and code it is →
    the feature's `FEATURE.md` → UI
  - a deliberate difference from an artboard → `DECISIONS.md` (and a note
    in that screen's `FEATURE.md` → UI row)
  - what is not designed → nowhere
  - an accessibility or visual check and its results → the verification
    guide of the step that made it
- **Writer & moment:** discovery writes Source at Init (and takes the first
  copies of both artifacts) and replaces it whenever it copies an artifact
  again; the design-system step writes Tokens and the first Components
  rows; `/do-step` step 5 adds a Components row in the commit that adds a
  shared component.

### docs/API.md
- **Reader & question:** the planner and the builder before touching an
  endpoint, a webhook handler, a call to an outside service or a payload:
  what the project exposes and who calls it; how each incoming webhook is
  verified, stored and answered; which calls the project makes to each
  service, with what, and what comes back — as the provider's
  documentation states it, not as remembered. Read by section: Rules
  whole, then the service or endpoint the step touches. Exists only when the project
  exposes an endpoint, receives a webhook or calls an API.
- **Type:** state — a row is replaced when the call or endpoint changes;
  no "as built", no spike, no step; a removed endpoint has no row.
- **Sections (fixed, this order):** Rules · Exposed · Incoming webhooks ·
  Consumed
- **Record:**
  - Rules — one sentence each: what every incoming webhook does (verify first,
    store under a unique key, answer at once, state moves forward only);
    the error format of own endpoints; how own endpoints are
    authenticated; tests never call a real service
  - Exposed — one table row per endpoint the project answers (a public API
    and the JSON endpoints its own pages call): Method & path · Caller ·
    Request (fields, a phrase) · Response (fields and status, a phrase) ·
    Errors (statuses)
  - Incoming webhooks — one `### {Service}` each: Path · Verified by (the
    scheme and the key's role, never its value) · Stored as (table and
    unique key) · In the request / queued (what is done where) · Answer
  - Consumed — one `### {Service}` each: Base address · Auth (header or
    scheme, the key's role) · a table: Call ·
    Sent · Answered · Errors — the statuses and fields the code maps,
    exactly as the provider spells them
- **Not here → where:**
  - the handler, client or interface class → the code; which module owns
    it → `ARCHITECTURE.md` → Integrations
  - the rules applied to a payload → the operation that applies them
  - the `.env` names, the webhook's configuration in the service →
    `THIRD-PARTY-SERVICES.md`
  - when a contract was verified and how → the step's verification guide,
    `WORKLOG.md`
  - a fallback provider, a choice of scheme → `DECISIONS.md`
  - the link to the provider's documentation → `ARCHITECTURE.md` →
    Integrations → Docs
- **Writer & moment:** `/do-step` step 5 in the commit that adds or changes
  a route, a webhook handler or a call in a client — a Consumed block from
  the provider's documentation (`ARCHITECTURE.md` → Integrations →
  Docs). Discovery writes Rules at Init when the project will have any of
  the three.

### docs/features/{feature}/FEATURE.md
- **Reader & question:** the planner at the start of every step of the
  feature, read whole: where the feature's boundary is; how it sits in the
  host and which shared code it depends on or exposes — what "touches
  shared code" means for it; which tables are its own; which screens it
  has and where each is in code; which sprints make it up. `/close-step`
  reads Roadmap for the next sprint.
- **Type:** state — rows are replaced; no sprint or step beside a screen
  or a module, no "built in"; Roadmap is the one place a sprint number
  belongs.
- **Sections (fixed, this order):** Scope · Fit · Structure · Data · UI ·
  Roadmap
- **Record:**
  - Scope — two to four sentences: what the feature does and for whom
  - Fit — four bullets: Code path (as the CLAUDE.md Features table has it) ·
    Entry point (the one registration in shared code) · Depends on (shared
    code it reads — a change there affects it; or "—") · Exposes (what
    other features may rely on — a change here "touches shared code")
  - Structure — one table row per internal module or directory: Module ·
    Where · Responsibility (a phrase); a single-feature project writes
    instead: "see ARCHITECTURE.md → Modules"
  - Data — the list of tables the feature owns, by name; no other feature
    writes them
  - UI — one table row per screen: Screen (named as its artboard) · Route ·
    In code · Artboard (folder and the `{Screen}--*` pattern — one row per
    screen, all its states); a feature with no screens has no section
  - Roadmap — one entry per sprint: number · goal (a phrase, as a user-visible outcome)
    · link to `sprints/SPRINT-N.md`; every entry has its file
- **Not here → where:**
  - a rule → `DOMAIN.md`; a data constraint → `DATA-MODEL.md`
  - a module other features use → `ARCHITECTURE.md` → Modules
  - tokens and shared components → `DESIGN.md`
  - the steps of a sprint → `sprints/SPRINT-N.md`; what was built when →
    `WORKLOG.md`
  - the states a screen was drawn in → the artboard files themselves
  - layouts, page shells → the code
- **Writer & moment:** discovery writes it whole (Init: `core`; Feature
  mode: the new feature) and rewrites Roadmap at a re-planning; `/do-step`
  step 5 adds a UI row in the commit that adds the screen's route, a
  Structure row with a new internal module, a Data entry with the migration
  (beside its DATA-MODEL block), and a Fit bullet when a task first reads or
  exposes shared code.

### docs/DECISIONS.md
- **Reader & question:** anyone before proposing an architecture, tooling,
  data or domain change: is it already decided, and why did the other way
  lose. The planner reads only the entries the sprint's Fixed decisions
  name, by title; `/question` and `/adhoc` search titles. Never read whole
  after discovery.
- **Type:** journal — one entry per decision, in date order, appended; an
  entry is never edited except for one bullet, `Superseded by:`, added when
  a later entry reverses it.
- **Sections:** none — entries only, under the file's header.
- **Record:** one entry =
  `## {YYYY-MM-DD} — {the decision in one sentence — the title is the index}`
  · Context (one or two sentences: what forced the choice)
  · Decision (one sentence, imperative)
  · Alternatives rejected (one sentence each: the alternative — why it lost;
    at least one, or it is not a decision)
  · Consequences (what this commits the project to: a rule, an invariant,
    a table, a flow — not a list of documents)
  · Supersedes (the older entry's title, only when reversing one).
  Written only when real alternatives were weighed and the loser is one a
  future agent might reasonably propose.
- **Not here → where:**
  - how a step built a decided thing → the code; the state documents
  - what a step changed and which documents it touched → `WORKLOG.md`
  - a mid-step choice with an obvious winner and no consequence beyond the
    branch → the plan's `### Mid-step decisions`, gone with the plan
  - the rule a decision produces → `DOMAIN.md`, `TECH-STACK.md`, the
    invariants — the entry says it commits to it; those files hold it
  - a decision's status today → the state documents; an entry is history
- **Writer & moment:** discovery (Phases B–C) writes the bulk; a step adds
  an entry only at `/close-step` step 2, from a plan question the user
  answered between alternatives or a `### Mid-step decisions` item that
  reversed or refined a fixed decision; `/fix-step` adds one when the cause
  was a decision. The closer applies the criterion; the builder never
  writes here.

### docs/WORKLOG.md
- **Reader & question:** a session at its start (CLAUDE.md core rule 6):
  where the project stands — feature, sprint, step, whether the sprint is
  on `main` — and what is still owed; the planner, for what changed at
  module level since the step it builds on. Read `## Open` and the newest
  entry; older entries only when looking for when something changed.
- **Type:** journal with one state section — `## Open` at the top is
  replaced by every writer; the entries below it are appended newest
  first and never edited.
- **Sections (fixed, this order):** Open · Entries
- **Record:**
  - Open — one bullet per item still owed, opening with the date of the
    entry that raised it: a guide item not yet verified, something pending
    a deploy or an outside account, a question carried forward. A bullet
    is removed the moment its item is closed. Items are never repeated
    inside entries.
  - Entry — heading `## {YYYY-MM-DD} — [{feature}] Sprint {N} Step {M} —
    {title}` (an ad-hoc: `[adhoc] [{feature}]`; a fix after the close:
    `… — fix: {what failed}`; the title is a phrase), then exactly four
    bullets:
    · Changed — what, at module or feature level: one or two sentences
    · Decisions — the titles of the `DECISIONS.md` entries added, or "—"
    · Shared code — what was touched and the consuming features, or "none"
    · Opened / closed — the Open bullets this step added or removed, by
      their text, or "—"
    The last step of a sprint ends its entry with the sentence
    `Sprint {N} complete — {sprint branch} merged into main.`
  Written for the agent, in the language of the documents — never in the
  plain language of the reports to the user.
- **Not here → where:**
  - the report to the user — plain language, as long as it needs → the
    chat reply of `/close-step`, built from the entry, never the entry
    itself
  - what a decision was and why → `DECISIONS.md`; what the code does →
    the code; test and line counts → git
  - what the user verified and how → the verification guide
  - a rule or a trap learned → `TECH-STACK.md`, `TESTING.md`
- **Writer & moment:** `/close-step` step 3 (the entry and `## Open`),
  `/fix-step` after a close, `/adhoc` at its close. Nothing else writes
  here; a step in flight has no entry.

### docs/INCIDENTS.md
- **Reader & question:** the playbook's author in an analysis chat, never a
  step: what went wrong in this project that the playbook should have
  prevented — as events, so that the analysis is made there, not here.
  No step reads this file.
- **Type:** journal — newest first; the agent writes `[new]`, only the user
  sets `[closed]`; an entry is never edited otherwise.
- **Sections:** none — entries only, under the file's header.
- **Record:** one entry per event of one of four classes, and only these:
  · `loss` — an irreversible action, or one outside the step's branch, ran
    without the user's explicit yes before it, or caused a loss despite it
  · `intervention` — the user stopped or corrected an action of the agent
  · `blocked` — the step stopped because two playbook rules cannot both
    hold, or a command requires what the project does not have
  · `repeat` — a second `/fix-step` on the same step, or the same failure
    in two consecutive steps
  Heading `## [new] {YYYY-MM-DD} · {class} — {what happened, a phrase}`,
  then exactly: Where (command and step) · Event (the facts — the command
  or action and its effect, two or three sentences, no interpretation) ·
  Cost (what was lost, redone or waited for — one sentence) · Rule involved
  (file → section that should have prevented it, or "none found") · Log
  (the event's local time, `{HH:MM}` from `date +%H:%M` — the key into the
  action log file of the heading's date, `.claude/log/{YYYY-MM-DD}.jsonl`;
  "—" when the time is not known, as for a `repeat` found at close).
  No proposal of a change: that is the analysis chat's work.
- **Not here → where:**
  - a choice made while building → the plan's `### Mid-step decisions`
  - a rule for code or tests learned → `TECH-STACK.md`, `TESTING.md`
  - a plan that proved wrong against the code → the plan's Checks and the
    chat — not an incident unless it blocked the step
  - a question or an inconvenience → nowhere
  - what the agent would change in the playbook → the analysis chat
  - how long something took, what was read or run and how often → the
    action log, `.claude/log/`, which the hooks write and the agent never
    does; a slow step is not an incident
- **Writer & moment:** `/do-step`, `/fix-step` and `/adhoc` at the moment
  of a loss, an intervention or a block — the builder's one permitted
  write outside the plan, because the trigger is an event, not a judgement;
  `/close-step` and `/fix-step` for a repeat, since only they see the
  history.

### docs/features/{feature}/verification/sprint-N/step-M.md
- **Reader & question:** the user, on the task branch, before
  `/close-step` — the one document written for a person: how to verify
  the step, completely, and what the agent decided on the user's behalf.
  The measure of the guide is that a person who has not seen the code
  follows it without asking a single question: exact addresses, exact
  strings, exact data, the exact state to expect. A step reads it twice
  only: `/fix-step` reads the failed item, `/close-step` checks it exists.
- **Type:** state of one step — written once at the hand-over; `/fix-step`
  before the close adds a fix note under the failed item; after the close
  never touched. In plain language (CLAUDE.md → Project profile →
  Verification): no class, method or test name outside Covered by tests.
  No budget of any kind: the guide is as long as the walk needs.
- **Sections (fixed, this order):** head · What you need · What was built
  · What differs from the design and the sprint's text · What I saw myself
  · Items · Covered by tests · Done
- **Record:**
  - head — `# Verification — {feature}, Sprint {N}, Step {M}: {title}` and
    one sentence: written by `/do-step` on {date}; followed on the task
    branch `{branch}`, before `/close-step`
  - What you need — bullets: the accounts and whose passwords, the
    windows (an ordinary one, a private one), files, a tunnel, the minutes
    it takes; what this computer holds already and what it lacks —
    migrations waiting, data an item relies on — read from the machine,
    never assumed
  - What was built — what the user can now see or do, in the words of the
    screens, never how it is made; as many sentences as the step has
    things to see
  - What differs — the sentence "Say so if you want any of these
    changed.", then one bullet per choice the design, the sprint and the
    documents did not fix: a plan question resolved as recommended, a
    `### Mid-step decisions` item, a deviation from the plan, the agent's
    own words on a screen or in a letter; nothing → "Nothing."
  - What I saw myself — what this session opened in a browser, a mailbox
    or a terminal, and what it could not open
  - Items — every item of the step's Verification (manual), in full, in
    its order, whether or not a test also proves it: one `### {k}. {screen
    or flow}` each; under it numbered bullets, one action each, every one
    ending in `**Must show:**` and the exact state — never two actions or
    two results in one bullet; item 1 starts from the checked-out branch
    and the commands that make the step runnable; a negative check (what
    must not be possible) wherever the step has one; an artefact not
    verifiable locally gets its item marked so, with the run that
    verifies it
  - Covered by tests — the check command's result in one sentence
    (`{n} tests`), then one bullet per item above that a test also
    proves: the rule in the words of the screen, the test's name in
    parentheses — information for the user, who decides what to skip
  - Done — every item holds → `/close-step`; one fails → what was done,
    expected and got, or `/fix-step <what failed>`; the fix lands on this
    branch
- **Not here → where:**
  - how it is made → the code; what a test asserts → the test file
  - why a choice was made → the plan's `### Mid-step decisions` (quoted
    here in plain words) and, if weighed, `DECISIONS.md`
  - the report of the step → the chat reply of `/close-step`
- **Writer & moment:** `/do-step` step 8, at the hand-over, once;
  `/fix-step` before the close — a fix note under the item that failed.
  `/close-step` reads, never writes.

### docs/DISCOVERY-NOTES.md (discovery only)
- **Reader & question:** the next Init-discovery chat: what the chats
  before it confirmed and settled, in decision order, so no topic is
  reopened. No step ever sees it: the file exists only between the first
  discovery chat and assembly, which converts it and removes it. Its
  working rules are DISCOVERY.md → Multi-chat discovery.
- **Type:** journal with one state section — Vision & domain at the top,
  replaced only if the vision changes; entries appended at the bottom
  (reading order = decision order). An entry grows while its topic chat
  runs and is never edited after it.
- **Sections (fixed, this order):** Vision & domain · Entries
- **Record:**
  - Vision & domain — the confirmed Phase A summary, verbatim
  - Entry — `## {YYYY-MM-DD} — {Topic} (chat: {name})`, then exactly:
    · Context — what forced the choices; becomes the Context of the
      DECISIONS entries
    · Decisions — one sentence each, imperative; only where real
      alternatives were weighed (the `DECISIONS.md` criterion); each
      becomes one DECISIONS entry at assembly
    · Alternatives rejected — one sentence each: the alternative — why it
      lost; or "—"
    · Facts — one sentence each: what is not a choice but must be
      recorded — a term, a rule of the domain, a lifecycle, a constraint —
      each with its destination (`DOMAIN.md` → Glossary / Lifecycles /
      Rules; `ARCHITECTURE.md` → Overview constraints; …); or "—"
    · Records — anything settled that is larger than a sentence: a table
      block, a Flows block, a Platform or Stack row, a Lifecycles table,
      the `DESIGN.md` → Source record — each already in the shape of its
      destination's Contract, headed `{file} → {section}`; as many as the
      topic needs, no limit of size or count; placed as they are at
      assembly; or "—"
    · Open questions — one sentence each, or "—"
    · Affects — the artifacts and sections this entry feeds:
      `ARCHITECTURE.md` (Modules / Platform / Flows / Jobs / Integrations /
      Environments & deploy) · `DATA-MODEL.md` · `TECH-STACK.md` (Stack /
      ANTI-PATTERNS / CONVENTIONS) · `TESTING.md` (Layers / Run) ·
      `DOMAIN.md` (Glossary / Lifecycles / Rules / Roles) ·
      `API.md` · `DESIGN.md` → Source · CLAUDE.md invariants ·
      `FEATURE.md` / SPRINT-1 scope
- **Not here → where:**
  - an artifact written before assembly → nowhere; its content is a Record
    of the entry (the design folder's files are the exception)
  - anything after assembly → the artifacts; the file is removed
- **Writer & moment:** every Init-discovery chat, at the end of its topic;
  the assembly chat converts and removes it (DISCOVERY.md Phase C
  checklist).
