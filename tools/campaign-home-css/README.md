# campaign-home.css

`extract.mjs` loads the home page in headless Chrome at 375, 768 and 1200 px and writes `used.css`. That file holds the campaign.css rules that match something on the page, Chrome's CSS coverage of campaign.css, and the band's state variants (closed). GTM, GA4 and Clarity are blocked, so no analytics hit is sent.

```
cd tools/campaign-home-css && npm i puppeteer-core@23.5.0 && node extract.mjs https://animalwrites.in/
```

Copy `used.css` under the header of `plugins/animal-writes-core/assets/css/campaign-home.css`. Set `AW_CAMP_HOME_CSS_FOR` (campaign-tools.php) to the band's campaign slug. While the home band shows any other campaign, the full campaign.css loads as before.

The same script makes `campaign-record-home.css` (the community-dogs story, pattern `animal-writes/aw1-story`): `node extract.mjs https://animalwrites.in/ /campaign-record.css record-used`, then copy `record-used.css` under that file's header. While the pattern is missing from Home, the full campaign-record.css loads.
