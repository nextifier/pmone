# Shaders.com Browser Interface Catalog

## 1. Dashboard (shaders.com/dashboard)

### Layout
- Top navigation bar with Shaders logo, links to presets/docs, user menu, copy share link button
- Main heading "Dashboard Pro" with Pro badge
- Two prominent CTA buttons: "Design your own" and "Explore presets"

### Content Sections
- **Saved Shaders Grid**: Shows recently saved user shaders with thumbnails, titles, and timestamps (27m ago, 4d ago, etc.)
  - Includes: Arrow Skies 3, Gradient Grid 2, Geogrid 6, Holographic Waves 1 (multiple), Untitled
- **Pro Presets Section**: "Find your perfect shader with 0 Pro presets" + Explore Presets CTA
- **Learning/Resources Section**:
  - "Take a walkthrough (10m)" video button
  - "Read the docs" link
  - "Join the community" Discord link

### Footer
- Standard footer with links to Product (Home, Dashboard, MCP, Framer, Support), Presets category links, Docs links, Legal, and social links

---

## 2. Presets Gallery (shaders.com/presets)

### Top Section
- Main heading: "What can we help you find today?"
- Search input: "Search presets…" (textbox)
- **Suggested tags buttons**: dither effects, ocean blue, warm gradient, ascii effects, flowing liquid, dark ambient, stunning glass, holographic

### Category Navigation
- Browse by category section with counts:
  - **Backgrounds**: 570+
  - **Logo Shaders**: 150+
  - **Image Effects**: 30+
  - **Recently Added** (anchor link)

### Content Structure
1. **Featured Collections** (carousel with prev/next buttons per collection):
   - Undertones, Mercury, Pixel Beams, Fluid Chrome, Studio Glass, Watercolor on Paper, Smokescreen, Gradient Grid

2. **Backgrounds Section** (570+):
   - Description: "Bring depth and motion to backgrounds, cards, and landing pages"
   - Collections with carousels: Synthesis, Geogrid, Floating Glass, Radial Overlap, Ribbon Flows, Flowing Dots, Hex Path, Rolling Shadows, Crystal Ball
   - Link: "Explore backgrounds"

3. **Logo Shaders Section** (150+):
   - Description: "Turn static logos into animated, reactive brand visuals"
   - Collections with carousels: Smokey Logo, Soft Prism, Chroma Chrome, Embossed Relief, Magnified Pixels, Frosted Glass, Backlit Smoke, Broken Panel, Glass Halo
   - Link: "Explore logo shaders"

4. **Image Effects Section** (30+):
   - Description: "Apply real-time shader effects to photos, media, and artwork"
   - Collections: Damaged Sensor, Pixel Reveal, Dithered Reveal, Impression Trail, Tinted Lenses, Glitch Trail, Tropical Ascii Cam, Dither Cam, Broken Track
   - Note: Some effects without "Explore Collection" button (linked directly)

5. **Recently Added / New Collections**:
   - **Tag/Category Links**: All, Gradient, Vibrant, Geometric, Abstract, Dark, Minimal, Retro, Dither, ASCII, Subtle
   - Collections grid: Liquid Lenses, Pistons, Radial Overlap, Collapsing Grid, Dithered Reveal, Spectral Bloom, Popping Bubbles, Tinted Lenses, Damaged Sensor, Onward, Broken Panel, Retro Pop, Stretched Chevrons, Holofoil, Afternoon Sunlight, Hex Path, Soft Register, Mercury, Magnify, Cracked Crystal, Focal Plane, Container Ship, Pixel Reveal, Impression Trail, + "Show more" button

### Footer
Same as dashboard with welcome-back message for logged-in users

---

## 3. Design Editor (shaders.com/design-editor/4726121)

### Top Bar Controls
- Left section:
  - Shaders logo (home link)
  - Hamburger menu button
- Center:
  - Share link button ("Copy share link")
  - User menu (profile avatar)
  - Shader name display ("Gradient Grid 2")
- Right section:
  - **Color Space Buttons**: "P3 Linear", "Linear", "Standard" (toggle buttons)
  - Additional control buttons (icon-based)

### Left Panel - Layers & Properties

#### Layers List
- Tree-based layer structure showing component hierarchy
- Example: `<FlowingGradient>`, `<Grid>`, `<FilmGrain>`
- Each layer is clickable to select and show its properties

#### Selected Layer Properties Panel (changes based on selected layer)

**For FlowingGradient:**
- **COLORS section**:
  - Color A (swatch + hex input, e.g., "#fffd29")
  - Color B (swatch + hex input, e.g., "#ff8964")
  - Color C (swatch + hex input, e.g., "#ff54a0")
  - Color D (swatch + hex input, e.g., "#e7000b")
  - Color Space dropdown (e.g., "OKLAB")

- **EFFECT section**:
  - Speed (slider + numeric input, e.g., 1)
  - Distortion (slider + numeric input + "Dynamic" toggle, e.g., 0.2)
  - Seed (slider + numeric input + "Dynamic" toggle, e.g., 32)

- **LAYER TRANSFORM section**:
  - Offset X (slider + numeric input, e.g., 0)
  - Offset Y (slider + numeric input, e.g., 0)
  - Rotation (slider + numeric input, e.g., 0)
  - Scale (slider + numeric input, e.g., 1)
  - Anchor X (slider + numeric input, e.g., 0.5)
  - Anchor Y (slider + numeric input, e.g., 0.5)

- **Additional Controls**:
  - Edges section with blend mode dropdown (e.g., "Transparent")
  - Per-property "Dynamic" toggle for reactive/animated properties
  - Opacity control (implied in Edges)

**For Grid (example properties):**
- Color (swatch + hex, e.g., "#000000" = black)
- EFFECT section:
  - Cells (slider + numeric, e.g., 30) + "Dynamic" toggle
  - Thickness (slider + numeric, e.g., 0.4) + "Dynamic" toggle
  - Rotation (slider + numeric, e.g., 0) + "Dynamic" toggle
- LAYER TRANSFORM: (same as above)

**For FilmGrain:**
- Similar effect and transform structure

#### Common Features:
- Sliders with numeric inputs allowing precise control
- "Dynamic" toggle labels on effect properties for animation/interactivity
- All properties are editable; changes apply in real-time to the canvas

### Canvas (Center)
- Large preview showing the composed shader effect
- Live rendering (e.g., gradient grid with film grain)
- Interactive preview area

### Bottom Bar - Toolbar
- **Left section**:
  - "Export" button (with dropdown showing Vue, React, Svelte, Solid icons)
  - Checkmark (likely "Save" or "Apply")
  - Settings/gear icon
  - Color/palette icon
  - Camera/screenshot icon
  - Collapse/expand button

- **Right section** (Icon toolbar ~8 buttons):
  - Various control icons (purposes: add layer, alignment, blend mode, effects, opacity/visibility, undo/redo, duplicate, lock, etc.)
  - Icons: layout grid, alignment, blend modes, opacity, visibility, undo/redo, duplicate, library/save

### Export Button Features
- Dropdown showing framework targets: Vue, React, Svelte, Solid
- Implies export formats: code (per framework) + image formats (JPG, PNG implied)

### Key UI Patterns
- Undo/redo (likely keyboard shortcuts Cmd+Z / Cmd+Shift+Z)
- Save functionality (button or auto-save)
- Layer selection with property introspection
- Real-time preview
- "Dynamic" toggle for making props reactive/animated

---

## 4. Documentation (shaders.com/docs)

### Header Section
- "Documentation" heading with NPM Version badge
- Two main tabs:
  - **Guides** (/docs/guide)
  - **Component Docs** (/docs/components)
- **Framework Selector** (combobox dropdown):
  - Options: Vue / Nuxt, React / Next, Svelte, Solid, JavaScript
  - Icons shown for each framework

### Guides Section (/docs/guide)

#### Sidebar Navigation (Collapsible Sections)
1. **Core Concepts**:
   - What is Shaders?
   - Quickstart
   - Composing Effects
   - Layout & Positioning

2. **Features**:
   - Blending & Masking
   - Props & Reactivity
   - Transforms
   - Dynamic Props
   - Shape / SDF Effects

3. **Advanced**:
   - Color Space
   - Telemetry
   - Hooks & Events
   - Shaders MCP (Pro)
   - Performance
   - Nuxt / SSR

#### Main Content Area
- Article content with headings, code examples, and explanations
- "On this page" sidebar with anchor links to sections
- Action buttons: "Open in ChatGPT", "Open in Claude", "Open in Cursor", "Open in Grok", "Open in Perplexity", "Open in T3 Chat"
- Navigation: "Previous" and "Next" links at bottom (example: "Next > Quickstart")

### Components Section (/docs/components)

#### Sidebar Navigation Categories:
1. **Textures** (40 components):
   Aurora, Beam, Blob, BrickPattern, Checkerboard, Chevron, ColorWheel, ConicGradient, DiamondGradient, DOMTexture, DotGrid, FallingLines, FloatingParticles, FlowingGradient, FractalNoise, Godrays, Grid, HexGrid, ImageTexture, LinearGradient, Marble, MultiPointGradient, Plasma, RadialGradient, Ripples, SimplexNoise, SineWave, SolidColor, Spiral, Strands, Stripes, StudioBackground, SunBurst, Swirl, Truchet, VideoTexture, Voronoi, Weave, WebcamTexture, WorleyNoise

2. **Shapes** (11 components):
   Circle, Crescent, Cross, Ellipse, Flower, Polygon, Ring, RoundedRect, Star, Trapezoid, Vesica

3. **Shape Effects** (5 components):
   Crystal, Emboss, Glass, Neon, SmokeFill

4. **Stylize** (15 components):
   Ascii, ChromaticAberration, ContourLines, CRTScreen, Dither, DropShadow, FilmGrain, Glitch, Glow, Halftone, LensFlare, Paper, Pixelate, ReflectivePlane, VHS, Vignette

5. **Interactive** (8 components):
   ChromaFlow, CursorRipples, CursorTrail, Fog, GridDistortion, Liquify, Shatter, Smoke

6. **Distortions** (16 components):
   BarShift, Bulge, ConcentricSpin, FlowField, FlutedGlass, Form3D, GlassTiles, Kaleidoscope, Mirror, Perspective, PolarCoordinates, RectangularCoordinates, Spherize, Stretch, Twirl, WaveDistortion

7. **Blurs** (8 components):
   AngularBlur, Blur, ChannelBlur, DiffuseBlur, LinearBlur, ProgressiveBlur, TiltShift, ZoomBlur

8. **Adjustments** (12 components):
   BrightnessContrast, Duotone, Grayscale, HueShift, Invert, Posterize, Saturation, Sharpness, Solarize, Tint, Tritone, Vibrance

#### Component Detail Page (Example: Aurora)
- Component name heading
- Description (e.g., "Mesmerizing aurora borealis with layered curtains, vertical rays, and flowing light")
- **Props Table**:
  - Columns: Prop name, Type, Default, Description
  - Example props for Aurora: colorA, colorB, colorC, colorSpace, balance, intensity, curtainCount, speed, waviness, rayDensity, height, center, seed
- **Usage Section** with framework tabs (Vue, React, Svelte, Solid, JS):
  - Code examples for each framework
  - Copy to clipboard button
- Navigation: Previous (Ascii) and Next (BarShift) links
- Right sidebar: "On this page" links to Props and Usage sections
- AI chat integration links (ChatGPT, Claude, Cursor, Grok, Perplexity, T3 Chat)

### Footer (all docs pages)
- Welcome-back message for authenticated users
- Dashboard link with user avatar
- Standard footer: Product, Presets, Docs, Legal sections with links
- Social links: X (Twitter), Discord, YouTube, npm

---

## Summary of Key Features Across All Pages

1. **Authentication**: User menu with profile avatar throughout; logged-in state (user: Antonius Richardo)
2. **Search**: Semantic search on presets page with suggested tags and category filters
3. **Shader Composition**: Layer-based editor with property panels, dynamic toggles, and real-time preview
4. **Export**: Multi-framework export (Vue, React, Svelte, Solid, JS) + image formats
5. **Documentation**: Comprehensive guides + 107 total components across 8 categories
6. **Color Management**: Color space selector in editor (P3 Linear, Linear, Standard) + per-component color space props
7. **Responsive Navigation**: Consistent top bar, sidebar navigation, and footer across all pages
8. **Community**: Discord integration, social links, AI chat helpers (Claude, ChatGPT, Cursor, etc.)
