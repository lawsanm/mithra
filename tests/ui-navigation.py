"""Read-only HTTP regression checks. Start Mithra locally before running."""
from pathlib import Path
from html.parser import HTMLParser
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost/mithra'
ORIGIN = BASE[:BASE.index('/', len('http://'))]
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

def sign_in(email, password):
    """Every screen needs a session (AuthMiddleware) and a role that may open it
    (RbacMiddleware), so the run signs in as whichever account owns the path."""
    urllib.request.install_opener(urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())))
    with urllib.request.urlopen(BASE + '/login', timeout=15) as response:
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"',
                          response.read().decode('utf-8', errors='replace'))
    if token is None:
        raise SystemExit('No sign-in form at ' + BASE + '/login. Is the app running?')
    form = urllib.parse.urlencode(
        {'csrf_token': token.group(1), 'identifier': email, 'password': password}).encode()
    with urllib.request.urlopen(BASE + '/login', form, timeout=15) as response:
        if response.geturl().endswith('/login'):
            raise SystemExit('Could not sign in as ' + email + '. Is the demo data loaded?')

# One seeded account per role; whoever is signed in is swapped as the run moves
# between areas.
MEMBER = ('lawsanm@gmail.com', 'password')
MODERATOR = ('kavipriya@email.com', 'password')
ADMIN = ('madushan@email.com', 'password')
LIAISON = ('akalvily@email.com', 'password')
SPONSOR = ('thineka@email.com', 'password')
signed_in_as = None

def use(account):
    global signed_in_as
    if account != signed_in_as:
        sign_in(*account)
        signed_in_as = account

def account_for(path):
    for prefix, account in [('/admin', ADMIN), ('/moderator', MODERATOR), ('/sponsor-liaison', LIAISON), ('/sponsor', SPONSOR)]:
        if path == prefix or path.startswith(prefix + '/'):
            return account
    return MEMBER

route_text = (ROOT / 'app/routes.php').read_text(encoding='utf-8')
get_routes = route_text.split("'GET' =>", 1)[1].split("'POST' =>", 1)[0]
paths = [p.replace('{id}', '1') for p in re.findall(r"^\s*'(/[^']*)'\s*=>", get_routes, re.M)]
paths = [p for p in paths if account_for(p) is not None]
pages = {}
for path in paths:
    use(account_for(path))
    status, body = fetch(path)
    if path == '/items/1/edit' and status == 403:
        continue  # The seeded item is owned by another member.
    if path == '/admin/listing-approvals/1' and status == 403:
        continue  # Its division has a moderator, so the Admin may not decide it.
    if path == '/admin/disputes/1' and status == 404:
        continue  # The seed has no disputes; a database with one serves it.
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

use(MEMBER)
for role in ['borrower', 'lender']:
    status, body = fetch('/bookings?role=' + role)
    check(status == 200, 'Booking role page failed')
    page = Page(body)
    check(any(a.get('href', '').endswith('?role=' + role) and a.get('aria-current') == 'page'
              for a in page.tagged('a')), f'{role}: wrong active tab')
    for href in {a['href'] for a in page.tagged('a') if re.fullmatch(r'/mithra/bookings/\d+', a.get('href', ''))}:
        code, detail = fetch(ORIGIN + href)
        check(code == 200 and ('Booking #' + href.rsplit('/', 1)[1]) in detail, 'Wrong booking record')
        check('As ' + role.capitalize() in detail, 'Wrong booking party role')
check(fetch('/bookings/999999')[0] == 404, 'Unknown booking must not display a sample record')

use(MODERATOR)
for group in ['verifications', 'listing-approvals', 'cases']:
    code1, one = fetch('/moderator/' + group + '/1')
    code2, two = fetch('/moderator/' + group + '/2')
    check(code1 == code2 == 200 and one != two, 'Moderator detail must change with the selected ID')
    check(fetch('/moderator/' + group + '/999999')[0] == 404, 'Unknown moderator record must not fall back')

use(LIAISON)
_, first = fetch('/sponsor-liaison/sponsors/1')
_, second = fetch('/sponsor-liaison/sponsors/2')
check('Lanka Hardware' in first and 'Ceylon Fresh Mart' in second and first != second, 'Sponsor record selection failed')
check(fetch('/sponsor-liaison/sponsors/999999')[0] == 404, 'Unknown sponsor must not display a record')
check(fetch('/sponsor-liaison/sponsors/999999/edit')[0] == 404, 'Unknown sponsor must not open the edit form')
_, search = fetch('/sponsor-liaison/sponsors?q=Sunrise')
check('Sunrise Pharmacy' in search and 'Lanka Hardware' not in search, 'Sponsor search does not filter')
_, by_name = fetch('/sponsor-liaison/sponsors?sort=name')
check(by_name.index('Ceylon Fresh Mart') < by_name.index('Lanka Hardware') < by_name.index('Sunrise Pharmacy'), 'Sponsor name sort failed')
_, edit = fetch('/sponsor-liaison/sponsors/1/edit')
check('value="Lanka Hardware (Pvt) Ltd"' in edit and 'name="csrf_token"' in edit, 'Sponsor edit form not prefilled')
_, purchase = fetch('/sponsor-liaison/purchases?q=INV-0306')
check('INV-0306' in purchase and 'INV-0312' not in purchase, 'Receipt search does not filter')
_, approved = fetch('/sponsor-liaison/aid-grants?status=approved')
check('/sponsor-liaison/aid-grants/4' in approved and '/sponsor-liaison/aid-grants/1"' not in approved, 'Grant status filter failed')
_, grant1 = fetch('/sponsor-liaison/aid-grants/1')
_, grant2 = fetch('/sponsor-liaison/aid-grants/2')
check('School supplies' in grant1 and 'Medical costs' in grant2, 'Grant detail shows wrong request')

use(ADMIN)
_, appointment = fetch('/admin/moderators/appoint/1')
choices = [a['href'] for a in Page(appointment).tagged('a') if re.search(r'/appoint/\d+\?member=\d+', a.get('href', ''))]
for href in choices:
    code, selected = fetch(ORIGIN + href)
    check(code == 200 and re.search(r'id="review-name"[^>]*>[^<]+</strong>', selected), 'Appointment selection did not populate the review')
    check('id="btn-confirm-appointment" disabled' in selected, 'Demo appointment must not imply a saved decision')

print(f'Passed {checks} read-only UI/navigation checks across {len(paths)} routes.')
