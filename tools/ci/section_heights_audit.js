// Layout audit (docs/design-system.md, "Layout rules"). Paste into the browser console on a page.
// 1. Sections, with their padding: from 768 wide (tablet and desktop) OVER above min(720px, viewport height) and
//    FAIL above 800px; on phones only Home has a limit (one screen, 812 at 375x812).
// 2. Content blocks (cards, tiles, photo cards, columns, folds, table rows, form steps): OVER above 600px at any width.
// 3. WCAG 2.2 AA points that can be measured: swipe rows reachable by keyboard and named, targets at least 44x44px,
//    no sideways page scroll.
(() => {
  const name = (e) => (e.id ? '#' + e.id : '') + '.' + [...e.classList].filter((c) => /^(aw|kit|wp-block)-/.test(c)).slice(0, 3).join('.');
  const h = (e) => Math.round(e.getBoundingClientRect().height);
  const shown = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
  const root = document.querySelector('main') || document.body;
  const w = innerWidth;
  const wide = w >= 768;

  const secSel = '.kit-section, .alignfull, .aw-camp-section, .kit-hero, main section';
  const limit = wide ? Math.min(720, innerHeight) : (document.body.classList.contains('home') ? innerHeight : Infinity);
  const sections = [...root.querySelectorAll(secSel)]
    .filter((e) => !e.closest('header, footer') && !e.parentElement.closest(secSel) && shown(e))
    .map((e) => ({ section: name(e), height: h(e), result: wide && h(e) > 800 ? 'FAIL' : (h(e) > limit ? 'OVER' : '') }));

  const blockSel = '.kit-card, .aw-card, .kit-tile, .kit-photo-card, .wp-block-cover, .wp-block-column, details, tr, fieldset, .aw-camp-card, .aw-content';
  const blocks = [...root.querySelectorAll(blockSel)]
    .filter((e) => shown(e) && !e.matches('.kit-hero') && !e.parentElement.closest(blockSel) && h(e) > 600)
    .map((e) => ({ block: name(e) || e.tagName.toLowerCase(), height: h(e) }));

  const rows = [...root.querySelectorAll('*')].filter((e) => {
    if (!shown(e)) return false;
    const s = getComputedStyle(e);
    return /(auto|scroll)/.test(s.overflowX) && e.scrollWidth > e.clientWidth + 1;
  });
  const badRows = rows.filter((e) => {
    const keys = e.tabIndex >= 0 || e.querySelector('a[href], button, [tabindex]:not([tabindex="-1"])');
    const named = e.getAttribute('aria-label') || e.getAttribute('aria-labelledby') || e.closest('[aria-label], [aria-labelledby]');
    return !keys || !named;
  }).map((e) => ({ row: name(e) || e.tagName.toLowerCase(), keyboard: e.tabIndex >= 0 || !!e.querySelector('a[href], button'), named: !!(e.getAttribute('aria-label') || e.getAttribute('aria-labelledby')) }));

  const small = [...root.querySelectorAll('a[href], button, [role="button"], input:not([type="hidden"]), select, summary')]
    .filter((e) => shown(e) && !e.closest('p, li, td, dd, figcaption')) // links inside running text are exempt (WCAG 2.5.8 inline)
    .filter((e) => { const r = e.getBoundingClientRect(); return r.width < 44 || r.height < 44; })
    .map((e) => ({ target: (e.innerText || e.getAttribute('aria-label') || e.tagName).trim().slice(0, 40), size: Math.round(e.getBoundingClientRect().width) + 'x' + Math.round(e.getBoundingClientRect().height) }));

  console.table(sections);
  if (blocks.length) console.table(blocks);
  if (badRows.length) console.table(badRows);
  if (small.length) console.table(small);
  return {
    page: location.pathname, screen: w + 'x' + innerHeight, sectionLimit: limit,
    sectionsOver: sections.filter((r) => r.result === 'OVER').length, sectionsFail: sections.filter((r) => r.result === 'FAIL').length,
    blocksOver600: blocks.length, swipeRowsNotOk: badRows.length, smallTargets: small.length,
    sidewaysScroll: document.documentElement.scrollWidth > w,
  };
})();
