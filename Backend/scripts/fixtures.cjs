/* Development fixtures from the approved prototype. Never imported on activation. */
const fs = require('fs'), path = require('path'), vm = require('vm');
const root = path.resolve(__dirname, '../../Site');
function array(name, marker) {
    const source = fs.readFileSync(path.join(root, 'assets/js', name), 'utf8');
    const start = source.indexOf('[', source.indexOf(marker));
    let depth = 0, quote = '', escape = false, end = start;
    for (; end < source.length; end++) {
        const c = source[end];
        if (quote) { if (escape) escape = false; else if (c === '\\') escape = true; else if (c === quote) quote = ''; continue; }
        if (c === '"' || c === "'") { quote = c; continue; }
        if (c === '[') depth++;
        if (c === ']' && --depth === 0) break;
    }
    return vm.runInNewContext('(' + source.slice(start, end + 1) + ')', {}, {timeout: 1000});
}
const text = s => (s || '').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').trim();
const capture = (s, pattern) => text(s.match(pattern)?.[1]);
const html = name => fs.readFileSync(path.join(root, name+'.html'), 'utf8');
const board = [...html('board').matchAll(/<article class="bmember">([\s\S]*?)<\/article>/g)].map((m,i) => ({
    id:i+1, title:capture(m[1], /class="bname">([\s\S]*?)<\/h3>/),
    position:capture(m[1], /class="brole">([\s\S]*?)<\/span>/),
    description:capture(m[1], /class="bbio">([\s\S]*?)<\/p>/),
    image:m[1].match(/<img src="([^"]+)"/)?.[1],
    content:capture(html('board').match(new RegExp('<article class="profile" id="profile-'+(i+1)+'"[\\s\\S]*?<\\/article>'))?.[0] || '', /class="profile-bio">([\s\S]*?)<\/p>/)
}));
const events = [...html('meetings').matchAll(/<article class="meet-card" data-status="([^"]+)"[^>]*>([\s\S]*?)<\/article>/g)].map((m,i) => ({
    id:i+1, title:capture(m[2], /<h3>([\s\S]*?)<\/h3>/), status:m[1],
    image:m[2].match(/<img src="([^"]+)"/)?.[1],
    description:capture(m[2], /<p>([\s\S]*?)<\/p>/),
    content:capture(m[2], /class="meet-summary">([\s\S]*?)<\/template>/),
    location:capture(m[2], /<span>📍 ([\s\S]*?)<\/span>/),
    date:'2026-09-'+String(13-i).padStart(2,'0'), time:'16:00'
}));
const data={news:array('news-data.js','window.NewsItems ='), companies:array('member-data.js','window.MemberDirectory ='), circulars:array('circulars.js','const documents ='), prices:array('approved-prices.js','const documents ='), board, events};
const out=process.argv[2];if(!out)throw new Error('Pass output path');
fs.writeFileSync(out, JSON.stringify(data, null, 2));
