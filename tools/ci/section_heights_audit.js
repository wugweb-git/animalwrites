// Section heights audit (docs/design-system.md, "Layout rules"). Paste into the browser console on a page.
// Lists every top-level section with its height, padding included, and marks it when it breaks the rule:
//   desktop (1024 wide and up): OVER above min(720px, viewport height); FAIL above 800px (the ceiling);
//   phones (under 768): Home only, OVER above one screen (812 at 375x812). Other pages have no phone limit.
// Also reports whether the page scrolls sideways.
(() => {
  const sel = '.kit-section, .alignfull, .aw-camp-section, .kit-hero, main section';
  const root = document.querySelector('main') || document.body;
  const all = [...root.querySelectorAll(sel)].filter((e) => !e.closest('header, footer') && !e.parentElement.closest(sel) && e.getBoundingClientRect().height > 0);
  const w = innerWidth;
  const desk = w >= 1024;
  const limit = desk ? Math.min(720, innerHeight) : (w < 768 && document.body.classList.contains('home') ? innerHeight : Infinity);
  const rows = all.map((e) => {
    const h = Math.round(e.getBoundingClientRect().height);
    const name = (e.id ? '#' + e.id : '') + '.' + [...e.classList].filter((c) => /^(aw|kit)-/.test(c)).slice(0, 3).join('.');
    return { section: name, height: h, result: desk && h > 800 ? 'FAIL' : (h > limit ? 'OVER' : '') };
  });
  console.table(rows);
  return { page: location.pathname, screen: w + 'x' + innerHeight, limit, sideways: document.documentElement.scrollWidth > w, over: rows.filter((r) => r.result === 'OVER').length, fail: rows.filter((r) => r.result === 'FAIL').length };
})();
