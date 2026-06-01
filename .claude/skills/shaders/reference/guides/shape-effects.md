---
title: Shape / SDF Effects
description: How to use and configure the built-in shape system
icon: shapes
category: features
---

# Shape / SDF Effects

Several components - such as **Glass**, **Neon**, and **Emboss** - are driven by a *shape*. These effects accept a `shape` prop that describes which shape to use and how it should be configured. They use what is called an "SDF" (or "signed distance field") texture to render the shape. An SDF is a mathematical representation of a shape that determines the distance from any pixel to the shape's boundary.

The result is a stunning physically based effect that wraps around a particular shape, including your own SVG logo or icon.

## Configuration

The `shape` prop accepts a configuration object. The only required key is `type`, which selects one of the built-in analytical shapes, like `circleSDF` or `starSDF`. Everything else is shape-specific and optional (sensible defaults apply).

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<!-- Circle (default) -->
<Glass :shape='{ type: "circleSDF", radius: 0.35 }' />

<!-- Six-pointed star -->
<Neon :shape='{ type: "starSDF", radius: 0.35, sides: 6, innerRatio: 0.45 }' />

<!-- Rounded rectangle -->
<Emboss :shape='{ type: "roundedRectSDF", width: 0.4, height: 0.25, rounding: 0.06 }' />
```

```jsx
// Circle (default)
<Glass shape={JSON.stringify({ type: "circleSDF", radius: 0.35 })} />

// Six-pointed star
<Neon shape={JSON.stringify({ type: "starSDF", radius: 0.35, sides: 6, innerRatio: 0.45 })} />

// Rounded rectangle
<Emboss shape={JSON.stringify({ type: "roundedRectSDF", width: 0.4, height: 0.25, rounding: 0.06 })} />
```

```tsx
// Circle (default)
<Glass shape={JSON.stringify({ type: "circleSDF", radius: 0.35 })} />

// Six-pointed star
<Neon shape={JSON.stringify({ type: "starSDF", radius: 0.35, sides: 6, innerRatio: 0.45 })} />

// Rounded rectangle
<Emboss shape={JSON.stringify({ type: "roundedRectSDF", width: 0.4, height: 0.25, rounding: 0.06 })} />
```

```tsx
// Circle (default)
<Glass shape={JSON.stringify({ type: "circleSDF", radius: 0.35 })} />

// Six-pointed star
<Neon shape={JSON.stringify({ type: "starSDF", radius: 0.35, sides: 6, innerRatio: 0.45 })} />

// Rounded rectangle
<Emboss shape={JSON.stringify({ type: "roundedRectSDF", width: 0.4, height: 0.25, rounding: 0.06 })} />
```

```javascript
// Circle (default)
const shader = await createShader(canvas, {
  components: [
    { type: 'Glass', props: { shape: { type: 'circleSDF', radius: 0.35 } } }
  ]
})

// Six-pointed star
const shader = await createShader(canvas, {
  components: [
    { type: 'Neon', props: { shape: { type: 'starSDF', radius: 0.35, sides: 6, innerRatio: 0.45 } } }
  ]
})

// Rounded rectangle
const shader = await createShader(canvas, {
  components: [
    { type: 'Emboss', props: { shape: { type: 'roundedRectSDF', width: 0.4, height: 0.25, rounding: 0.06 } } }
  ]
})
```
::

---

## Built-in shapes

### Circle - `circleSDF`

A perfect circle. The default shape for all shape effects.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Circle radius in UV space |

```json
{ "type": "circleSDF", "radius": 0.35 }
```

---

### Ellipse - `ellipseSDF`

A stretched circle with independent horizontal and vertical radii.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `width` | `number` | `0.4` | Horizontal radius (semi-major axis) |
| `height` | `number` | `0.25` | Vertical radius (semi-minor axis) |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "ellipseSDF", "width": 0.4, "height": 0.25, "rotation": 0 }
```

---

### Polygon - `polygonSDF`

A regular N-sided polygon (triangle, square, hexagon, etc.).

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Distance from center to nearest edge midpoint |
| `sides` | `number` | `6` | Number of sides |
| `rounding` | `number` | `0` | Corner rounding amount |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "polygonSDF", "radius": 0.3, "sides": 6, "rounding": 0.05, "rotation": 0 }
```

---

### Star - `starSDF`

An N-pointed star with configurable inner/outer radius ratio.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Outer tip radius |
| `sides` | `number` | `5` | Number of points |
| `innerRatio` | `number` | `0.382` | Inner vertex radius as a fraction of outer radius - `0.382` gives the classic golden-ratio 5-star |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "starSDF", "radius": 0.35, "sides": 5, "innerRatio": 0.382, "rotation": 0 }
```

---

### Flower - `flowerSDF`

An N-petalled flower with smooth concave valleys between petals.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Outer petal tip radius |
| `sides` | `number` | `6` | Number of petals |
| `innerRatio` | `number` | `0.5` | Valley depth - `0.2` = deep narrow valleys, `0.8` = subtle scalloped edge |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "flowerSDF", "radius": 0.35, "sides": 6, "innerRatio": 0.5, "rotation": 0 }
```

---

### Ring - `ringSDF`

A hollow ring / annulus. The effect applies to the ring material itself, not the interior hole.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.3` | Distance from center to the ring centerline |
| `thickness` | `number` | `0.08` | Half-width of the ring on each side of the centerline |

```json
{ "type": "ringSDF", "radius": 0.3, "thickness": 0.08 }
```

---

### Cross - `crossSDF`

A plus-sign cross with configurable arm length, thickness, and corner rounding.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `size` | `number` | `0.25` | Arm half-length |
| `thickness` | `number` | `0.08` | Arm half-width |
| `rounding` | `number` | `0.02` | Corner rounding at arm ends and intersections |
| `rotation` | `number` | `0` | Rotation in degrees - use `45` for an X shape |

```json
{ "type": "crossSDF", "size": 0.25, "thickness": 0.08, "rounding": 0.02, "rotation": 0 }
```

---

### Rounded Rectangle - `roundedRectSDF`

A rectangle with uniformly rounded corners.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `width` | `number` | `0.35` | Half-width |
| `height` | `number` | `0.25` | Half-height |
| `rounding` | `number` | `0.05` | Corner rounding radius |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "roundedRectSDF", "width": 0.35, "height": 0.25, "rounding": 0.05, "rotation": 0 }
```

---

### Vesica - `vesicaSDF`

A lens / eye shape formed by the intersection of two overlapping circles.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Radius of each circle |
| `spread` | `number` | `0.5` | Half-distance between circle centers as a fraction of radius - `0` = a single circle, `1` = an infinitely thin lens |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "vesicaSDF", "radius": 0.35, "spread": 0.5, "rotation": 0 }
```

---

### Crescent - `crescentSDF`

A crescent / moon shape formed by subtracting a smaller offset circle from a larger one.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `radius` | `number` | `0.35` | Outer circle radius |
| `innerRatio` | `number` | `0.75` | Inner circle radius as a fraction of outer - larger values produce a thinner crescent |
| `offset` | `number` | `0.15` | Horizontal distance between the two circle centers - controls how much the inner circle overlaps |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "crescentSDF", "radius": 0.35, "innerRatio": 0.75, "offset": 0.15, "rotation": 0 }
```

---

### Trapezoid - `trapezoidSDF`

A quadrilateral with parallel top and bottom edges of different widths.

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `bottomWidth` | `number` | `0.35` | Bottom edge half-width |
| `topWidth` | `number` | `0.2` | Top edge half-width |
| `height` | `number` | `0.25` | Half-height |
| `rotation` | `number` | `0` | Rotation in degrees |

```json
{ "type": "trapezoidSDF", "bottomWidth": 0.35, "topWidth": 0.2, "height": 0.25, "rotation": 0 }
```

---

## Custom shapes

For getting creative with custom shapes like your product logo or icon, you can supply an SDF (signed distance) field texture.

To use an SVG shape, simply set `:shapeSdfUrl` to the URL of the SDF `.bin` file. Note that at the moment the SDF conversion occurs within the design editor, so it's recommended to use the `.bin` file provided in the code export from the editor. In the near future we'll release a standalone `SVG -> SDF` conversion tool here for non-Pro users.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Glass :shapeSdfUrl="myLogoUrl" />
```

```jsx
<Glass shapeSdfUrl={myLogoUrl} />
```

```tsx
<Glass shapeSdfUrl={myLogoUrl} />
```

```tsx
<Glass shapeSdfUrl={myLogoUrl} />
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'Glass', props: { shapeSdfUrl: 'https://example.com/my-logo.bin' } }
  ]
})
```
::
