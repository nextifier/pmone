# Shaders MCP Guidelines

**Read this document before taking any action.** Every tool response in this MCP will remind you to read this. These guidelines ensure you make the right decisions quickly and produce great-looking shader effects with minimal back-and-forth.

---

## What is Shaders?

Shaders is a component library for Vue, React, Svelte, Solid and vanilla JS that lets developers build declarative WebGPU visual effects in the browser. Effects are composed by nesting components inside a `<Shader>` tag. Components are either **Generators** (they draw pixels to the screen) or **Effects** (they consume and transform the output of their children).

See `shaders://component-directory` for a full categorized list of all available components.

---

## Available Resources

You have access to three tiers of documentation:

| Resource | URI Pattern | When to use                                                                                                                                                                               |
|---|---|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Pro Notes** | `shaders://pro-notes/{slug}` | Always check first — these are expert patterns not in the public docs                                                                                                                     |
| **Guides** | `shaders://docs/guide/{framework}/{slug}` | Core concepts: composing, masking, transforms, layout, performance                                                                                                                        |
| **Component Directory** | `shaders://component-directory` | Overview of all components — read before adding, removing, or restructuring components. |
| **Component Docs** | `shaders://docs/components/{name}` | Props reference for a specific component                                                                                                                                                  |

List all available Pro Notes: `shaders://pro-notes/{slug}` (use the resource list)
List all available guides: `shaders://docs/guide/{+slug}` (use the resource list)

---

## Pro Notes: Your Most Important Resource

Pro Notes contain expert-level patterns, tips and tricks that go well beyond the public documentation. They encode the difference between a shader that works and one that looks excellent.

**Rule: Before writing any shader code, list the available Pro Notes and check whether any apply to your task.** It is always worth taking the extra time to read a relevant Pro Note. A few seconds of reading saves multiple rounds of revision.

The component directory (`shaders://component-directory`) also surfaces Pro Note links per-component when available.

**Quick reference — Pro Notes by scenario:**
- Hero section / masking the shader into the UI → `shaders://pro-notes/hero-section-masking`
- Cursor interactions and dynamic prop mapping → `shaders://pro-notes/interactions`
- Full dynamic prop mapping reference (all modes, map mode deep-dive) → `shaders://pro-notes/dynamic-prop-mapping`
- Grain, texture, and animated background choices → `shaders://pro-notes/finishing-touches`
- Glass, Neon, Emboss, Crystal — placement and sizing → `shaders://pro-notes/shape-effects-placement`
- Images, video, and webcam with applied effects → `shaders://pro-notes/media-effects`
- Color spaces and gradient quality → `shaders://pro-notes/color-spaces`
- Layer ordering and transparent backgrounds → `shaders://pro-notes/composition`

---

## Required Reading Flows

Follow these flows before writing code:

### Installing a preset
1. Call `get-preset` to retrieve the code
2. Check the `tag_guidance` and `related_docs` fields in the response if available
3. List Pro Notes — read any that match the use case or might be relevant
4. Install the preset code

### Building a shader from scratch
1. Read `shaders://component-directory` to orient yourself on available components
2. Read `shaders://docs/guide/{framework}/composing-effects` — this is required
3. List Pro Notes and read any that might be relevant.
4. Once you've decided which components to use, read those component docs and related Pro Notes
5. Write the shader

### Adding or removing components from an existing shader
1. Read `shaders://component-directory` — understand `requiresChild` before making structural changes
2. Read any Pro Notes linked to the components you're modifying
3. Make the change

### Masking the shader to fit into the UI
1. Read `shaders://pro-notes/hero-section-masking` — required, every time
2. Do not use CSS masking (`mask-image`, `clip-path`) — in-shader masking is more performant and produces better results

### Modifying blend modes or transforms
1. Read `shaders://docs/guide/{framework}/blending-masking` or `shaders://docs/guide/{framework}/transforms` as appropriate

---

## Code Principles

### Use a single `<Shader>` tag
Place one `<Shader>` tag per effect area. Never stack two `<Shader>` tags on top of each other to achieve layering — compose effects as children within a single shader instead. Multiple `<Shader>` instances are only appropriate when effects are in completely separate, non-overlapping regions.

### Positioning: component vs shader
When asked to move or position an element:
- **Move a specific component within the shader** (e.g. shift a gradient to the left) → adjust position props on the individual component
- **Move the entire shader on the page** (e.g. place the shader in the top half of the screen) → use normal CSS/layout on the `<Shader>` element itself

Default to keeping `<Shader>` as a full-canvas background and positioning elements inside it. Only resize or reposition the `<Shader>` element when the user is explicitly placing it in a constrained region.

### Understand `requiresChild` and how children work

Components with `requiresChild: true` are **Effects**. They operate in one of two modes depending on whether they have explicit nested children:

**With explicit children** — the effect applies only to those nested components:
```jsx
<Shader>
  <LinearGradient />  {/* unaffected */}
  <AngularBlur>
    <Grid />         {/* only Grid is blurred */}
  </AngularBlur>
</Shader>
```

**Without children** — the renderer falls back to the effect's preceding siblings in the stack, applying the effect to all of them. This makes it easy to style an entire composition by adding an effect at the end:
```jsx
<Shader>
  <LinearGradient />
  <Grid />
  <AngularBlur />   {/* no children → applies to everything above it */}
</Shader>
```

This sibling-fallback pattern is the idiomatic way to add stylization effects like `FilmGrain`, `Ascii`, or `Dither` to a full composition. Always check `shaders://component-directory` before restructuring to understand each component's role.

### Do not fight the GPU
Avoid combining shader effects with heavy CSS filters (`blur`, `backdrop-filter`) on the same element. Let the shader do the work — there are Blur, Distortion, and other components that run entirely on the GPU and produce better results.

### RTT (Render-to-Texture) effects are expensive — use sparingly
Blur and Distortion components require a render-to-texture pass, which is significantly more GPU-intensive than standard compositing. Avoid stacking multiple Blur or Distortion components in the same shader. One or two is fine; more than that and you should consider whether a simpler approach achieves the same result.

### Never use `DOMTexture` unless explicitly requested
`DOMTexture` renders live HTML into the shader pipeline. It is experimental, non-standard, and **only works in Chrome Canary with a specific flag enabled** (`chrome://flags/#canvas-draw-element`). It must not be used in production. If a user explicitly asks for it or asks about "html-in-canvas", inform them of the Chrome Canary requirement before proceeding. In 99% of cases, `DOMTexture` is not the right tool — use an `ImageTexture` or `VideoTexture` instead.

---

## Component Directory

For a full, categorized list of all components with their roles and any linked Pro Notes:

→ Read `shaders://component-directory`
