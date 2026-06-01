---
title: Group
description: Container for organizing and composing child effects
category: Utilities
componentType: Filter/Effect
requiresChild: true
---

# Group

Container for organizing and composing child effects


::shader-preview{component="Group"}
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Group>
    <Circle />
  </Group>
</Shader>
```

```jsx
<Shader>
  <Group>
    <Circle />
  </Group>
</Shader>
```

```svelte
<Shader>
  <Group>
    <Circle />
  </Group>
</Shader>
```

```tsx
<Shader>
  <Group>
    <Circle />
  </Group>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Group', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
