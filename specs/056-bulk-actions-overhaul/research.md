# Feature 056 — Phase 0 grep baseline

Recorded before any Phase 2 edit. Post-implementation gate T034 must return identical hit lists.

## grep-1: JS/JSX callers of the reference bulk thunks + api.updateAbility
```
src/js/abilities/components/AbilitiesList.jsx:336:			dispatch.bulkUpdateStatus(slugs, 'publish');
src/js/abilities/components/AbilitiesList.jsx:339:			dispatch.bulkUpdateStatus(slugs, 'draft');
src/js/abilities/components/AbilitiesList.jsx:367:				dispatch.bulkDeleteAbilities(slugs);
src/js/abilities/store/index.js:239:				const ability = await api.updateAbility(slug, data);
src/js/abilities/store/index.js:269:	bulkDeleteAbilities(slugs) {
src/js/abilities/store/index.js:284:	bulkUpdateStatus(slugs, status) {
src/js/abilities/store/index.js:289:					slugs.map((slug) => api.updateAbility(slug, { status }))
```

## grep-2: PHP references to the abilities-manager REST namespace
```
```

## grep-3: JS/JSX + vendor references to the composer wpb-ac REST base
```
```
