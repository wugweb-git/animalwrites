# Wugweb — Final Repository, Git, Packaging & WordPress Delivery Architecture

**Source boundary:** Wugweb Notion records, architecture discussions, and repository contracts.  
**Purpose:** Establish the canonical monorepo, product boundaries, Git workflow, build/package/release process, WordPress Playground previews, WordPress.org readiness, and the native-versus-plugin approach.

---

# 1. Repository Decision

## 1.1 One Wugweb Monorepo
The canonical development direction is one Git repository (`wugweb-git/kits`) for:
- WordPress plugins (`plugins/*`)
- Kits by Wugweb theme (`themes/kits-by-wugweb`)
- Design system and tokens (`packages/design-tokens`, `packages/design-system`)
- Shared packages (`packages/*`)
- Skills (`skills/*`)
- Agent instructions (`agents/*`)
- Documentation (`docs/*`)
- Build and release tooling (`tools/*`)
- Marketplace and distribution material (`marketplace/*`)
- GitHub Actions workflows (`.github/workflows/*`)

Each product retains its own independent code boundary, namespace, dependencies, version, build output, tests, and release process.

**One repository does not mean one product or one release.**

## 1.2 Repository Boundaries
The consolidated repository houses the development source of truth. Existing historical snapshots (such as legacy Brand OS foundation files) remain archived and preserved, not overwritten.

---

# 2. Final Repository Structure

```text
wugweb/ (https://github.com/wugweb-git/kits)
│
├── .github/
│   ├── workflows/
│   │   ├── ci.yml
│   │   ├── lint.yml
│   │   ├── test.yml
│   │   ├── build-theme.yml
│   │   ├── build-plugins.yml
│   │   ├── plugin-check.yml
│   │   ├── playground-preview.yml
│   │   └── release.yml
│   └── CODEOWNERS
│
├── plugins/
│   ├── kits-core/
│   │   ├── kits-core.php
│   │   ├── includes/
│   │   ├── src/
│   │   ├── build/
│   │   ├── assets/
│   │   ├── languages/
│   │   ├── readme.txt
│   │   ├── uninstall.php
│   │   └── composer.json
│   ├── sourceweb/
│   ├── insightweb/
│   ├── rankweb/
│   ├── directoryweb/
│   ├── mailweb/
│   └── planweb/
│
├── themes/
│   └── kits-by-wugweb/
│       ├── style.css
│       ├── theme.json
│       ├── functions.php
│       ├── templates/
│       ├── parts/
│       ├── patterns/
│       ├── styles/
│       ├── assets/
│       ├── tokens/
│       │   └── tokens.json
│       └── languages/
│
├── packages/
│   ├── design-tokens/
│   ├── design-system/
│   ├── shared-php/
│   ├── shared-js/
│   ├── admin-shell/
│   ├── rankweb-core/
│   ├── rankweb-shell/
│   ├── sourceweb-shell/
│   ├── insightweb-shell/
│   ├── mailweb-shell/
│   ├── planweb-shell/
│   └── automation-contracts/
│
├── skills/
│   ├── wordpress/
│   ├── design-system/
│   ├── frontend/
│   ├── accessibility/
│   ├── testing/
│   ├── security/
│   └── release/
│
├── agents/
│   ├── engineering/
│   ├── design/
│   ├── qa/
│   └── documentation/
│
├── tools/
│   ├── build/
│   ├── package/
│   ├── release/
│   ├── validate/
│   ├── tokens/
│   └── playground/
│
├── docs/
│   ├── architecture/
│   ├── decisions/
│   ├── design-system/
│   ├── plugins/
│   ├── theme/
│   ├── git/
│   ├── marketplace/
│   ├── deployment/
│   ├── testing/
│   └── release/
│
├── marketplace/
│   ├── theme/
│   ├── plugins/
│   ├── screenshots/
│   ├── banners/
│   ├── icons/
│   └── submission/
│
├── tests/
│   ├── integration/
│   ├── contracts/
│   └── shared/
│
├── package.json
├── pnpm-workspace.yaml
├── composer.json
└── README.md
```

## 2.1 Directory Responsibilities
| Directory | Responsibility |
|---|---|
| `plugins/` | Independently buildable WordPress plugins |
| `themes/` | Kits by Wugweb and its theme assets |
| `packages/` | Reusable code, shared contracts, and namespaced shells |
| `skills/` | Reusable task-specific skills |
| `agents/` | Agent instructions and role-specific workflows |
| `tools/` | Build, validation, packaging, and release scripts |
| `docs/` | Architecture, product, and engineering documentation |
| `marketplace/` | WordPress.org and commercial distribution assets |
| `tests/` | Shared, integration, and contract tests |
| `.github/workflows/` | CI, PR previews, packaging, and release automation |

## 2.2 Important Package & Bundling Rules
1. **Bundled-Code Model**: Shared code is bundled directly into each public plugin edition at build time. A public plugin must **never** depend at runtime on another Wugweb plugin simply because both live in the same monorepo.
2. **Namespaced Shell Per Plugin**:
   * `rankweb-shell`, `planweb-shell`, `sourceweb-shell`, `insightweb-shell`, `mailweb-shell`.
   * **Rule**: Do not use a global `kits-shell` handle across independently installed plugins. Each plugin bundles its own namespaced copy, injecting plugin-specific branding, endpoints, and navigation via configuration.
3. **`rankweb-core`**: Contains the 9 core SEO engine classes: Linker, Anchor, Silo, Link Audit, Video, Schema, Bulk, Redirects, Agent. Collision guards ensure multiple bundled copies coexist safely.
4. **External Services Registry**: Outbound network requests are centralized through `register_external_service(id, args)` and `remote_request(url, args, method)` using `wp_safe_remote_*`. All hosts must be disclosed in `== External services ==` in `readme.txt`.

---

# 3. GitHub & Git Workflow

## 3.1 Review & Merge Sequence
`Branch → PR → Review Gate → Owner Merge → Ship`

The review gate requires:
1. Code review using the code-review skill.
2. Review comments with file/line, severity, and fix.
3. Plugin audit when a plugin changes (`wp-plugin-audit`).
4. PHPCS on changed files.
5. Deploy check green.
6. Plugin Check for changed plugins.
7. Smoke test passing.
8. Codex review comments answered, fixed, or given a valid technical reason.
9. Review section in the PR description.
10. **Owner approval and merge** (no auto-merge).

## 3.2 Git Worktrees & Attribution
* **Worktrees**: Each session works in its own Git worktree for larger changes. Clean checkouts maintained; worktrees removed by exact path after merge.
* **Commit Attribution**: Must strictly use:
  ```text
  Co-Authored-By: Wugweb <admin@wugweb.com>
  ```
  *(No Claude or generic AI attribution lines).*

---

# 4. Build, ZIP Packaging & Distribution

## 4.1 Independent Packaging
Every plugin and the theme must produce its own independent, standalone distributable artifact:
* `dist/rankweb.zip`
* `dist/kits-core.zip`
* `dist/mailweb.zip`
* `dist/kits-by-wugweb.zip`

The monorepo **never** produces one combined WordPress ZIP containing every plugin.

## 4.2 Release Isolation
A commit or change to RankWeb must not automatically trigger a release of Kits Theme, MailWeb, or another product. Each product maintains its own:
- Independent version number
- Independent changelog
- Independent ZIP build
- Independent release decision and release tag

---

# 5. GitHub Actions & WordPress Playground

## 5.1 WordPress Playground PR Previews
Using `WordPress/action-wp-playground-pr-preview`:
1. Developer opens PR.
2. GitHub Actions packages the changed plugin/theme ZIPs.
3. A temporary, disposable WordPress Playground instance is initialized in the browser.
4. Blueprint configures the environment and activates the built ZIPs.
5. Reviewers inspect live block templates, patterns, and admin screens before merge.

---

# 6. WordPress.org Readiness Norms
Every public plugin edition follows the official WordPress Plugin Developer Handbook:
* **GPL License Only**: No trialware, no locked features, no hosted plugin bundling.
* **No Update URI**: `Update URI` removed for `.org` editions.
* **Requires Plugins**: Used strictly for dependencies hosted directly on WordPress.org.
* **External Services**: Full disclosure in `readme.txt` with privacy links and terms.
* **Naming**: Unique plugin-specific prefix (min 4 characters). Never use `aw_` or `aw-` in new code.
* **Security**: Nonces, capability checks, `wp_unslash()`, data sanitization, output escaping, and `$wpdb->prepare()`.

---

# 7. Native WordPress vs. Kits Core

| Layer | Responsibility | What Belongs Here |
|---|---|---|
| **Native WordPress (FSE)** | Visual styling, layout, content editing | Site Editor, Global Styles, Theme variations, Templates, Template Parts, Patterns, Typography/Palette presets. |
| **Kits Core** | Branded management & companion tools | Theme Hub, Page Options, Design-system index, Settings transfer (JSON export/import), Minimal Admin Shell, shared plugin navigation. |
| **Companion Plugins** | Domain-specific application logic | DirectoryWeb (Listings), RankWeb (SEO), MailWeb (Hostinger SDK & email), SourceWeb (Feeds), InsightWeb (Analytics), PlanWeb (Calendar). |

---

# 8. Admin UI Standard (Minimal UI)
* **Design Philosophy**: Modeled structurally on **Google Site Kit** (plugin dashboards) and **Uncode** (theme management).
* **Visual Palette**: Strict greyscale with a rare yellow accent (`#f59e0b` / `--kit-color-accent`).
* **Visual Data**: Inline SVG `MiniChart` primitive (no heavy Chart.js or external library dependencies).
* **Data Presentation**: Sortable, filterable tables with bulk actions, clear columns, pagination, and empty/loading states.

---

# 9. Product Naming Hierarchy

| Product Family | Full Name | Half Name | Initials |
|---|---|---|---|
| Standalone Plugin | SourceWeb | Source | SW |
| Standalone Plugin | InsightWeb | Insight | IW |
| Standalone Plugin | RankWeb | Rank | RW |
| Standalone Plugin | DirectoryWeb | Directory | DW |
| Standalone Plugin | MailWeb | Mail | MW |
| Standalone Plugin | PlanWeb | Plan | PW |
| Kits Suite | Kits Core | Core | KC |
| Kits Suite | Kits by Wugweb | Kits Theme | KT |

---

# 10. Phased Implementation Roadmap

* **Phase 0 — Inventory**: Audit existing repos, branches, and commits; preserve historical assets.
* **Phase 1 — Monorepo Foundation**: Establish root workspace, directories, README, CODEOWNERS, manifests.
* **Phase 2 — Shared Foundations**: `packages/design-tokens`, `packages/admin-shell`, Token Foundry tools.
* **Phase 3 — Product Packaging**: Independent ZIP builds, namespaced shells, bundled-code model.
* **Phase 4 — CI & Previews**: GitHub Actions for linting, Plugin Check, Playground previews.
* **Phase 5 — Marketplace Readiness**: Banners, icons, screenshots, POT files, external services disclosures.
