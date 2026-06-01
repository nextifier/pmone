---
title: Telemetry
description: Anonymous usage data collection and how to disable it
icon: chart-simple
category: advanced
---

# Telemetry

Shaders collects anonymous usage data to help improve the library. This includes the domain where the shader is installed and runtime FPS data. No personal information is captured.

Telemetry is **automatically disabled** when running on `localhost` or in development environments.

To disable it explicitly in production:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader :disable-telemetry="true">
  <LinearGradient />
</Shader>
```

```jsx
<Shader disableTelemetry={true}>
  <LinearGradient />
</Shader>
```

```tsx
<Shader disableTelemetry={true}>
  <LinearGradient />
</Shader>
```

```tsx
<Shader disableTelemetry={true}>
  <LinearGradient />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  disableTelemetry: true,
  components: [
    { type: 'LinearGradient', props: {} }
  ]
})
```
::
