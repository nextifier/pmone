---
title: Ascii
description: Convert imagery to ASCII character art
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Ascii

Convert imagery to ASCII character art


::shader-preview{component="Ascii"}
::

## Props

::props-table
---
props:
  - name: "characters"
    type: "string"
    default: "@%#*+=-:."
    description: "Characters ordered from dense to sparse. First character is used for bright areas, last for dark areas."
  - name: "cellSize"
    type: "number"
    default: "30"
    description: "Size of each ASCII character cell (normalized to 1080p reference, scales proportionally at other resolutions)"
  - name: "fontFamily"
    type: "\"Azeret Mono\" | \"Courier Prime\" | \"Cutive Mono\" | \"Fira Code\" | \"Geist Mono\" | \"IBM Plex Mono\" | \"JetBrains Mono\" | \"Major Mono Display\" | \"Martian Mono\" | \"Nova Mono\" | \"Press Start 2P\" | \"Roboto Mono\" | \"Share Tech Mono\" | \"Silkscreen\" | \"Source Code Pro\" | \"Space Mono\" | \"Syne Mono\" | \"VT323\" | \"Xanh Mono\""
    default: "JetBrains Mono"
    description: "Font family for characters"
  - name: "spacing"
    type: "number"
    default: "1"
    description: "Character size within each cell (1.0 = optimal size, 0.0 = smallest)"
  - name: "gamma"
    type: "number"
    default: "1"
    description: "Brightness curve adjustment. <1 brightens darks (more light characters), >1 darkens midtones (more dark characters). Use to better fit characters to image brightness range."
  - name: "alphaThreshold"
    type: "number"
    default: "0"
    description: "Pixels with alpha below this threshold become fully transparent."
  - name: "preserveAlpha"
    type: "boolean"
    default: "true"
    description: "When enabled, output alpha matches input alpha. When disabled, pixels above the alpha threshold become fully opaque."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Ascii>
    <Circle />
  </Ascii>
</Shader>
```

```jsx
<Shader>
  <Ascii>
    <Circle />
  </Ascii>
</Shader>
```

```svelte
<Shader>
  <Ascii>
    <Circle />
  </Ascii>
</Shader>
```

```tsx
<Shader>
  <Ascii>
    <Circle />
  </Ascii>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Ascii', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
