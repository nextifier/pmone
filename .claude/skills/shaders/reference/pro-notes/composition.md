---
title: Composition - Layering, Opacity, and Transparency
description: How to compose shader layers effectively, avoid pointless stacks, and use the transparent background to your advantage
---

# Composition - Layering, Opacity, and Transparency

## Don't stack components that can't be seen

Every component in the stack costs GPU time. If a foreground component renders completely opaque across the entire canvas - covering every pixel with full alpha - anything below it is invisible and wasted.

The most common example is placing a `Checkerboard`, `SolidColor`, or `Stripes` (with fully opaque colors) over a `Swirl` or `Aurora`. If the top layer has no transparency, the bottom layer will never be seen.

Before adding a background layer, ask: does the foreground leave any transparent or semi-transparent pixels where the background would show through? If not, remove the background layer.

## The shader renders on a transparent background

Unless you add a fully opaque base layer (which you should in most cases), the shader canvas is fully transparent. This is a feature, not a limitation.

**Overlaying shaders on page content:** By default, the `<Shader>` element has a transparent background. Position it over a section of the page and page content will show through any areas the shader does not cover. Combined with masking, this enables precise integration - the shader fades in from one side while the rest of the layout remains fully visible. Obviously for this to work the z-index depth of the shader will need to be higher than page content, but it can make for an interesting effect. That said, if you're specifically creating a background, using an opaque base layer and lower z-index is the practical approach.

See `shaders://pro-notes/hero-section-masking` for tips on masking your compositions.
