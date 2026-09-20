const fs = require('fs');
const path = require('path');

const destDir = path.join(__dirname, '..', 'dist', 'front-end');
const htaccessSrc = path.join(__dirname, '..', 'src', '.htaccess');
const htaccessDest = path.join(destDir, '.htaccess');
const indexDest = path.join(destDir, 'index.html');

if (!fs.existsSync(destDir)) {
  console.error('Build dist first (dist/front-end not found)');
  process.exit(1);
}

fs.copyFileSync(htaccessSrc, htaccessDest);
console.log('Copied .htaccess to dist/front-end');

if (fs.existsSync(indexDest)) {
  let html = fs.readFileSync(indexDest, 'utf8');
  html = html.replace(/\s+type="module"/g, '');
  html = html.replace(/rel="stylesheet" href="(\/?styles\.[^"]+\.css)" media="print" onload="this\.media='all'"/g,
    'rel="stylesheet" href="$1"');
  fs.writeFileSync(indexDest, html);
  console.log('Patched dist/front-end/index.html for classic scripts');
}
