---
title: Finishing Touches - Texture, Grain, and Ambient Motion
description: How to use FilmGrain and Paper to add tactile texture, and which components make the best lightly-animated backgrounds
components: [FilmGrain, Paper, Swirl, FlowingGradient, StudioBackground]
---

# Finishing Touches - Texture, Grain, and Ambient Motion

## Adding texture with FilmGrain and Paper

Adding a thin layer of `FilmGrain` at the end of your shader stack is one of the cheapest ways to make a composition feel more physical and tactile.

```jsx
<Shader>
  <FlowingGradient />
  <FilmGrain />   {/* applies to everything above - no children needed */}
</Shader>
```

**Intensity calibration by background brightness:**

This is easy to get wrong. The grain is highly visible on dark compositions and barely noticeable in bright ones. The practical rule:

- **Dark backgrounds:** start at `0.02-0.05` intensity. Even this will be clearly visible. Push above `0.1` only for a deliberately gritty aesthetic.
- **Light/white backgrounds:** `0.3-0.4` intensity is usually a sweet spot.

A value that looks perfect on a dark shader will be overwhelming on a light one - always calibrate to the actual brightness of the composition rather than using a single default.

`FilmGrain` adds random static noise (cinematic feel, not animated). `Paper` adds a static fibrous texture (print/editorial feel). They can be layered together at very low intensities for a combined effect.

## Breaking up banding in gradients

`FilmGrain` also has a secondary use, in that it breaks up color banding in gradients where the colors are too similar. Again, even the tiniest amount of grain makes a big difference, so don't overdo it.

## Recommended components for lightly animated backgrounds

If the goal is a background that feels alive but doesn't distract from content, these three are the most reliable starting points:

**Swirl** - smooth, fluid rotation with configurable spiral density. Set two colors that are close in hue or brightness (e.g. two shades of the same blue, or a dark grey and a near-black) for a background that feels like a single color with subtle internal motion. Lower `density` values are less visually complex.

**FlowingGradient** - organic liquid silk motion across multiple color bands. Works especially well when the colors are kept within a narrow palette - pick 2-3 colors in the same family and reduce the variance so it reads as one unified tone that shifts gently.

**StudioBackground** - a photograph-inspired vignette and gradient background. Ideal when you want something polished with zero configuration effort. Defaults to a light white background, but setting the "surface color" to any color instantly gives you a specific-colored studio backdrop with ambient lighting that animates slowly over time.

For any of these, the key to keeping a background "background-like" is restraint with color variance, density/complexity and animation speed. Pick colors that share a common lightness and saturation level, varying only slightly. Keep complexity and animation speed low to make it subtle and clean.
