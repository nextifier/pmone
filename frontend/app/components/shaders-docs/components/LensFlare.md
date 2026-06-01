---
title: LensFlare
description: Realistic camera lens flare with artifacts.
category: Stylize
componentType: Generator
requiresChild: false
---

# LensFlare

Realistic camera lens flare with artifacts.


::shader-preview{component="LensFlare"}
::

## Props

::props-table
---
props:
  - name: "lightPosition"
    type: "{x: number, y: number}"
    default: "{\"x\":0.3,\"y\":0.3}"
    description: "Position of the light source"
  - name: "intensity"
    type: "number"
    default: "0.5"
    description: "Master brightness of the entire lens flare effect"
  - name: "ghostIntensity"
    type: "number"
    default: "0.4"
    description: "Brightness of internal reflection ghost discs along the flare axis"
  - name: "ghostSpread"
    type: "number"
    default: "0.7"
    description: "Spacing between ghost reflections along the flare axis"
  - name: "ghostChroma"
    type: "number"
    default: "0.3"
    description: "Rainbow chromatic fringing around ghost element edges"
  - name: "haloIntensity"
    type: "number"
    default: "0.4"
    description: "Brightness of the circular halo ring from internal reflection"
  - name: "haloRadius"
    type: "number"
    default: "0.6"
    description: "Radius of the halo ring"
  - name: "haloChroma"
    type: "number"
    default: "0.6"
    description: "Spectral dispersion on the halo creating rainbow color separation"
  - name: "haloSoftness"
    type: "number"
    default: "0.8"
    description: "Thickness and softness of the halo ring"
  - name: "starburstIntensity"
    type: "number"
    default: "0.3"
    description: "Brightness of diffraction spikes radiating from the light source"
  - name: "starburstPoints"
    type: "number"
    default: "6"
    description: "Number of starburst spikes (simulates aperture blade count)"
  - name: "streakIntensity"
    type: "number"
    default: "0.15"
    description: "Brightness of horizontal anamorphic light streak"
  - name: "streakLength"
    type: "number"
    default: "0.5"
    description: "Horizontal extent of the anamorphic streak"
  - name: "glareIntensity"
    type: "number"
    default: "0.2"
    description: "Soft veiling glare that washes out contrast around the light"
  - name: "glareSize"
    type: "number"
    default: "0.5"
    description: "Size of the soft glare glow"
  - name: "edgeFade"
    type: "number"
    default: "0.2"
    description: "How much the flare fades when the light source is near the screen edge (0 = no fade, 1 = heavy fade)"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Speed of subtle flare shimmer and starburst rotation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LensFlare
    :intensity="0.5"
  />
</Shader>
```

```jsx
<Shader>
  <LensFlare
    intensity={0.5}
  />
</Shader>
```

```svelte
<Shader>
  <LensFlare
    intensity={0.5}
  />
</Shader>
```

```tsx
<Shader>
  <LensFlare
    intensity={0.5}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'LensFlare', props: { intensity: 0.5 } }
  ]
})
```
::
