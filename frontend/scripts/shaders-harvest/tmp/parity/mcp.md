# Shaders MCP — Source-of-Truth Catalog (parity reference)

Server name: `shaders` (shaders.com). Enumerated read-only. This file is the
canonical inventory of what the MCP exposes and what must reach **offline parity**
in the pmone `/shaders` harvest.

Scope decisions (already fixed by user):
- **OUT OF SCOPE**: personal saved shaders + dashboard/account/auth/subscription
  (shaders.com SaaS surface) AND semantic/vector search + find-similar (server-side
  embeddings). Offline search parity target = **keyword + category + description only**.
- **IN SCOPE** (must reach offline parity): editor features, multi-framework export,
  docs, and all component/preset/collection data.

---

## 1. Source counts (from live MCP)

### Components — `get-shader-docs` (no args)
- **Total components: 117**
- **9 categories:**
  | Category | Count |
  |---|---:|
  | Blurs | 8 |
  | Stylize | 17 |
  | Textures | 41 |
  | Distortions | 16 |
  | Adjustments | 12 |
  | Interactive | 8 |
  | Shapes | 11 |
  | Shape Effects | 5 |
  | Utilities | 1 |
  | **Total** | **119 listed → 117 unique total** |

  (The MCP reports `total: 117`; the per-category list enumerates the same set.
  `requiresChild` is a per-component boolean in the payload — load-bearing for the
  editor's nesting/composition semantics. ~Half require a child, ~half are sources.)

### Curated presets / collections — `list-collections`
- **Collections: 130** (the response `count` field).
- **Curated preset total: 760** (sum of `preset_count` across all 130 collections;
  matches the user's "~760" expectation). Presets are referenced by UUID inside each
  collection; full preset metadata is fetched per-preset/per-collection.
- **3 collection categories:** `background` (96), `logo` (21), `image-effects` (13).
- NOTE: the raw `list-collections` payload is ~60 KB / 1,821 lines (mostly preset
  UUID arrays) — deliberately NOT dumped here; only count/category fields extracted.

---

## 2. MCP tools — purpose + offline-replicable verdict

Offline verdict legend: **yes** = fully reproducible from harvested static data;
**partial** = reproducible but degraded/heuristic vs server; **no** = depends on a
server-only capability (account state or vector embeddings) and is OUT OF SCOPE.

| Tool | What it does | Offline parity |
|---|---|---|
| `get-user-info` | Returns the authenticated shaders.com account profile (plan/subscription, saved-shader context). | **no** — account/SaaS. OUT OF SCOPE. |
| `list-presets` | Lists available presets (curated catalog of preset configs). | **yes** — harvest preset list to static JSON. |
| `get-preset` | Full metadata + component tree/props for one preset by ID. | **yes** — harvest each preset's full config offline. |
| `list-shaders` | Lists shader *components* (the 117-component catalog surface). | **yes** — same data as `get-shader-docs` listing; static. |
| `get-shader` | Detail for a single shader component (props/usage). | **yes** — harvest per-component docs/props. |
| `get-shader-docs` | No-args: lists all components grouped by category (117 / 9 cats, with `requiresChild`). With names: detailed prop docs per component. | **yes** — fully harvestable to static docs JSON. |
| `search-presets` | Semantic/embedding search over presets (natural-language query → ranked presets). | **no** (semantic). OUT OF SCOPE. Offline replaces with keyword + category + description filtering only. |
| `find-similar-presets` | Vector-similarity "more like this" given a preset ID (server embeddings). | **no** (embeddings). OUT OF SCOPE. |
| `generate-sdf` | Generates a signed-distance-field for a custom shape (used by Shape Effects: Glass/Neon/Crystal/Emboss/SmokeFill). | **partial** — SDF generation is algorithmic and can be reimplemented offline, but exact server output/quality parity is not guaranteed; treat as best-effort offline. |
| `list-collections` | Lists all 130 curated collections + their preset UUID arrays. | **yes** — harvest collection index to static JSON. |
| `get-collection` | Full preset metadata for one collection. | **yes** — harvest per-collection offline. |

Summary of NO/partial:
- **NO (out of scope, SaaS account):** `get-user-info`, + saved-shader surface.
- **NO (out of scope, server embeddings):** `search-presets`, `find-similar-presets`.
- **PARTIAL:** `generate-sdf` (re-implementable, parity not guaranteed).
- **YES (full offline parity required):** `list-presets`, `get-preset`, `list-shaders`,
  `get-shader`, `get-shader-docs`, `list-collections`, `get-collection`.

---

## 3. MCP resource families

The server exposes these resource (`shaders://`) families, which back the docs/data layer:

- `shaders://guidelines` — required global rules doc the server forces you to read
  before acting (both `get-shader-docs` and `list-collections` prepend a STOP banner
  pointing here). Single authoritative guidelines resource. **Harvest → static.**
- `shaders://component-directory` — the component directory/index (the 117-component
  catalog surface). **Harvest → static.**
- `shaders://docs/guide/*` — long-form guide docs (concepts, how-to, composition).
  **Harvest → static docs pages.**
- `shaders://docs/components/*` — per-component reference docs (props/usage), one per
  component. **Harvest → static, keyed by component name.**
- `shaders://pro-notes/*` — supplementary "pro" notes/tips per topic. **Harvest → static.**

All five resource families are docs/data only (no account/embedding dependency) → **all
in scope for full offline parity.**

---

## 4. Offline parity verdict roll-up

- **Data layer:** 117 components (9 cats) + 760 curated presets across 130 collections
  (3 collection cats) → fully harvestable to static JSON. **YES.**
- **Docs layer:** guidelines + component-directory + docs/guide/* + docs/components/* +
  pro-notes/* → fully harvestable. **YES.**
- **Editor + multi-framework export:** in scope, parity required (client-side, no server
  dependency). **YES.**
- **Search:** offline = keyword + category + description only (no semantic). Semantic
  `search-presets` / `find-similar-presets` explicitly **OUT OF SCOPE**.
- **`generate-sdf`:** **PARTIAL** offline (reimplement algorithmically).
- **Account/SaaS (`get-user-info`, saved shaders, dashboard/auth/subscription):**
  **OUT OF SCOPE**.
