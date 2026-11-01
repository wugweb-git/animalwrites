// Section heights audit (docs/design-system.md, "Section heights"). Paste into the browser console on a page.
// Lists every top-level section with its height; marks OVER when it breaks the rule for this screen:
//   desktop (1024 wide and up): at most min(720px, viewport height);
//   phones (under 768): Home sections fit one screen (812 at 375x812).
// Also reports whether the page scrolls sideways.
(() => {
  const sel = '.kit-section, .alignfull, .aw-camp-section, .kit-hero, main section';
  const root = document.querySelector('main') || document.body;
  const all = [...root.querySelectorAll(sel)].filter((e) => !e.closest('header, footer') && !e.parentElement.closest(sel) && e.getBoundingClientRect().height > 0);
  const w = innerWidth;
  const limit = w >= 1024 ? Math.min(720, innerHeight) : (w < 768 && document.body.classList.contains('home') ? innerHeight : Infinity);
  const rows = all.map((e) => {
    const h = Math.round(e.getBoundingClientRect().height);
    const name = (e.id ? '#' + e.id : '') + '.' + [...e.classList].filter((c) => /^(aw|kit)-/.test(c)).slice(0, 3).join('.');
    return { section: name, height: h, over: h > limit ? 'OVER' : '' };
  });
  console.table(rows);
  return { page: location.pathname, screen: w + 'x' + innerHeight, limit, sideways: document.documentElement.scrollWidth > w, over: rows.filter((r) => r.over).length };
})();
