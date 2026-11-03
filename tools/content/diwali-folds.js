// Diwali campaign (aw_campaign 23202): sections within 720px with no sideways scrolling (owner, 3 Nov 2026: "manage it").
// Markup only, text unchanged. Pairs with the "Diwali sections within 720px" rules in campaign.css.
// 1. The 8 cards in What the law says and What to do, and when become core Details folds (.aw-camp-acc), the first of
//    each list open; each card's H3 becomes the fold's summary; anchors (#rules, #before, ...) and the open-state class
//    (aw-camp-open) are kept on the fold.
// 2. In the news and guides: the News query shows 4 (was 6); the 5th and 6th News items (same query, offset 4) and the
//    Guides query sit in a closed fold "More news and guides" under them.
// Run in the browser console on https://animalwrites.in/wp-admin/ as an admin. DRY = true checks and prints only.
// WordPress keeps the old content as a revision; the script prints the revision IDs after a write.
(async (DRY = true) => {
  const nonce = await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text();
  const h = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' };
  const url = '/wp-json/wp/v2/aw_campaign/23202';
  const raw = (await (await fetch(url + '?context=edit&_fields=content', { headers: h })).json()).content.raw;
  const stop = (why, x) => { console.error('Stopped, nothing written:', why, x || ''); return null; };

  // 1. Cards to folds.
  const cardRe = /<!-- wp:group (\{[^\n]*?"className":"aw-camp-card( aw-camp-open)?"[^\n]*?\}) -->\n<div(?: id="([^"]+)")? class="wp-block-group aw-camp-card(?: aw-camp-open)?">([\s\S]*?)<\/div>\n<!-- \/wp:group -->/g;
  const headRe = /<!-- wp:heading \{"level":3\} -->\n<h3 class="wp-block-heading">([\s\S]*?)<\/h3>\n<!-- \/wp:heading -->\n\n/;
  let n = 0;
  const openAt = [0, 3]; // first card of What the law says (3 cards) and of What to do (5 cards)
  let next = raw.replace(cardRe, (all, attrs, open, id, inner) => {
    const m = inner.match(headRe);
    if (!m) return all;
    const i = n++;
    const cls = 'aw-camp-acc' + (open || '');
    const a = { className: cls };
    if (id) a.anchor = id;
    if (openAt.includes(i)) a.showContent = true;
    return '<!-- wp:details ' + JSON.stringify(a) + ' -->\n<details' + (id ? ' id="' + id + '"' : '') + ' class="wp-block-details ' + cls + '"' + (openAt.includes(i) ? ' open' : '') + '><summary>' + m[1] + '</summary>'
      + inner.replace(m[0], '') + '</details>\n<!-- /wp:details -->';
  });
  if (n !== 8) return stop('expected 8 cards, found', n);

  // 2. News row: 4 News, the rest and the Guides in a closed fold.
  const qRe = id => new RegExp('<!-- wp:query \\{"queryId":' + id + ',[\\s\\S]*?<!-- \\/wp:query -->');
  const q1 = (next.match(qRe(91)) || [])[0];
  const q2 = (next.match(qRe(92)) || [])[0];
  if (!q1 || !q2) return stop('News or Guides query not found');
  if (!q1.includes('"perPage":6,') || !q1.includes('"offset":0,')) return stop('News query not as expected');
  if (!next.includes(q1 + '\n' + q2)) return stop('News and Guides queries are not next to each other');
  const q1a = q1.replace('"perPage":6,', '"perPage":4,');
  const q1b = q1.replace('"queryId":91,', '"queryId":95,').replace('"perPage":6,', '"perPage":2,').replace('"offset":0,', '"offset":4,');
  const fold = '<!-- wp:details {"className":"aw-camp-more-fold"} -->\n<details class="wp-block-details aw-camp-more-fold"><summary>More news and guides</summary>'
    + q1b + '\n' + q2 + '</details>\n<!-- /wp:details -->';
  next = next.replace(q1 + '\n' + q2, q1a + '\n' + fold);

  // Checks: the visible text is the same (only the fold summary words are new), and every card is a fold.
  const txt = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  const same = txt(next).replace(' More news and guides', '') === txt(raw);
  const folds = (next.match(/<!-- wp:details \{"className":"aw-camp-acc/g) || []).length;
  console.log({ cards: n, folds, newsFold: next.includes('aw-camp-more-fold'), textSame: same, lenBefore: raw.length, lenAfter: next.length });
  if (!same || folds !== 8) return stop('checks failed');
  if (DRY) { console.log('Dry run only: nothing written. Run with DRY = false to write.'); return { raw, next }; }

  const r = await fetch(url, { method: 'POST', headers: h, body: JSON.stringify({ content: next }) });
  const back = await (await fetch(url + '?context=edit&_fields=content,modified', { headers: h })).json();
  const revs = await (await fetch(url + '/revisions?context=edit&per_page=2&_fields=id,date,content', { headers: h })).json();
  console.log({ status: r.status, saved: back.content.raw === next, modified: back.modified,
    revisions: revs.map(x => ({ id: x.id, date: x.date, isNew: x.content.raw === next, isOld: x.content.raw === raw })) });
  return null;
})();
