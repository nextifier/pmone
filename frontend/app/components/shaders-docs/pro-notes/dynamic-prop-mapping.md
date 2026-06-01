---
title: Dynamic Prop Mapping - Driving Props from Mouse, Animation, and Layers
description: Complete reference for all four dynamic prop mapping modes, including the powerful map mode for cross-layer data flow
components: [RadialGradient, LinearGradient, Blur, ZoomBlur, AngularBlur, ChromaticAberration, Vignette, Circle, Ellipse, SimplexNoise]
---

# Dynamic Prop Mapping - Driving Props from Mouse, Animation, and Layers

Dynamic prop mapping lets you replace any static prop value with a live driver - one that updates every frame from the cursor position, based on auto-animation, or from the visual output of another layer. Instead of hardcoding `radius={0.4}`, you write a driver object that makes the radius follow the mouse, pulse over time, or respond to the luminance of a texture elsewhere in the composition.

There are four driver modes. Each has a different input source and different configuration. The regular docs cover the syntax for your framework - this note covers the semantics: what each mode actually does, when to use it, and how to get the most out of `map` mode in particular.

---

## The four modes at a glance

| Mode | Input source | Output type | Use for |
|---|---|---|---|
| `mouse-position` | Cursor XY position | `{x, y}` position | Moving a component's center/position to follow the cursor |
| `mouse` | One axis of cursor position | Scalar number | Scaling intensity, blur, etc. based on where the cursor is |
| `auto-animate` | Elapsed time | Scalar number | Pulsing, rotating, oscillating props over time |
| `map` | Another layer's rendered pixels | Scalar number | Using one layer's shape/brightness to drive another layer's prop |

---

## `mouse-position` - tracking a position prop

Binds a `{x, y}` position prop to the cursor. The output is always normalised canvas coordinates (0-1 range).

```js
{
  type: "mouse-position",
  smoothing: 0.2,        // lag: 0 = instant, higher = sluggish
  momentum: 0.2,         // spring overshoot: 0 = none, near 1 = bouncy
  reach: 1,              // displacement scale from origin (default 1 = 1:1)
  originX: 0.5,          // scaling origin (default: viewport centre)
  originY: 0.5,
  invertX: false,        // flip horizontal direction
  invertY: false,
}
```

**Pinning one axis:** Set `x` or `y` to a fixed number to lock that axis while leaving the other free. This is how you make something track horizontally but stay centred vertically:

```js
{ type: "mouse-position", y: 0.5 }  // tracks X, locked at vertical centre
```

**Recommended starting defaults:** `smoothing: 0.2, momentum: 0.2` gives a natural, slightly weighted feel - the element follows the cursor with a gentle lag and a very slight overshoot. From there, increase `smoothing` for more lag, increase `momentum` for more bounce.

**`reach`:** Multiplies the displacement from the origin point. At `reach: 2`, a cursor that moves halfway across the screen moves the element by a full-screen width. Use values below 1 for subtle parallax.

---

## `mouse` - scaling a numeric prop from one cursor axis

Maps a single cursor axis (X or Y) to a scalar prop. The cursor's normalised position (0-1) is remapped to your output range.

```js
{
  type: "mouse",
  axis: "x",            // "x" or "y"
  outputMin: 0,         // value when axis is at 0
  outputMax: 1,         // value when axis is at 1
  curve: 0,             // -1 to +1, biases toward min or max
  smoothing: 0.1,
  momentum: 0,
}
```

**`curve`:** Applies a power curve to the normalised input before remapping. Negative values concentrate change near `outputMin`; positive values concentrate change near `outputMax`. A value of `-0.5` makes the prop very responsive at the left/bottom of the screen and less responsive at the right/top.

**Example - blur intensity from cursor X:**

```jsx
<Blur strength={{ type: "mouse", axis: "x", outputMin: 0, outputMax: 20 }} />
```

Moving the cursor right increases blur from 0 to 20. Left edge of screen = no blur; right edge = maximum blur.

---

## `auto-animate` - animating a prop over time

Drives a numeric prop from a continuously advancing time value. Two modes: `ping-pong` (oscillates back and forth) and `loop` (wraps around, useful for rotation).

```js
{
  type: "auto-animate",
  mode: "ping-pong",    // "ping-pong" or "loop"
  outputMin: 0.2,       // value at one extreme
  outputMax: 0.8,       // value at the other extreme
  speed: 1.0,           // cycles per second (base rate: ~5s at speed=1)
  easing: "sine",       // "sine" | "linear" | "quad" | "expo" | "bounce"
}
```

**`mode` choices:**
- `ping-pong` - goes from `outputMin` to `outputMax` and back. Best for pulsing, breathing, back-and-forth movement.
- `loop` - goes from `outputMin` to `outputMax` then jumps back to `outputMin`. Best for rotation angles, hue shifts, anything that wraps.

**`easing`** (applies to `ping-pong` only):
- `sine` - smooth cosine ease in/out, the default. Organic and natural.
- `linear` - constant speed, mechanical.
- `quad` - tighter ease at the extremes, slightly snappier.
- `expo` - very slow at the extremes, fast through the middle. Good for dramatic pulses.
- `bounce` - bounces at each end. Use sparingly - it reads as cartoonish at high speed.

**`speed`:** Negative values reverse direction. All props sharing the same `speed` value stay in sync because they share a global elapsed time - you can orchestrate multiple props pulsing in phase by matching their speeds.

**Example - gentle radius pulse:**

```jsx
<Circle radius={{ type: "auto-animate", mode: "ping-pong", outputMin: 0.3, outputMax: 0.5, speed: 0.4, easing: "sine" }} />
```

---

## `map` - driving a prop from another layer's pixels

This is the most powerful and least obvious mode. `map` reads the rendered output of another layer in the composition and extracts a scalar value from it each frame. That scalar is then remapped to drive any numeric prop on any other component.

In short: **one layer's visual appearance becomes a control signal for another layer's behaviour.**

```js
{
  type: "map",
  source: "myLayerId",        // id of the component to read from
  channel: "luminance",       // what to extract from that layer
  inputMin: 0,                // source value considered "0"
  inputMax: 1,                // source value considered "1"
  outputMin: 0,               // output when normalised input is 0
  outputMax: 10,              // output when normalised input is 1
  curve: 0,                   // -1 to +1 power curve
}
```

**`channel` options:**
- `alpha` - reads the alpha channel of the source layer. Transparent areas → 0, opaque areas → 1.
- `alphaInverted` - inverted alpha. Transparent areas → 1.
- `luminance` - reads perceived brightness (BT.709 weights: `0.2126R + 0.7152G + 0.0722B`). Black → 0, white → 1.
- `luminanceInverted` - inverted brightness. Bright areas → 0, dark areas → 1.

### How the sampling works

The source layer is rendered to a texture once per composition. Each frame, the driver samples that texture at the current screen position - the same UV the current pixel occupies. This means `map` drives are **spatially aware**: the driven prop doesn't get a single value for the whole canvas; it gets a value that varies across every pixel based on where that pixel falls on the source layer.

This is what makes `map` mode remarkable: it's not a global control, it's per-pixel. You can use the spatial distribution of one layer to modulate the behavior of another across the entire canvas simultaneously.

### What you can build with `map`

**Luminance-driven blur:** A `SimplexNoise` layer with dark and bright patches drives the `strength` of a `Blur`. Where the noise is bright, blur is strong; where it's dark, blur is light. The result looks organic - as if the focus plane is uneven.

```jsx
<Shader>
  <SimplexNoise id="noiseMask" visible={false} />
  <ImageTexture src="/hero.jpg" />
  <Blur strength={{
    type: "map",
    source: "noiseMask",
    channel: "luminance",
    inputMin: 0,
    inputMax: 1,
    outputMin: 0,
    outputMax: 15
  }} />
</Shader>
```

**Shape-driven intensity:** A `Circle` or `RadialGradient` with a soft edge drives any prop that should be stronger at the center and weaker at the edges - chromatic aberration, displacement strength. This is often more expressive than just using a mask.

```jsx
<Shader>
  <RadialGradient id="vignetteShape" visible={false} colorInner="#ffffff" colorOuter="#000000" />
  <Aurora />
  <ChromaticAberration strength={{
    type: "map",
    source: "vignetteShape",
    channel: "luminanceInverted",  // strongest at edges (dark in source)
    inputMin: 0,
    inputMax: 1,
    outputMin: 0,
    outputMax: 8
  }} />
</Shader>
```

**Animated source → animated output:** If the source layer is itself animated (or has a `mouse-position` or `auto-animate` driver or reactive animated props), its changing appearance drives the mapped prop frame by frame. The result is a derived animation - one layer's motion creates another layer's reaction.

**Alpha-based gating:** Set `channel: "alpha"` on a source with hard edges to create binary-like driven values. Where the source is fully opaque the prop is at `outputMax`; where it's transparent the prop is at `outputMin`. This can threshold effects so they only appear within a specific region. This is like masking but can allow the "off" value to be non-zero and in some cases it won't cause clipping (like in `ASCII` where the mask would otherwise clip the output).

### Gotchas

**The source layer can be, but doesn't have to be visible.** Set `visible={false}` on it. It still gets rendered to a texture for sampling - it just doesn't composite into the final output. This is the standard pattern: design a shape or gradient purely as a control source, keep it invisible.

**Circular dependencies are silently dropped.** If A maps from B and B maps from A, the cycle is detected and one mapping is dropped with a console error. Chain freely (A → B → C is fine), but don't loop.

**RTT cost.** The source layer is rendered to a texture. This is an additional GPU pass. One source used by multiple `map` drivers is only RTT'd once, so share sources where possible.

**`inputMin`/`inputMax` clamp the input range.** If your source typically outputs values between 0.3 and 0.7, set `inputMin: 0.3, inputMax: 0.7` to use the full output range rather than only using 40% of it.

---

## Combining modes

Props are independent - different props on the same component can use different drivers simultaneously:

```jsx
<RadialGradient
  center={{ type: "mouse-position", smoothing: 0.15, momentum: 0.1 }}
  radius={{ type: "auto-animate", mode: "ping-pong", outputMin: 0.3, outputMax: 0.6, speed: 0.5 }}
/>
```

The center follows the cursor with a little spring; the radius independently pulses on its own cycle. Neither interferes with the other. This is the basis for taking otherwise mundane components and turning them into dynamic, interactive experiences.
