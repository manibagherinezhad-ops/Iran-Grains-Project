#!/usr/bin/env python3
"""Read-only crawl of sitemap URLs and their internal discovery graph (no dependencies)."""
import argparse
from collections import Counter
from html.parser import HTMLParser
import json
from pathlib import Path
from urllib.parse import parse_qsl, unquote, urlencode, urljoin, urlsplit, urlunsplit
from urllib.request import urlopen
from urllib.error import HTTPError
from xml.etree import ElementTree as ET


class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.links = []
        self.h1 = 0
        self.titles = []
        self.meta = {}
        self.canonical = []
        self.title_depth = False
        self.ignored = 0

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if tag in ('script', 'style', 'template'):
            self.ignored += 1
        if self.ignored:
            return
        if tag == 'title':
            self.titles.append('')
            self.title_depth = True
        elif tag == 'h1':
            self.h1 += 1
        elif tag == 'a' and attrs.get('href'):
            self.links.append(attrs['href'])
        elif tag == 'link' and attrs.get('rel') == 'canonical':
            self.canonical.append(attrs.get('href', ''))
        elif tag == 'meta' and attrs.get('name'):
            self.meta.setdefault(attrs['name'], []).append(attrs.get('content', ''))

    def handle_endtag(self, tag):
        if tag in ('script', 'style', 'template'):
            self.ignored = max(0, self.ignored - 1)
        if tag == 'title':
            self.title_depth = False

    def handle_data(self, data):
        if self.title_depth and not self.ignored:
            self.titles[-1] += data


def key(url):
    parts = urlsplit(url)
    return urlunsplit((parts.scheme, parts.netloc.lower(), unquote(parts.path),
                       urlencode(sorted(parse_qsl(parts.query))), ''))


def audit(base, expect_indexable=False):
    namespace = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
    def get(url):
        try:
            with urlopen(url, timeout=30) as response:
                return response.status, response.read(), response.headers
        except HTTPError as error:
            return error.code, error.read(), error.headers
    status, index, _ = get(urljoin(base, 'wp-sitemap.xml'))
    if status != 200:
        raise RuntimeError('Sitemap index returned HTTP ' + str(status))
    root = ET.fromstring(index)
    sitemap_urls = root.findall('s:sitemap/s:loc', namespace)
    urls = []
    for entry in sitemap_urls:
        if urlsplit(entry.text).netloc != urlsplit(base).netloc:
            raise ValueError('Sitemap points to a different host: ' + entry.text)
        status, body, _ = get(entry.text)
        if status != 200:
            raise RuntimeError('Sitemap returned HTTP ' + str(status) + ': ' + entry.text)
        urls.extend(node.text for node in ET.fromstring(body).findall('s:url/s:loc', namespace))
    pages = {}
    issues = []
    for url in dict.fromkeys(urls):
        status, body, headers = get(url)
        page = Page()
        page.feed(body.decode('utf-8'))
        errors = []
        if status != 200:
            errors.append('http_status')
        if page.h1 != 1:
            errors.append('single_h1')
        if len(page.titles) != 1 or not page.titles[0].strip():
            errors.append('single_title')
        if len(page.meta.get('description', [])) != 1 or not page.meta['description'][0].strip():
            errors.append('description')
        if len(page.canonical) != 1 or key(page.canonical[0]) != key(url):
            errors.append('canonical')
        if expect_indexable and (any('noindex' in value.lower() for value in page.meta.get('robots', []))
                                 or 'noindex' in headers.get('X-Robots-Tag', '').lower()):
            errors.append('noindex')
        if errors:
            issues.append({'url': url, 'errors': errors})
        pages[key(url)] = {'url': url, 'title': ''.join(page.titles), 'bytes': len(body),
                           'links': [key(urljoin(url, link)) for link in page.links]}
    visited = set()
    queue = [key(base)]
    while queue:
        current = queue.pop()
        if current in visited or current not in pages:
            continue
        visited.add(current)
        queue.extend(pages[current]['links'])
    unreachable = [page['url'] for url, page in pages.items() if url not in visited]
    titles = Counter(page['title'] for page in pages.values())
    return {'pages': len(pages), 'issues': issues, 'unreachable': unreachable,
            'duplicate_titles': [title for title, count in titles.items() if count > 1],
            'largest_pages': sorted(({k: v for k, v in page.items() if k != 'links'}
                                      for page in pages.values()), key=lambda item: item['bytes'], reverse=True)[:5]}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', default='http://localhost:8090/')
    parser.add_argument('--output', type=Path)
    parser.add_argument('--expect-indexable', action='store_true', help='Use only for the published production site.')
    args = parser.parse_args()
    base = args.base_url.rstrip('/') + '/'
    if urlsplit(base).scheme not in ('http', 'https'):
        parser.error('An HTTP(S) site URL is required.')
    result = audit(base, args.expect_indexable)
    report = json.dumps(result, ensure_ascii=False, indent=2)
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(report + '\n', encoding='utf-8')
    print(report)
    raise SystemExit(1 if result['issues'] or result['unreachable'] or result['duplicate_titles'] else 0)
