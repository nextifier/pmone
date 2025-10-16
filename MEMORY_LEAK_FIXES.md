# Memory Leak Fixes - PM One Frontend

## Problem Summary
Application experiences memory leaks causing:
- Infinite loading after leaving app idle
- "Worker terminated due to reaching memory limit: JS heap out of memory" errors
- Application freezing in both development and production

## Root Causes Identified

### 1. useNow Composable - setInterval Leak
**File:** `frontend/app/composables/useNow.js`

**Issue:**
- Global `interval` and `activeInstances` variables in module scope
- Improper cleanup causing interval to persist after component unmount
- Counter could become out of sync with multiple component instances

**Fix Applied:**
- Moved interval to component scope
- Proper cleanup in onUnmounted hook
- Each component instance manages its own interval

### 2. defineShortcuts - Event Listener Leak
**File:** `frontend/app/composables/defineShortcuts.ts`

**Issue:**
- `useEventListener('keydown', onKeyDown)` never cleaned up
- Event listeners accumulate on every component mount
- Shortcut array not cleared on unmount

**Fix Applied:**
- Store cleanup function from useEventListener
- Call cleanup in onUnmounted hook
- Clear shortcuts array on unmount

### 3. FormProfile - Deep Watcher Leak
**File:** `frontend/app/components/FormProfile.vue`

**Issue:**
- Deep watcher on large object creates many reactive observers
- Watcher never stopped, continuing to observe even after unmount
- Deep watching images and nested objects is expensive

**Fix Applied:**
- Removed `deep: true` option (not needed since we watch the entire object)
- Store watcher stop function
- Explicitly stop watcher in onUnmounted

### 4. Memory Optimization Settings
**File:** `frontend/nuxt.config.ts`

**Added:**
- Manual chunk splitting for better bundle management
- Separate vendor chunks to reduce memory pressure
- Nitro minification for production builds

## How to Test

### Development
```bash
cd frontend
npm run dev
```

Leave the app idle for 10+ minutes, then navigate back. The app should load normally without freezing.

### Production Build
```bash
cd frontend
npm run build
npm run preview
```

## Additional Recommendations

### 1. Monitor Memory Usage
Use browser DevTools to monitor memory:
1. Open Chrome DevTools
2. Performance tab → Memory
3. Take heap snapshot before/after navigation
4. Look for detached DOM nodes

### 2. Check Other Components
Review components that use:
- `setInterval` / `setTimeout`
- Event listeners (`addEventListener`, `useEventListener`)
- Deep watchers
- Large reactive objects

### 3. Production Monitoring
Consider adding:
```js
// In a plugin or middleware
if (process.client && process.env.NODE_ENV === 'production') {
  setInterval(() => {
    if (performance.memory) {
      console.log('Memory:', {
        used: Math.round(performance.memory.usedJSHeapSize / 1048576),
        total: Math.round(performance.memory.totalJSHeapSize / 1048576),
        limit: Math.round(performance.memory.jsHeapSizeLimit / 1048576)
      });
    }
  }, 30000); // Every 30 seconds
}
```

## Files Modified

1. ✅ `frontend/app/composables/useNow.js` - Fixed interval leak
2. ✅ `frontend/app/composables/defineShortcuts.ts` - Fixed event listener leak
3. ✅ `frontend/app/components/FormProfile.vue` - Fixed deep watcher leak
4. ✅ `frontend/nuxt.config.ts` - Added memory optimization settings

## Next Steps

1. Deploy fixes to production
2. Monitor application for 24-48 hours
3. Check error logs for memory-related issues
4. If issues persist, profile specific pages/components
5. Consider lazy loading heavy components

## Note on NODE_OPTIONS

The `--max-old-space-size=4096` in package.json is a workaround, not a solution. With these fixes, you may be able to reduce this value or remove it entirely. Try testing with:

```json
"dev": "nuxt dev",
"build": "nuxt build"
```

If builds succeed without OOM errors, the memory leaks are fully resolved.
