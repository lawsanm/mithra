"""Exercise real multipart uploads against a temporary, loopback-only PHP server."""
from pathlib import Path
import json
import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('MITHRA_PHP') or shutil.which('php') or r'C:\xampp\php\php.exe'
BOUNDARY = 'mithra-' + uuid.uuid4().hex


def multipart(files):
    body = bytearray()
    for name, data in files:
        body.extend((f'--{BOUNDARY}\r\nContent-Disposition: form-data; '
                     f'name="photos[]"; filename="{name}"\r\n'
                     'Content-Type: application/octet-stream\r\n\r\n').encode())
        body.extend(data)
        body.extend(b'\r\n')
    body.extend(f'--{BOUNDARY}--\r\n'.encode())
    return body


with tempfile.TemporaryDirectory(prefix='mithra-upload-check-') as temporary:
    directory = Path(temporary)
    token = uuid.uuid4().hex
    router = directory / 'router.php'
    # PHP string paths use JSON quoting; Windows separators are normalized first.
    autoload = json.dumps((ROOT / 'app/autoload.php').as_posix())
    router.write_text('''<?php
require AUTOLOAD;
if (!hash_equals('__TOKEN__', $_SERVER['HTTP_X_TEST_TOKEN'] ?? '')) {
    http_response_code(403);
    return;
}
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo '{}';
    return;
}
$store = new PhotoStore(__DIR__ . '/uploads');
try {
    echo json_encode(['paths' => $store->storeMany(uploaded_files('photos'), 'item-photos', 'photos', 3)]);
} catch (ValidationException $error) {
    http_response_code(422);
    echo json_encode(['errors' => $error->errors()]);
}
'''.replace('AUTOLOAD', autoload).replace('__TOKEN__', token), encoding='utf-8')

    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    process = subprocess.Popen(
        [PHP, '-d', f'upload_tmp_dir={directory}', '-S', f'127.0.0.1:{port}', str(router)], cwd=directory,
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0,
    )
    try:
        for attempt in range(50):
            try:
                with urllib.request.urlopen(urllib.request.Request(
                        url, headers={'X-Test-Token': token}), timeout=1):
                    break
            except (urllib.error.URLError, TimeoutError):
                if process.poll() is not None:
                    raise RuntimeError('Temporary PHP server failed to start.')
                time.sleep(0.1)
        else:
            raise RuntimeError('Temporary PHP server did not become ready.')

        photo = (ROOT / 'storage/demo/item-photos/cordless-drill.jpg').read_bytes()

        def upload(files):
            request = urllib.request.Request(url, multipart(files), headers={
                'X-Test-Token': token,
                'Content-Type': f'multipart/form-data; boundary={BOUNDARY}',
            })
            try:
                with urllib.request.urlopen(request, timeout=10) as response:
                    return response.status, json.load(response)
            except urllib.error.HTTPError as error:
                body = error.read().decode('utf-8')
                try:
                    return error.code, json.loads(body)
                except json.JSONDecodeError as exception:
                    raise RuntimeError(f'Upload returned HTTP {error.code}: {body}') from exception

        status, result = upload([('photo.jpg', photo), ('invalid.jpg', b'not an image')])
        assert status == 422 and 'photos' in result['errors'], result
        assert not list(directory.glob('uploads/**/*.jpg')), 'A failed batch left orphaned files.'
        status, result = upload([('photo.jpg', photo)])
        assert status == 200 and len(result['paths']) == 1, result
        assert len(list(directory.glob('uploads/**/*.jpg'))) == 1, 'A valid batch must persist its image.'
        print('Passed: 4 upload checks — failed batches cleaned up, valid images retained.')
    finally:
        process.terminate()
        process.wait(timeout=10)
