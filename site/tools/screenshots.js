'use strict';
/* Captures home-page screenshots for the PWA manifest.
   Needs the site running on http://localhost:4000 (npm run dev). */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const OUT = path.join(__dirname, '..', 'src', 'assets', 'screenshots');
fs.mkdirSync(OUT, { recursive: true });

const SHOTS = [
  { name: 'home-wide.png',   width: 1280, height: 720, mobile: false },
  { name: 'home-mobile.png', width: 390,  height: 844, mobile: true  }
];

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  for (const s of SHOTS) {
    await page.setViewport({
      width: s.width, height: s.height, deviceScaleFactor: 1,
      isMobile: s.mobile, hasTouch: s.mobile
    });
    await page.goto('http://localhost:4000/', { waitUntil: 'networkidle0' });
    await page.screenshot({ path: path.join(OUT, s.name) });
    console.log('saved', s.name, s.width + 'x' + s.height);
  }
  await browser.close();
})();