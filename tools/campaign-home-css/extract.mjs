// Which campaign.css rules does the home page use? Union over 375 / 768 / 1200 px of:
//  (a) Chrome CSS coverage of campaign.css, and (b) style rules with a selector that matches an element on the page
//  (user-action pseudo-classes and pseudo-elements stripped, so :hover / ::before variants of used rules count),
//  plus (c) state variants of the band and countdown (selectors naming aw-camp-band or countdown) that JS may switch on.
// Writes used.css (rules in source order, @media / @supports kept around them, @keyframes and @font-face that
// used rules reference) and report.json.
import puppeteer from 'puppeteer-core';
import fs from 'fs';

const URL = process.argv[2] || 'https://animalwrites.in/';
const SHEET_ARG = process.argv[3] || '/campaign.css';
const OUT = process.argv[4] || 'used';
const browser = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new' });
const used = new Set();
const cov = {};
let ruleTexts = null;
for (const w of [375, 768, 1200]) {
  const page = await browser.newPage();
  await page.setUserAgent(w < 768 ? 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36' : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36');
  await page.setViewport({ width: w, height: 900, deviceScaleFactor: 1, isMobile: w < 768, hasTouch: w < 768 });
  await page.setRequestInterception(true);
  page.on('request', q => (/googletagmanager\.com|google-analytics\.com|analytics\.google\.com|doubleclick\.net|clarity\.ms/.test(q.url()) ? q.abort() : q.continue()));
  await page.coverage.startCSSCoverage();
  await page.goto(URL + '?cssx=' + w + '-' + Date.now(), { waitUntil: 'networkidle0', timeout: 90000 });
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
  await new Promise(r => setTimeout(r, 1500));
  const res = await page.evaluate((SHEET) => {
    const sheet = [...document.styleSheets].find(s => s.href && s.href.includes(SHEET));
    if (!sheet) return { error: 'sheet not on page' };
    const STATE = SHEET.includes('record') ? /aw-camp-record|aw-record/ : /aw-camp-band|countdown/;
    const STRIP = /::?(before|after|marker|placeholder|selection|backdrop|file-selector-button|-webkit-[a-z-]+|-moz-[a-z-]+)|:(hover|focus-visible|focus-within|focus|active|visited|link|target)(?![a-z-])/g;
    const out = [];
    let idx = 0;
    const walk = (rules, wrap) => {
      for (const r of rules) {
        const i = idx++;
        if (r.type === CSSRule.STYLE_RULE) {
          const sels = r.selectorText.split(/,(?![^()]*\))/).map(s => s.trim());
          let hit = false;
          for (const s of sels) {
            const t = s.replace(STRIP, '').replace(/\s+>?\s*$/, '').trim() || '*';
            try { if (document.querySelector(t)) { hit = true; break; } } catch (e) { hit = true; break; }
          }
          if (!hit && STATE.test(r.selectorText)) hit = 'state';
          out.push({ i, wrap, text: r.cssText, hit, sel: r.selectorText });
        } else if (r.cssRules && (r.type === CSSRule.MEDIA_RULE || r.type === CSSRule.SUPPORTS_RULE || r.type === 12 /* container */ || r.type === 18 /* layer block */)) {
          const head = r.cssText.slice(0, r.cssText.indexOf('{')).trim();
          out.push({ i, wrap, open: head });
          walk(r.cssRules, wrap.concat([head]));
        } else {
          out.push({ i, wrap, text: r.cssText, other: r.constructor.name, name: r.name || null });
        }
      }
    };
    walk(sheet.cssRules, []);
    return { out };
  }, SHEET_ARG);
  if (res.error) { console.error(w, res.error); process.exit(1); }
  const c = (await page.coverage.stopCSSCoverage()).find(e => e.url.includes(SHEET_ARG));
  cov[w] = c ? { bytes: c.text.length, usedBytes: c.ranges.reduce((s, r) => s + r.end - r.start, 0) } : null;
  ruleTexts = res.out;
  res.out.forEach(r => { if (r.hit) used.add(r.i); });
  // coverage cross-check: a rule whose text appears inside a covered range is used too
  if (c) {
    const covered = c.ranges.map(r => c.text.slice(r.start, r.end)).join('\n');
    res.out.forEach(r => { if (r.sel && !used.has(r.i)) { const s = r.sel.split(',')[0].trim(); if (covered.includes(s + ' {') || covered.includes(s + '{')) used.add(r.i); } });
  }
  await page.close();
}
// keyframes and font-faces referenced by used rules
const usedText = ruleTexts.filter(r => used.has(r.i)).map(r => r.text).join('\n');
ruleTexts.forEach(r => {
  if (r.other === 'CSSKeyframesRule' && r.name && usedText.includes(r.name)) used.add(r.i);
  if (r.other === 'CSSFontFaceRule' || r.other === 'CSSPropertyRule') used.add(r.i);
});
// rebuild in source order, with @media / @supports wrappers
let css = '';
for (const r of ruleTexts) {
  if (!used.has(r.i) || !r.text) continue;
  css += r.wrap.reduce((acc, w) => acc + w + ' { ', '') + r.text + ' }'.repeat(r.wrap.length) + '\n';
}
const total = ruleTexts.filter(r => r.text).length;
fs.writeFileSync(OUT + '.css', css);
fs.writeFileSync(OUT + '.json', JSON.stringify({ total_rules: total, used_rules: used.size, used_bytes: css.length, coverage: cov, state_rules: ruleTexts.filter(r => r.hit === 'state').map(r => r.sel) }, null, 1));
console.log(JSON.stringify({ total_rules: total, used_rules: used.size, used_bytes: css.length, coverage: cov }));
await browser.close();
