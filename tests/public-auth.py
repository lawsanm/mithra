"""Public-page and registration HTTP checks against the running local app.

Uses a fresh cookie jar and synthetic details stored only in that session.
No documents are uploaded, accounts created, passwords changed, or emails sent.
"""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost/mithra'
checks = 0
client = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def check(condition, message):
    global checks
    checks += 1
    assert condition, message


def fetch(path, data=None):
    body = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = client.open(BASE + path, body, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        html = response.read().decode('utf-8')
        check(not re.search(r'(?:Warning|Fatal error|Parse error):', html),
              'PHP error on ' + path)
        return response.status, response.geturl(), html


def token(html):
    match = re.search(r'name="csrf_token" value="([a-f0-9]+)"', html)
    check(match is not None, 'Form must include a CSRF token')
    return match.group(1)


for path, text in [('/', 'Frequently asked questions'),
                   ('/how-it-works', 'How Mithra works'),
                   ('/transparency', 'Transparency Dashboard'),
                   ('/help', 'FAQ')]:
    status, url, html = fetch(path)
    check(status == 200 and url == BASE + path, 'Public access: ' + path)
    check(text in html, 'Public content missing: ' + path)
    check('/how-it-works' in html and '/login' in html, 'Public navigation: ' + path)
    if path == '/':
        check(all(label in html for label in ['verified members', 'GN divisions', 'items shared']),
              'Homepage must show live statistics')
        check('landing-hero' in html and 'id="faq"' in html,
              'Landing layout and existing FAQ must both survive the merge')

status, url, html = fetch('/dashboard')
check(url == BASE + '/login', 'Private pages must redirect visitors to login')
status, _, html = fetch('/register?step=2')
check('name="step" value="1"' in html, 'Cannot skip personal details')
csrf = token(html)
status, _, html = fetch('/register', {'step': '1', 'full_name': 'Missing CSRF'})
check(status == 403, 'Registration must reject missing CSRF')
status, _, html = fetch('/register', {'csrf_token': csrf, 'step': '1'})
check(status == 422, 'Invalid details must fail before document upload')
for field in ['register-name', 'register-nic', 'register-phone', 'register-address', 'register-division']:
    check(re.search(r'<(?:input|select)\b[^>]*id="' + field + r'"[^>]*aria-invalid="true"', html),
          'Expected field error: ' + field)
division = re.search(r'<option\s+value="([1-9][0-9]*)"', html)
check(division is not None, 'Test requires a seeded active division')
details = {'csrf_token': csrf, 'step': '1', 'full_name': 'HTTP Check <Visitor>',
           'nic': '200198765V', 'phone': '0779876543', 'email': 'pr14-check@example.test',
           'address': 'Test address', 'gn_division_id': division.group(1)}
status, url, html = fetch('/register', details)
check(status == 200 and url.endswith('/register?step=2'), 'Valid details must advance to step 2')
check('name="step" value="2"' in html and 'name="nic_photo"' in html
      and 'name="address_proof"' in html, 'Second step must collect both documents')
check('name="password"' in html, 'Password belongs to the second step')
status, _, html = fetch('/register', {'csrf_token': csrf, 'step': '2',
                                    'password': 'x', 'password_confirmation': 'y'})
check(status == 422 and 'name="step" value="2"' in html,
      'Missing documents and invalid password must keep the applicant on step 2')
check('value="x"' not in html and 'value="y"' not in html, 'Passwords must never be echoed')
status, _, html = fetch('/register')
check('HTTP Check &lt;Visitor&gt;' in html, 'Back navigation must retain and escape the draft')
status, url, _ = fetch('/register/pending')
check(url.endswith('/register'), 'Pending page requires a completed application')

status, _, html = fetch('/forgot-password')
check(status == 200 and re.search(r'<dialog\b[^>]*id="forgot-password"[^>]*\bopen\b', html),
      'Direct reset-link page must open its dialog without JavaScript')
status, _, html = fetch('/forgot-password', {'csrf_token': csrf, 'email': ''})
check(status == 422 and 'aria-invalid="true"' in html, 'Empty reset email must show a field error')
status, _, html = fetch('/reset-password?token=invalid-test-token')
check(status == 200 and re.search(r'<dialog\b[^>]*id="reset-password"[^>]*\bopen\b', html),
      'Invalid reset link must still render its reset dialog')
status, _, html = fetch('/login', {'csrf_token': csrf, 'identifier': '', 'password': ''})
check(status == 422 and 'Welcome back' in html, 'Login validation must use the merged dialog-aware renderer')
print(f'Passed: {checks} public-page and registration HTTP checks.')
