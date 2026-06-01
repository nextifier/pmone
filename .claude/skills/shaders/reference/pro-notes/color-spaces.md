---
title: Color Spaces - Choosing How Colors Blend
description: When to use OKLAB, OKLCh, HSL, or Linear RGB for color interpolation in gradient and multi-color components
---

# Color Spaces - Choosing How Colors Blend

Many components that interpolate between two or more colors expose a `colorSpace` prop. The default in most cases is `linear` (Linear RGB), which is physically accurate but can produce unexpected results - most notably, muddy or desaturated mid-tones when blending across hues. Understanding when to change this is one of the fastest ways to improve a composition's appearance.

## Quick reference

| Color Space | Character | Best for |
|---|---|---|
| `linear` | Physically accurate, can muddy mid-tones | Realistic lighting, photographic gradients |
| `oklab` | Perceptually uniform, consistent lightness | General-purpose blending, clean transitions |
| `oklch` | Perceptually uniform + hue rotation | Vivid gradients with full spectrum transitions |
| `hsl` | Hue-based, familiar | Intentional rainbow/spectral gradients |
| `hsv` | Similar to HSL, different lightness | Graphics-software-style color picking |
| `lch` | CIE-standard perceptual | Standards-compliant perceptual blending |

## The default is often not the best choice

Linear RGB is the default because it is physically correct for lighting calculations. But for visual gradients and color effects, "physically correct" often means colors pass through a grey or muddy zone in the middle of the blend - particularly noticeable when going from warm to cool tones (e.g. orange → blue produces a murky brown midpoint).

**OKLAB as the first upgrade:** If a gradient looks dull or muddy at its midpoint, switch to `oklab`. OKLAB maintains consistent perceived lightness across the blend, so the transition stays vivid throughout. It is usually the right choice for any component where you want clean, professional-looking color blending.

```jsx
<LinearGradient
  colorFrom="#ff6b00"
  colorTo="#0066ff"
  colorSpace="oklab"   {/* eliminates the muddy brown midpoint */}
/>
```

## When you want more color

If the goal is a vivid, rainbow-like gradient that passes through intermediate hues - a full-spectrum sweep - use `oklch` or `hsl`:

- **OKLCh** gives you hue rotation with perceptual uniformity, so the lightness stays consistent as it sweeps through the spectrum. Produces richer, more saturated intermediate colors than OKLAB.
- **HSL** is the most direct "go through the rainbow" mode - it rotates the hue angle between the two endpoints, which can produce dramatic spectral transitions. Use deliberately, as it can look garish at high saturation.

## In practice

Start with `linear`. If the midpoint looks muddy, switch to `oklab`. If you want more color intensity or a spectrum sweep, try `oklch` then `hsl`. You rarely need `hsv` or `lch` unless you have a specific reason.
