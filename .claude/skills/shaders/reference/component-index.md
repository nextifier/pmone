# Shaders Component Index

Prop schema for all 117 components (source of truth: the installed `shaders` package, extracted offline). Type legend: color, range, position, select, checkbox, text, map (dynamic prop driver). `d=` is the default.

## Blurs

- **AngularBlur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=20,0-100), center:position(d={"x":0.5,"y":0.5})
- **Blur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=50,0-200)
- **ChannelBlur** — Effect (requiresChild) [RTT]
  redIntensity:range|map(d=0,0-100), greenIntensity:range|map(d=20,0-100), blueIntensity:range|map(d=40,0-100)
- **DiffuseBlur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=30,0-100), edges:select(d=stretch)
- **LinearBlur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=30,0-100), angle:range|map(d=0,0-360)
- **ProgressiveBlur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=50,0-100), angle:range|map(d=0,0-360), center:position(d={"x":0,"y":0.5}), falloff:range|map(d=1,0-1)
- **TiltShift** — Effect (requiresChild) [RTT]
  intensity:range|map(d=50,0-100), width:range|map(d=0.3,0-1), falloff:range|map(d=0.3,0-1), angle:range|map(d=0,0-360), center:position(d={"x":0.5,"y":0.5})
- **ZoomBlur** — Effect (requiresChild) [RTT]
  intensity:range|map(d=30,0-100), center:position(d={"x":0.5,"y":0.5})

## Stylize

- **Ascii** — Effect (requiresChild) [RTT]
  characters:text(d=@%#*+=-:.), cellSize:range(d=30,8-100), fontFamily:select(d=JetBrains Mono), spacing:range|map(d=1,0-1), gamma:range|map(d=1,0.25-3), alphaThreshold:range(d=0,0-1), preserveAlpha:checkbox(d=true)
- **ChromaticAberration** — Effect (requiresChild) [RTT]
  strength:range|map(d=0.2,0-1), angle:range|map(d=0,0-360), redOffset:range|map(d=-1,-2-2), greenOffset:range|map(d=0,-2-2), blueOffset:range|map(d=1,-2-2)
- **ContourLines** — Effect (requiresChild)
  levels:range|map(d=5,2-30), lineWidth:range|map(d=2,0.5-5), softness:range(d=0,0-1), gamma:range|map(d=0.5,0.1-2), invert:checkbox(d=false), source:select(d=luminance), colorMode:select(d=source), lineColor:color(d=#000000), backgroundColor:color(d=transparent)
- **CRTScreen** — Effect (requiresChild) [RTT]
  pixelSize:range|map(d=128,8-128), colorShift:range|map(d=1,0-10), scanlineIntensity:range|map(d=0.3,0-1), scanlineFrequency:range|map(d=200,100-800), brightness:range|map(d=1,0.5-2), contrast:range|map(d=1,0.5-2), vignetteIntensity:range|map(d=1,0-1), vignetteRadius:range|map(d=0.5,0-1)
- **Dither** — Effect (requiresChild) [RTT]
  pattern:select(d=bayer4), pixelSize:range(d=4,1-20), threshold:range|map(d=0.5,0-1), spread:range|map(d=1,0-1), colorMode:select(d=custom), colorA:color(d=transparent), colorB:color(d=#ffffff)
- **DropShadow** — Effect (requiresChild) [RTT]
  color:color(d=#000000), distance:range|map(d=0.1,0-1), angle:range|map(d=135,0-360), blur:range|map(d=5,0-20), intensity:range|map(d=0.5,0-1), cutout:checkbox(d=false)
- **FilmGrain** — Effect (requiresChild)
  strength:range|map(d=0.5,0-1), bias:range(d=2,0-10), animated:checkbox(d=false)
- **Glitch** — Effect (requiresChild) [RTT]
  intensity:range|map(d=0.5,0-1), speed:range(d=1,0.1-5), rgbShift:range|map(d=5,0-20), blockDensity:range|map(d=10,2-50), colorBarIntensity:range|map(d=0.2,0-1), mirrorAmount:range|map(d=0.3,0-1), scanlineIntensity:range|map(d=0.2,0-1)
- **Glow** — Effect (requiresChild) [RTT]
  intensity:range|map(d=1,0-50), threshold:range|map(d=0.5,0-1), size:range|map(d=25,0-100)
- **Halftone** — Effect (requiresChild) [RTT]
  style:select(d=classic), frequency:range|map(d=100,10-300), angle:range|map(d=45,0-360), cyanAngle:range|map(d=15,0-360), magentaAngle:range|map(d=75,0-360), yellowAngle:range|map(d=0,0-360), blackAngle:range|map(d=45,0-360), misprint:range|map(d=0,0-0.01), misprintAngle:range|map(d=0,0-360), paperColor:color(d=#ffffff), cyanColor:color(d=#00ffff), magentaColor:color(d=#ff00ff), yellowColor:color(d=#ffff00), blackColor:color(d=#000000)
- **LensFlare** — Generator
  lightPosition:position(d={"x":0.3,"y":0.3}), intensity:range|map(d=0.5,0-2), ghostIntensity:range|map(d=0.4,0-1), ghostSpread:range(d=0.7,0.1-2), ghostChroma:range|map(d=0.3,0-1), haloIntensity:range|map(d=0.4,0-1), haloRadius:range(d=0.6,0.1-1), haloChroma:range|map(d=0.6,0-1), haloSoftness:range|map(d=0.8,0.01-3), starburstIntensity:range|map(d=0.3,0-1), starburstPoints:range|map(d=6,4-16), streakIntensity:range|map(d=0.15,0-1), streakLength:range|map(d=0.5,0.1-1), glareIntensity:range|map(d=0.2,0-1), glareSize:range|map(d=0.5,0.1-1), edgeFade:range|map(d=0.2,0-1), speed:range(d=0.5,0-3)
- **Paper** — Effect (requiresChild) [RTT]
  roughness:range(d=0.3,0-1), grainScale:range(d=1,0.1-3), displacement:range(d=0.15,0-1), seed:range(d=0,0-100)
- **Pixelate** — Effect (requiresChild) [RTT]
  scale:range|map(d=50,1-200), gap:range|map(d=0,0-0.95), roundness:range|map(d=0,0-1)
- **ReflectivePlane** — Effect (requiresChild) [RTT]
  height:range|map(d=0.7,0-1), distance:range|map(d=0.5,0.01-1), falloff:range|map(d=0.5,0-3), blur:range|map(d=3,0-5), blurDistance:range(d=0.3,0.01-1), edges:select(d=stretch)
- **VHS** — Effect (requiresChild) [RTT]
  wobble:range|map(d=1,0-5), scanlineNoise:range|map(d=0.6,0-1), smear:range|map(d=0.2,-2-2), speed:range(d=1,0.1-3)
- **Vignette** — Effect (requiresChild)
  color:color(d=#000000), center:position(d={"x":0.5,"y":0.5}), radius:range|map(d=0.5,0-1.5), falloff:range|map(d=0.5,0.01-1.5), intensity:range|map(d=1,0-1)

## Textures

- **Aurora** — Generator
  colorA:color(d=#a533f8), colorB:color(d=#22ee88), colorC:color(d=#1694e8), colorSpace:select(d=linear), balance:range|map(d=50,0-100), intensity:range|map(d=80,0-100), curtainCount:range(d=4,1-4), speed:range(d=5,-10-10), waviness:range|map(d=50,0-200), rayDensity:range|map(d=20,0-100), height:range|map(d=120,10-200), center:position(d={"x":0.5,"y":0}), seed:range|map(d=0,0-100)
- **Beam** — Generator
  startPosition:position(d={"x":0.2,"y":0.5}), endPosition:position(d={"x":0.8,"y":0.5}), startThickness:range|map(d=0.2,0-2), endThickness:range|map(d=0.2,0-2), startSoftness:range|map(d=0.5,0-50), endSoftness:range|map(d=0.5,0-20), insideColor:color(d=#FF0000), outsideColor:color(d=#0000FF), colorSpace:select(d=linear)
- **Blob** — Generator
  colorA:color(d=#ff6b35), colorB:color(d=#e91e63), size:range(d=0.5,0-2), deformation:range(d=0.5,0-1), softness:range|map(d=0.5,0-1), highlightIntensity:range|map(d=0.5,0-1), highlightX:range(d=0.3,-1-1), highlightY:range(d=-0.3,-1-1), highlightZ:range(d=0.4,-1-1), highlightColor:color(d=#ffe11a), speed:range|map(d=0.5,0-2), seed:range(d=1,0-100), center:position(d={"x":0.5,"y":0.5}), colorSpace:select(d=linear)
- **BrickPattern** — Generator
  colorBrick:color(d=#000000), colorMortar:color(d=#ffffff), cellsX:range|map(d=8,1-30), cellsY:range|map(d=10,1-30), mortar:range|map(d=0.05,0-1), angle:range|map(d=0,-180-180), speed:range(d=0,-2-2), offset:range|map(d=0,0-1), speedVariance:range|map(d=0,0-1), seed:range(d=0,0-100), colorSpace:select(d=linear)
- **Checkerboard** — Generator
  colorA:color(d=#cccccc), colorB:color(d=#999999), cells:range|map(d=8,1-50), softness:range|map(d=0,0-1), colorSpace:select(d=linear)
- **Chevron** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), count:range|map(d=5,1-30), angle:range|map(d=0,-180-180), balance:range|map(d=0.5,0-1), softness:range|map(d=0,0-1), speed:range(d=0,-2-2), offset:range|map(d=0,0-1), colorSpace:select(d=linear)
- **ColorWheel** — Generator
  mode:select(d=rainbow), colorA:color(d=#ff0000), colorB:color(d=#00ff88), colorC:color(d=#0066ff), scale:range|map(d=1,0.1-10), angle:range|map(d=0,-180-180), speed:range(d=0.05,-1-1), colorSpace:select(d=oklch)
- **ConicGradient** — Generator
  colorA:color(d=#FF0080), colorB:color(d=#00BFFF), center:position(d={"x":0.5,"y":0.5}), rotation:range|map(d=0,0-360), repeat:range|map(d=1,1-24), colorSpace:select(d=oklch)
- **DiamondGradient** — Generator
  colorA:color(d=#4ffb4a), colorB:color(d=#4f1238), center:position(d={"x":0.5,"y":0.5}), size:range|map(d=0.7,0.01-2), rotation:range|map(d=0,0-360), repeat:range|map(d=1,1-16), roundness:range|map(d=0,0-1), colorSpace:select(d=oklch)
- **DOMTexture** — Generator
  
- **DotGrid** — Generator
  color:color(d=#ffffff), density:range(d=30,1-200), dotSize:range|map(d=0.3,0-1), twinkle:range|map(d=0,0-1)
- **FallingLines** — Generator
  colorA:color(d=#ffffff), colorB:color(d=#ffffff00), colorSpace:select(d=linear), angle:range(d=90,0-360), speed:range(d=0.5,0-3), speedVariance:range(d=0.3,0-1), density:range(d=15,1-60), trailLength:range|map(d=0.35,0.01-1), balance:range|map(d=0.5,0-1), strokeWidth:range|map(d=0.15,0.02-1), rounding:range|map(d=1,0-1)
- **FloatingParticles** — Generator
  randomness:range|map(d=0.25,0-1), speed:range(d=0.25,0-1), angle:range(d=90,0-360), particleSize:range|map(d=2,0.1-20), particleSoftness:range|map(d=0,0-5), twinkle:range|map(d=0.5,0-1), count:range(d=5,1-5), particleColor:color(d=#ffffff), speedVariance:range(d=0.3,0-1), angleVariance:range(d=30,0-180), particleDensity:range(d=3,0.5-3)
- **FlowingGradient** — Generator
  colorA:color(d=#0a0015), colorB:color(d=#6b17e6), colorC:color(d=#ff4d6a), colorD:color(d=#ff6b35), colorSpace:select(d=oklch), speed:range(d=1,0-10), distortion:range|map(d=0.5,0-2), seed:range|map(d=0,0-100)
- **FractalNoise** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), octaves:range(d=4,1-8), detail:range|map(d=2,1-4), contrast:range|map(d=0.5,0.1-1), speed:range(d=0.15,0-1), angle:range|map(d=0,-180-180), seed:range|map(d=0,0-100), colorSpace:select(d=linear)
- **Godrays** — Generator
  center:position(d={"x":0,"y":0}), density:range|map(d=0.3,0-1), intensity:range|map(d=0.8,0-1), spotty:range|map(d=1,0-1), speed:range(d=0.5,0-2), rayColor:color(d=#4283fb), backgroundColor:color(d=transparent)
- **Grid** — Generator
  color:color(d=#ffffff), cells:range|map(d=10,1-50), thickness:range|map(d=1,0-20), rotation:range|map(d=0,0-360)
- **HexGrid** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), cells:range|map(d=8,1-40), thickness:range|map(d=1,0-10), colorSpace:select(d=linear)
- **ImageTexture** — Generator
  url:image-upload(d=https://shaders.com/sample.jpg), objectFit:select(d=cover)
- **LinearGradient** — Generator
  colorA:color(d=#1aff00), colorB:color(d=#0000ff), start:position(d={"x":0,"y":0.5}), end:position(d={"x":1,"y":0.5}), angle:range|map(d=0,0-360), edges:select(d=stretch), colorSpace:select(d=linear)
- **Marble** — Generator
  colorA:color(d=#ffffff), colorB:color(d=#3a2d54), colorC:color(d=#0f0f0f), scale:range|map(d=2,0.1-10), turbulence:range|map(d=10,0-50), speed:range(d=0.05,0-0.25), seed:range|map(d=0,0-100), colorSpace:select(d=linear)
- **MultiPointGradient** — Generator
  colorA:color(d=#4776E6), positionA:position(d={"x":0.2,"y":0.2}), colorB:color(d=#C44DFF), positionB:position(d={"x":0.8,"y":0.2}), colorC:color(d=#1ABC9C), positionC:position(d={"x":0.2,"y":0.8}), colorD:color(d=#F8BBD9), positionD:position(d={"x":0.8,"y":0.8}), colorE:color(d=#FF8C42), positionE:position(d={"x":0.5,"y":0.5}), smoothness:range|map(d=2,0-5)
- **Plasma** — Generator
  density:range|map(d=2,0-4), speed:range(d=2,0-5), intensity:range|map(d=1.5,0.1-3), warp:range(d=0.4,0-1), contrast:range|map(d=1,0-3), balance:range|map(d=50,0-100), colorA:color(d=#7018be), colorB:color(d=#000000), colorSpace:select(d=linear)
- **RadialGradient** — Generator
  colorA:color(d=#ff0000), colorB:color(d=#0000ff), center:position(d={"x":0.5,"y":0.5}), radius:range|map(d=1,0-2), repeat:range|map(d=1,1-20), aspect:range|map(d=1,0.1-4), skewAngle:range|map(d=0,0-360), colorSpace:select(d=linear)
- **Ripples** — Generator
  center:position(d={"x":0.5,"y":0.5}), colorA:color(d=#ffffff), colorB:color(d=#000000), speed:range(d=1,-5-5), frequency:range|map(d=20,1-80), softness:range|map(d=0,0-3), thickness:range|map(d=0.5,0-1), phase:range|map(d=0,0-6.28)
- **SimplexNoise** — Generator
  colorA:color(d=#ffffff), colorB:color(d=#000000), colorSpace:select(d=linear), scale:range(d=2,-2-5), balance:range|map(d=0,-1-1), contrast:range|map(d=0,-2-5), seed:range(d=0,0-100), speed:range(d=1,0-5)
- **SineWave** — Generator
  color:color(d=#ffffff), amplitude:range|map(d=0.15,0-1), frequency:range|map(d=1,0.1-20), speed:range(d=1,-5-5), angle:range|map(d=0,0-360), position:position(d={"x":0.5,"y":0.5}), thickness:range|map(d=0.2,0-2), softness:range|map(d=0.4,0-1)
- **SolidColor** — Generator
  color:color(d=#5b18ca)
- **Spiral** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), strokeWidth:range|map(d=0.5,0-2), strokeFalloff:range|map(d=0,0-1), softness:range|map(d=0,0-1), speed:range(d=1,-3-3), center:position(d={"x":0.5,"y":0.5}), scale:range|map(d=1,0.1-5), colorSpace:select(d=linear)
- **Strands** — Generator
  speed:range(d=0.5,0-1), amplitude:range|map(d=1,0-5), frequency:range|map(d=1,0-5), lineCount:range(d=12,4-32), lineWidth:range|map(d=0.1,0-1), waveColor:color(d=#f1c907), pinEdges:checkbox(d=true), start:position(d={"x":0,"y":0.5}), end:position(d={"x":1,"y":0.5})
- **Stripes** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), angle:range|map(d=45,-180-180), density:range|map(d=5,1-30), balance:range|map(d=0.5,0-1), softness:range|map(d=0,0-1), speed:range(d=0.2,-1-1), offset:range|map(d=0,0-1), colorSpace:select(d=linear)
- **StudioBackground** — Generator
  color:color(d=#d8dbec), keyColor:color(d=#d5e4ea), keyIntensity:range|map(d=40,0-100), keySoftness:range|map(d=50,0-100), fillColor:color(d=#d5e4ea), fillIntensity:range|map(d=10,0-100), fillSoftness:range|map(d=70,0-100), fillAngle:range|map(d=70,0-100), backColor:color(d=#c8d4e8), backIntensity:range|map(d=20,0-100), backSoftness:range|map(d=80,0-100), brightness:range|map(d=20,0-100), vignette:range|map(d=0,0-100), center:position(d={"x":0.5,"y":0.8}), lightTarget:range|map(d=100,0-100), wallCurvature:range|map(d=10,0-100), ambientIntensity:range|map(d=50,0-100), ambientSpeed:range(d=2,-5-5), seed:range(d=0,0-100)
- **SunBurst** — Generator
  color:color(d=#ffdd88), background:color(d=#000000), center:position(d={"x":0.5,"y":0.5}), rayCount:range(d=12,3-64), softness:range|map(d=0.3,0-1), radius:range|map(d=0.8,0-1.2), feather:range|map(d=0.5,0-5), speed:range(d=0.2,-2-2)
- **Swirl** — Generator
  colorA:color(d=#1275d8), colorB:color(d=#e19136), speed:range(d=1,0-5), detail:range|map(d=1,0-5), blend:range|map(d=50,0-100), colorSpace:select(d=linear)
- **Truchet** — Generator
  colorA:color(d=#000000), colorB:color(d=#ffffff), cells:range|map(d=10,2-40), thickness:range|map(d=2,0-20), seed:range|map(d=0,0-100), colorSpace:select(d=linear)
- **VideoTexture** — Generator
  url:video-upload(d=https://shaders.com/sample.mp4), objectFit:select(d=cover), loop:checkbox(d=true)
- **Voronoi** — Generator
  colorA:color(d=#3186cf), colorB:color(d=#fc02dd), colorBorder:color(d=#000000), scale:range|map(d=6,1-20), speed:range(d=0.5,0-5), seed:range|map(d=0,0-100), edgeIntensity:range|map(d=0.5,0-1), edgeSoftness:range|map(d=0.05,0-0.4), colorSpace:select(d=oklch)
- **Weave** — Generator
  colorA:color(d=#c4c4c4), colorB:color(d=#4d4d4d), cells:range|map(d=10,2-40), gap:range|map(d=0.25,0-0.45), rotation:range|map(d=0,0-360)
- **WebcamTexture** — Generator
  objectFit:select(d=cover), mirror:checkbox(d=true)
- **WorleyNoise** — Generator
  colorA:color(d=#ffffff), colorB:color(d=#000000), colorSpace:select(d=linear), scale:range|map(d=6,1-30), mode:select(d=f1), distance:select(d=euclidean), octaves:range(d=1,1-4), lacunarity:range|map(d=2,1.5-4), persistence:range|map(d=0.5,0-1), jitter:range|map(d=1,0-1), contrast:range|map(d=1,0.25-4), balance:range|map(d=0,-1-1), seed:range|map(d=0,0-100), speed:range(d=0.5,0-5)

## Distortions

- **BarShift** — Effect (requiresChild) [RTT]
  count:range|map(d=6,1-30), angle:range|map(d=0,-180-180), intensity:range|map(d=0.15,0-1), seed:range(d=0,0-100), speed:range(d=0,-2-2), edges:select(d=mirror)
- **Bulge** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), strength:range|map(d=1,-1-1), radius:range|map(d=1,0-5), falloff:range|map(d=0.5,0-1), edges:select(d=stretch)
- **ConcentricSpin** — Effect (requiresChild) [RTT]
  intensity:range|map(d=20,0-100), rings:range|map(d=8,1-30), smoothness:range|map(d=0.03,0-1), seed:range(d=0,0-100), speed:range(d=0.1,-5-5), speedRandomness:range|map(d=0.5,0-1), edges:select(d=mirror), center:position(d={"x":0.5,"y":0.5})
- **FlowField** — Effect (requiresChild) [RTT]
  strength:range|map(d=0.15,0-0.5), detail:range|map(d=2,0.5-5), speed:range(d=0,0-20), evolutionSpeed:range(d=0,0-20), edges:select(d=mirror)
- **FlutedGlass** — Effect (requiresChild) [RTT]
  shape:select(d=bars), angle:range|map(d=0,0-360), frequency:range|map(d=10,1-20), softness:range|map(d=0.5,0-1), waveAmplitude:range|map(d=0.06,0-0.5), waveFrequency:range|map(d=1.5,0.1-10), speed:range(d=0,-1-1), refraction:range|map(d=1.5,0-4), aberration:range|map(d=0.2,0-1), lightAngle:range(d=30,-90-90), highlight:range|map(d=0.2,0-2), highlightSoftness:range|map(d=0.3,0-1), highlightColor:color(d=#ffffff), edges:select(d=mirror)
- **Form3D** — Effect (requiresChild) [RTT]
  shape3d:shape3d(d={"type":"ribbon","angle":0,"twist":50,"width":40,"thickness":20,"seed":0}), shape3dType:?(d=ribbon), center:position(d={"x":0.5,"y":0.5}), zoom:range|map(d=50,10-200), glossiness:range|map(d=50,0-200), lighting:range|map(d=50,0-200), uvMode:select(d=stretch), speed:range(d=1,-10-10)
- **GlassTiles** — Effect (requiresChild) [RTT]
  intensity:range|map(d=2,0-10), tileCount:range|map(d=20,5-50), rotation:range|map(d=0,0-360), roundness:range|map(d=0,0-1)
- **Kaleidoscope** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), segments:range|map(d=6,2-24), angle:range|map(d=0,0-360), edges:select(d=mirror)
- **Mirror** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), angle:range|map(d=0,0-360), edges:select(d=mirror)
- **Perspective** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), pan:range|map(d=0,-90-90), tilt:range|map(d=0,-90-90), fov:range|map(d=60,30-120), zoom:range|map(d=1,0.5-3), offset:position(d={"x":0.5,"y":0.5}), edges:select(d=transparent)
- **PolarCoordinates** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), wrap:range|map(d=1,0-2), radius:range|map(d=1,0-2), intensity:range|map(d=1,0-1), edges:select(d=transparent)
- **RectangularCoordinates** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), scale:range|map(d=1,0.1-3), intensity:range|map(d=1,0-1), edges:select(d=transparent)
- **Spherize** — Effect (requiresChild) [RTT]
  radius:range|map(d=1,0.1-3), depth:range|map(d=1,0-3), center:position(d={"x":0.5,"y":0.5}), lightPosition:position(d={"x":0.3,"y":0.3}), lightIntensity:range|map(d=0.5,0-1), lightSoftness:range|map(d=0.5,0-1), lightColor:color(d=#ffffff)
- **Stretch** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), strength:range|map(d=1,0-1), angle:range|map(d=0,0-360), falloff:range|map(d=0,0-1), edges:select(d=stretch)
- **Twirl** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), intensity:range|map(d=1,-5-5), edges:select(d=stretch)
- **WaveDistortion** — Effect (requiresChild) [RTT]
  strength:range|map(d=0.3,0-1), frequency:range|map(d=1,0.1-10), speed:range(d=1,0-5), angle:range|map(d=0,0-360), waveType:select(d=sine), edges:select(d=stretch)

## Adjustments

- **BrightnessContrast** — Effect (requiresChild)
  brightness:range|map(d=0,-1-1), contrast:range|map(d=0,-1-1)
- **Duotone** — Effect (requiresChild)
  colorA:color(d=#ff0000), colorB:color(d=#023af4), blend:range|map(d=0.5,0-1), colorSpace:select(d=linear)
- **Grayscale** — Effect (requiresChild)
  
- **HueShift** — Effect (requiresChild)
  shift:range|map(d=0,-180-180)
- **Invert** — Effect (requiresChild)
  
- **Posterize** — Effect (requiresChild)
  intensity:range|map(d=5,2-20)
- **Saturation** — Effect (requiresChild)
  intensity:range|map(d=1,0-3)
- **Sharpness** — Effect (requiresChild) [RTT]
  sharpness:range|map(d=0,0-5)
- **Solarize** — Effect (requiresChild)
  threshold:range|map(d=0.5,0-1), strength:range|map(d=1,0-1)
- **Tint** — Effect (requiresChild)
  color:color(d=#ff8800), amount:range|map(d=0.5,0-1), preserveLuminosity:checkbox(d=true)
- **Tritone** — Effect (requiresChild)
  colorA:color(d=#ce1bea), colorB:color(d=#2fff00), colorC:color(d=#ffff00), blendMid:range|map(d=0.5,0-1), colorSpace:select(d=linear)
- **Vibrance** — Effect (requiresChild)
  intensity:range|map(d=0,-2-2)

## Interactive

- **ChromaFlow** — Generator
  baseColor:color(d=#0066ff), upColor:color(d=#00ff00), downColor:color(d=#ff0000), leftColor:color(d=#0000ff), rightColor:color(d=#ffff00), intensity:range(d=1,0.5-1.5), radius:range(d=3,0-5), momentum:range(d=30,10-60)
- **CursorRipples** — Effect (requiresChild) [RTT]
  intensity:range|map(d=10,0-20), decay:range(d=10,0-20), radius:range(d=0.5,0.1-1), chromaticSplit:range|map(d=1,0-3), edges:select(d=stretch)
- **CursorTrail** — Generator
  colorA:color(d=#00aaff), colorB:color(d=#ff00aa), radius:range(d=0.5,0.5-2), length:range(d=0.5,0.1-2), shrink:range(d=1,0-1), colorSpace:select(d=linear)
- **Fog** — Generator
  colorA:color(d=#e0e0e0), colorB:color(d=#888888), seed:range(d=0,0-999), speed:range(d=1,0.1-3), turbulence:range(d=1,0-3), detail:range(d=15,0-50), blending:range(d=0.3,0-1), mouseInfluence:range(d=0.1,0-2), mouseRadius:range(d=0.1,0.02-0.5), colorSpace:select(d=linear)
- **GridDistortion** — Effect (requiresChild) [RTT]
  intensity:range(d=1,0-5), decay:range(d=3,0-10), radius:range(d=1,0-3), gridSize:range|map(d=20,8-128), edges:select(d=stretch)
- **Liquify** — Effect (requiresChild) [RTT]
  intensity:range|map(d=10,0-20), stiffness:range(d=3,1-30), damping:range(d=3,0-10), radius:range(d=1,0.1-1.5), edges:select(d=stretch)
- **Shatter** — Effect (requiresChild) [RTT]
  crackWidth:range|map(d=1,0.5-5), intensity:range(d=4,0-20), radius:range(d=0.4,0.1-1), decay:range(d=1,0.1-10), seed:range(d=2,0-50), chromaticSplit:range|map(d=1,0-5), refractionStrength:range|map(d=5,0-10), shardLighting:range(d=0.1,0-0.5), edges:select(d=mirror)
- **Smoke** — Generator
  colorA:color(d=#fc83f9), colorB:color(d=#c21c79), emitFrom:position(d={"x":0.5,"y":1}), direction:range(d=0,0-360), speed:range(d=20,0.1-50), spread:range(d=60,0-180), emitRadius:range(d=0.08,0.01-0.3), intensity:range(d=1,0.1-1), dissipation:range(d=0.2,0.1-3), detail:range(d=25,0-50), gravity:range(d=0.5,-2-2), colorDecay:range(d=0.4,0-3), mouseInfluence:range(d=0.1,0-2), mouseRadius:range(d=0.1,0.02-0.5), colorSpace:select(d=linear)

## Shapes

- **Circle** — Generator
  color:color(d=#ffffff), radius:range|map(d=1,0-2), softness:range|map(d=0,0-1), center:position(d={"x":0.5,"y":0.5}), strokeThickness:range|map(d=0,0-0.5), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Crescent** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.3,0.05-1), innerRatio:range(d=0.8,0.3-1.2), offset:range(d=0.2,0.01-0.5), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Cross** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.35,0-1), thickness:range(d=0.08,0.01-0.5), rounding:range(d=0,0-0.2), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Ellipse** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radiusX:range(d=0.35,0.01-1), radiusY:range(d=0.2,0.01-1), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Flower** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.4,0-1), sides:range(d=5,3-12), innerRatio:range(d=0.4,0.1-0.95), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Polygon** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.4,0-1), sides:range(d=6,3-12), rounding:range(d=0,0-1), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Ring** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.3,0-1), thickness:range(d=0.07,0.005-0.3), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.1), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **RoundedRect** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), width:range(d=0.35,0.01-1), height:range(d=0.25,0.01-1), rounding:range(d=0.05,0-0.5), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Star** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.4,0-1), sides:range(d=5,3-12), innerRatio:range(d=0.4,0.1-0.9), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Trapezoid** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), bottomWidth:range(d=0.35,0.01-1), topWidth:range(d=0.2,0.01-1), height:range(d=0.25,0.01-1), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)
- **Vesica** — Generator
  color:color(d=#ffffff), center:position(d={"x":0.5,"y":0.5}), radius:range(d=0.35,0.05-1), spread:range(d=0.5,0.05-0.95), rotation:range(d=0,0-360), softness:range(d=0,0-0.1), strokeThickness:range(d=0,0-0.2), strokeColor:color(d=#000000), strokePosition:select(d=center), colorSpace:select(d=linear)

## Shape Effects

- **Crystal** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), scale:range(d=1,0.1-3), cutout:checkbox(d=false), refraction:range(d=0.5,0-3), dispersion:range(d=0.5,0-2), facets:range(d=5,3-24), fresnel:range(d=0.05,0-1), fresnelSoftness:range(d=1,0-2), fresnelColor:color(d=#ffffff), edgeSoftness:range(d=0,0-1), innerZoom:range(d=1.5,0.5-3), lightAngle:range(d=270,0-360), highlights:range(d=0.5,0-2), shadows:range(d=0.3,0-1), brightness:range(d=1.2,0.5-3), tintColor:color(d=#e8e0ff), tintIntensity:range(d=0,0-1), tintPreserveLuminosity:checkbox(d=true), shape:shape(d={"type":"polygonSDF","radius":0.35,"sides":10}), shapeSdfUrl:?(d=), shapeType:?(d=)
- **Emboss** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), scale:range(d=1,0.1-3), depth:range(d=-0.5,-1-1), lightAngle:range|map(d=260,0-360), lightIntensity:range|map(d=0.6,0-2), shadowIntensity:range|map(d=0.3,0-1), shape:shape(d={"type":"circleSDF","radius":0.35}), shapeSdfUrl:?(d=), shapeType:?(d=)
- **Glass** — Effect (requiresChild) [RTT]
  center:position(d={"x":0.5,"y":0.5}), scale:range|map(d=1,0.1-3), cutout:checkbox(d=false), refraction:range|map(d=1,0-2), edgeSoftness:range|map(d=0.1,0-1), blur:range(d=0,0-20), thickness:range|map(d=0.2,0-1), aberration:range|map(d=0.5,0-1), innerZoom:range|map(d=1,0.5-3), lightAngle:range(d=300,0-360), highlight:range|map(d=0.05,0-2), highlightColor:color(d=#ffffff), highlightSoftness:range|map(d=0.5,0-1), fresnel:range|map(d=0.1,0-1), fresnelSoftness:range|map(d=0.1,0-1), fresnelColor:color(d=#ffffff), tintColor:color(d=#ffffff), tintIntensity:range|map(d=0,0-1), tintPreserveLuminosity:checkbox(d=true), shape:shape(d={"type":"circleSDF","radius":0.35}), shapeSdfUrl:?(d=), shapeType:?(d=)
- **Neon** — Generator
  center:position(d={"x":0.5,"y":0.5}), scale:range(d=1,0.1-3), color:color(d=#00ddff), secondaryColor:color(d=#ff00aa), secondaryBlend:range(d=0.5,0-1), glowColor:color(d=#00ddff), tubeThickness:range(d=0.2,0-1), intensity:range(d=1.5,0.5-4), hotCoreIntensity:range(d=0.6,0-1), glowIntensity:range(d=0.6,0-2), glowRadius:range(d=0.25,0.01-1), lightAngle:range(d=300,0-360), specularIntensity:range(d=0.5,0-2), specularSize:range(d=0.5,0-1), cornerSmoothing:range(d=0.15,0-1), flickerSpeed:range(d=0,0-5), flickerAmount:range(d=0.2,0-1), flowSpeed:range(d=0,0-5), flowAmount:range(d=0.3,0-1), shape:shape(d={"type":"circleSDF","radius":0.35}), shapeSdfUrl:?(d=), shapeType:?(d=)
- **SmokeFill** — Generator
  colorA:color(d=#8cf3ff), colorB:color(d=#04a0d6), center:position(d={"x":0.5,"y":0.5}), scale:range(d=1,0.1-3), emitFrom:position(d={"x":0.5,"y":0.5}), direction:range(d=0,0-360), speed:range(d=10,0.1-30), spread:range(d=60,0-180), emitRadius:range(d=0.03,0.01-0.3), intensity:range(d=1,0.1-1), dissipation:range(d=0.3,0.1-5), detail:range(d=25,0-50), gravity:range(d=0.5,-2-2), colorDecay:range(d=0.4,0-3), mouseInfluence:range(d=0.1,0-2), mouseRadius:range(d=0.1,0.02-0.5), colorSpace:select(d=linear), shape:shape(d={"type":"circleSDF","radius":0.35}), shapeSdfUrl:?(d=), shapeType:?(d=)

## Utilities

- **Group** — Effect (requiresChild)
  
