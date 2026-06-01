---
title: FloatingParticles
description: Animated floating particles with twinkle effects
category: Textures
componentType: Generator
requiresChild: false
---

# FloatingParticles

Animated floating particles with twinkle effects


::shader-preview{component="FloatingParticles"}
::

## Props

::props-table
---
props:
  - name: "randomness"
    type: "number"
    default: "0.25"
    description: "Randomness of particle animation"
  - name: "speed"
    type: "number"
    default: "0.25"
    description: "Speed of particle movement"
  - name: "angle"
    type: "number"
    default: "90"
    description: "Movement angle in degrees (0=right, 90=down, 180=left, 270=up)"
  - name: "particleSize"
    type: "number"
    default: "2"
    description: "Size of particles"
  - name: "particleSoftness"
    type: "number"
    default: "0"
    description: "Edge softness of particles (0 = sharp, 1 = very soft)"
  - name: "twinkle"
    type: "number"
    default: "0.5"
    description: "Intensity of the twinkle effect (0 = off, 1 = full twinkle)"
  - name: "count"
    type: "number"
    default: "5"
    description: "Number of particle layers"
  - name: "particleColor"
    type: "string"
    default: "#ffffff"
    description: "Color of the particles"
  - name: "speedVariance"
    type: "number"
    default: "0.3"
    description: "Per-layer speed variance (0 = all layers same speed, 1 = high variance)"
  - name: "angleVariance"
    type: "number"
    default: "30"
    description: "Per-layer angle variance in degrees (0 = all layers same angle, 180 = full variance)"
  - name: "particleDensity"
    type: "number"
    default: "3"
    description: "Particle density (lower = more spread out, higher = more dense)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FloatingParticles />
</Shader>
```

```jsx
<Shader>
  <FloatingParticles />
</Shader>
```

```svelte
<Shader>
  <FloatingParticles />
</Shader>
```

```tsx
<Shader>
  <FloatingParticles />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FloatingParticles', props: {} }
  ]
})
```
::
