"""Read-only HTTP regression checks. Start Mithra locally before running."""
from pathlib import Path
from html.parser import HTMLParser
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost/mithra'
ROOT = Path(__file__).resolve().parents[1]
checks = 0

def check(condition, message):
    global checks
    checks += 1
    assert condition, message

def fetch(path):
    url = path if path.startswith('http') else BASE + path
    try:
        with urllib.request.urlopen(url, timeout=15) as response:
            return response.status, response.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode('utf-8', errors='replace')

class Page(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.tags = []
        self.feed(body)
    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))
    def tagged(self, name):
        return [attrs for tag, attrs in self.tags if tag == name]

route_text = (ROOT / 'app/routes.php').read_text(encoding='utf-8').split("'POST' =>", 1)[0]
paths = [p.replace('{id}', '1') for p in re.findall(r"^\s*'(/[^']*)'\s*=>", route_text, re.M)]
pages = {}
for path in paths:
    status, body = fetch(path)
    if path == '/items/1/edit' and status == 403:
        continue  # The seeded item is owned by another member.
    check(status == 200, f'{path}: HTTP {status}')
    if path.endswith('/export'):
        check('Approved' in body and 'Awaiting approval' not in body, 'Grant export must contain only approved records')
        continue
    page = Page(body)
    pages[path] = body
    ids = [a['id'] for _, a in page.tags if 'id' in a]
    check(len(ids) == len(set(ids)), f'{path}: duplicate element IDs')
    check(any(a.get('href') == '/mithra/css/main.css' for a in page.tagged('link')), f'{path}: missing mounted stylesheet')
    check(any(a.get('class', '').find('nav__logo') >= 0 and a.get('width') == '21' and a.get('height') == '28'
              for a in page.tagged('img')), f'{path}: logo dimensions missing')
    for tag, attrs in page.tags:
        if 'data-modal-open' in attrs:
            check(attrs['data-modal-open'] in ids, f'{path}: missing modal {attrs["data-modal-open"]}')
            check('/js/modal.js' in body, f'{path}: modal script not loaded')
        if tag == 'button' and 'modal__close' in attrs.get('class', ''):
            check('data-modal-close' in attrs and 'disabled' not in attrs, f'{path}: modal close disabled/unwired')
        if tag == 'button':
            check('href' not in attrs, f'{path}: invalid button href')
        if tag == 'a' and attrs.get('href', '').startswith('#'):
            check(attrs['href'][1:] in ids, f'{path}: missing local fragment {attrs["href"]}')

for role in ['borrower', 'lender']:
    status, body = fetch('/bookings?role=' + role)
    check(status == 200, 'Booking role page failed')
    page = Page(body)
    check(any(a.get('href', '').endswith('?role=' + role) and a.get('aria-current') == 'page'
              for a in page.tagged('a')), f'{role}: wrong active tab')
    for href in {a['href'] for a in page.tagged('a') if re.fullmatch(r'/mithra/bookings/\d+', a.get('href', ''))}:
        code, detail = fetch('http://localhost' + href)
        check(code == 200 and ('Booking #' + href.rsplit('/', 1)[1]) in detail, 'Wrong booking record')
        check('As ' + role.capitalize() in detail, 'Wrong booking party role')
check(fetch('/bookings/999999')[0] == 404, 'Unknown booking must not display a sample record')

for group in ['verifications', 'listing-approvals', 'cases']:
    code1, one = fetch('/moderator/' + group + '/1')
    code2, two = fetch('/moderator/' + group + '/2')
    check(code1 == code2 == 200 and one != two, 'Moderator detail must change with the selected ID')
    check(fetch('/moderator/' + group + '/999999')[0] == 404, 'Unknown moderator record must not fall back')

_, first = fetch('/sponsor-liaison/sponsors/1')
_, second = fetch('/sponsor-liaison/sponsors/2')
check('Northwind Co' in first and 'ACM Corp' in second and first != second, 'Sponsor record selection failed')
_, search = fetch('/sponsor-liaison/sponsors?q=Texa')
check('team@texa.lk' in search and 'contact@northwind.lk' not in search, 'Sponsor search does not filter')
_, purchase = fetch('/sponsor-liaison/purchases?q=INV-0306')
check('INV-0306' in purchase and 'INV-0312' not in purchase, 'Receipt search does not filter')
_, approved = fetch('/sponsor-liaison/aid-grants?status=approved')
check('/sponsor-liaison/aid-grants/4' in approved and '/sponsor-liaison/aid-grants/1"' not in approved, 'Grant status filter failed')
_, grant1 = fetch('/sponsor-liaison/aid-grants/1')
_, grant2 = fetch('/sponsor-liaison/aid-grants/2')
check('School supplies' in grant1 and 'Medical costs' in grant2, 'Grant detail shows wrong request')

_, appointment = fetch('/admin/moderators/appoint/1')
choices = [a['href'] for a in Page(appointment).tagged('a') if re.search(r'/appoint/\d+\?member=\d+', a.get('href', ''))]
for href in choices:
    code, selected = fetch('http://localhost' + href)
    check(code == 200 and re.search(r'id="review-name"[^>]*>[^<]+</strong>', selected), 'Appointment selection did not populate the review')
    check('id="btn-confirm-appointment" disabled' in selected, 'Demo appointment must not imply a saved decision')

print(f'Passed {checks} read-only UI/navigation checks across {len(paths)} routes.')
