const http = require('http');
const fs = require('fs');
const path = require('path');

const s = http.createServer((req, res) => {
  let f = req.url === '/' ? '/index.html' : req.url;
  const fp = path.join('C:/Users/Lenovo/Desktop/Site', f);
  const ext = path.extname(fp);
  const types = {
    '.html': 'text/html',
    '.css': 'text/css',
    '.js': 'application/javascript',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon'
  };
  fs.readFile(fp, (err, data) => {
    if (err) {
      res.writeHead(404);
      res.end('Not Found');
      return;
    }
    res.writeHead(200, { 'Content-Type': types[ext] || 'text/plain' });
    res.end(data);
  });
});

s.listen(3000, () => console.log('Server running on http://localhost:3000'));
