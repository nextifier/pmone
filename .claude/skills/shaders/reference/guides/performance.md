---
title: Performance
description: Design high-performance shaders - what's expensive, what's free, and how to stay fast
icon: gauge-high
category: advanced
---

# Performance

Shaders runs on the GPU, which means most effects are significantly faster than equivalent CSS or canvas2D solutions. That said, the GPU isn't free - some patterns cost more than others. Understanding what drives GPU cost helps you design effects that stay smooth.

## The frame budget

At 60fps, the GPU has **16.67ms per frame**. Shaders renders once per frame, compositing all visible layers together. Simple setups render in under 1ms. Complex nesting with multiple effects can push toward that budget.

The renderer automatically drops to ~1fps when the canvas is scrolled out of the viewport - so effects that aren't visible consume almost nothing.

## Generator vs. filter effects

Effects fall into two broad categories with very different cost profiles:

**Generator** components (LinearGradient, Circle, Plasma, Swirl, etc.) create pixels from scratch by evaluating a mathematical function per-pixel. They're extremely fast - a full-screen gradient or noise pattern takes fractions of a millisecond.

**Filter/Effect** components (Blur, Glass, GlassTiles, Glow, Dither, etc.) need to read from an existing rendered image - they require a **render-to-texture (RTT)** pass. RTT adds one extra GPU render pass per effect boundary.

**Rough cost categories:**

| Category | Examples | Relative cost |
|----------|----------|---------------|
| Very light | SolidColor, LinearGradient, RadialGradient | ~0-0.1ms      |
| Light | Swirl, Circle, Plasma, SimplexNoise, most generators | ~0.1-0.5ms    |
| Medium | Blur, Glow, Dither, Halftone, Pixelate, CursorTrail | ~0.5-2ms      |
| Heavy | Glass, GlassTiles, multiple nested RTT effects | ~1-2ms+       |

These are rough estimates on a modern GPU. Performance varies significantly by hardware, canvas size, and pixel density.

## Render-to-texture (RTT)

RTT is what happens when one effect needs to read the rendered output of another. The renderer first renders the source layer to an intermediate texture, then the effect samples from it.

**What triggers RTT:**
- Filter/effect components that need input (Blur, Glass, GlassTiles, etc.)
- The `map` [Prop Driver](/docs/guide/dynamic-props) on any component (the source layer is RTT'd)
- The masking system when a layer is used as a mask source
- Non-default [Transforms](/docs/guide/transforms) on a component

**The cost scales with nesting.** One RTT pass is cheap. Three nested RTT effects means three serial passes. Avoid deep nesting of heavy filter effects:

```vue-html
<!-- Avoid: three RTT passes in series -->
<Shader>
  <LinearGradient />
  <GlassTiles>
    <Glass>
      <Blur :radius="20">
        <Circle />
      </Blur>
    </Glass>
  </GlassTiles>
</Shader>

<!-- Better: fewer RTT boundaries -->
<Shader>
  <LinearGradient />
  <GlassTiles>
    <Circle />
  </GlassTiles>
</Shader>
```

## Practical tips

**Use generators as backgrounds.** LinearGradient, Plasma, SimplexNoise, and similar generators are nearly free. Build your base layer with these before adding filter effects on top.

**Limit nested filter effects.** Each nesting boundary where a filter effect wraps children creates an RTT pass. Three flat filter layers is cheaper than three nested ones.

**Invisible layers still cost.** A hidden layer with `visible={false}` is completely excluded from composition - zero cost. Using the opacity prop with a value of `0` still renders the layer. Use `visible={false}` to truly exclude layers you don't need.

**Canvas size matters.** RTT textures scale with the canvas resolution. A full-screen Glass effect on a 4K display will be more expensive than on a 1080p display. This is automatic behavior, not something you control directly, but it's worth knowing for very large canvases.

**Prefer runtime props over compile-time ones.** Most props update instantly by writing a new GPU uniform value. A small number of props are marked as "compile-time" - changing them triggers a shader recompile, which pauses rendering briefly. The component docs flag these where applicable. Avoid animating compile-time props.