"""Read-only route/link audit of the local demo; never submits forms."""
import collections
import concurrent.futures
import html.parser
import json
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://localhost/mithra'
source = (ROOT / 'app/routes.php').read_text(encoding='utf-8')
get_source, post_source = source.split("'POST' =>", 1)
patterns = re.findall(r"^\s*'(/[^']*)'\s*=>", get_source, re.M)
posts = re.findall(r"^\s*'(/[^']*)'\s*=>", post_source, re.M)

class Page(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.targets, self.forms = [], []
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        attr = 'href' if tag in ('a', 'link') else 'src' if tag in ('img', 'script') else None
        if attr and attrs.get(attr) and not attrs[attr].startswith('#'):
            self.targets.append(attrs[attr])
        if tag == 'form':
            self.forms.append((attrs.get('method', 'GET').upper(), attrs.get('action', '')))

def fetch(url):
    try:
        with urllib.request.urlopen(url, timeout=15) as response:
            return response.status, response.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as error:
        return error.code, ''
    except Exception as error:
        return str(error), ''

def registered(method, url):
    path = urllib.parse.urlsplit(url).path
    if not path.startswith('/mithra/') and path != '/mithra':
        return False
    path = path[len('/mithra'):] or '/'
    table = posts if method == 'POST' else patterns if method == 'GET' else []
    return any(re.fullmatch(re.escape(p).replace(r'\{id\}', r'[1-9][0-9]*'), path) for p in table)

routes, targets, forms = [], collections.defaultdict(set), []
for pattern in patterns:
    url = BASE + pattern.replace('{id}', '1')
    status, body = fetch(url)
    routes.append({'url': url, 'status': status})
    if status != 200:
        continue
    page = Page()
    page.feed(body)
    for target in page.targets:
        target = urllib.parse.urldefrag(urllib.parse.urljoin(url, target))[0]
        if urllib.parse.urlsplit(target).netloc == 'localhost':
            targets[target].add(url)
    for method, action in page.forms:
        action = urllib.parse.urljoin(url, action)
        if not registered(method, action):
            forms.append({'source': url, 'method': method, 'action': action})

with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    statuses = list(pool.map(lambda url: (url, fetch(url)[0]), targets))
broken = [{'url': url, 'status': status, 'sources': sorted(targets[url])}
          for url, status in statuses if status != 200]
result = {
    'scope': 'GET requests and static form inspection only; no browser rendering or form submissions',
    'route_count': len(routes),
    'route_statuses': dict(collections.Counter(str(r['status']) for r in routes)),
    'route_failures': [r for r in routes if r['status'] != 200],
    'target_count': len(targets),
    'target_statuses': dict(collections.Counter(str(status) for _, status in statuses)),
    'broken_targets': broken,
    'unsupported_form_occurrences': len(forms),
    'unsupported_form_destinations': len({(f['method'], f['action']) for f in forms}),
    'unsupported_forms': forms,
}
(ROOT / 'docs/UI_AUDIT_RECHECK.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({key: value for key, value in result.items()
                  if key not in ('broken_targets', 'unsupported_forms')}, indent=2))
