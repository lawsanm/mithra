<?php

declare(strict_types=1);

/**
 * Mithra convention checker — the machine half of the PR review.
 *
 * Vanilla PHP, zero dependencies (Rules/CONVENTIONS.md §2). Run it locally with
 * `php .github/scripts/conventions-check.php` before pushing; CI runs the same file.
 *
 * Set BASE_REF to compare against the target branch (enables the append-only
 * migration check): BASE_REF=origin/main php .github/scripts/conventions-check.php
 *
 * Exit 0 = nothing blocking. Exit 1 = at least one violation of a hard rule.
 */

$root = dirname(__DIR__, 2);
chdir($root);

/** @var list<array{rule:string,file:string,line:int,message:string}> */
$errors = [];
/** @var list<array{rule:string,file:string,line:int,message:string}> */
$warnings = [];

function fail(string $rule, string $file, int $line, string $message): void
{
    global $errors;
    $errors[] = ['rule' => $rule, 'file' => $file, 'line' => $line, 'message' => $message];
}

function warn(string $rule, string $file, int $line, string $message): void
{
    global $warnings;
    $warnings[] = ['rule' => $rule, 'file' => $file, 'line' => $line, 'message' => $message];
}

/**
 * @return list<string>
 */
function trackedFiles(): array
{
    // Tracked files plus anything new that is not git-ignored, so a local run
    // sees the file you just created exactly as CI will once it is committed.
    exec('git ls-files --cached --others --exclude-standard', $output, $status);
    if ($status !== 0) {
        fwrite(STDERR, "conventions-check: not a git repository\n");
        exit(2);
    }

    return array_values(array_filter(array_map('trim', $output), static fn (string $f): bool => $f !== ''));
}

/**
 * @param  list<string> $files
 * @return list<string>
 */
function withExtension(array $files, string $extension): array
{
    return array_values(array_filter(
        $files,
        static fn (string $f): bool => str_ends_with(strtolower($f), '.' . $extension)
    ));
}

function isUnder(string $file, string $directory): bool
{
    return str_starts_with($file, rtrim($directory, '/') . '/');
}

/** Strips full-line comments so rules do not fire on prose about the rule. */
function isCommentLine(string $line): bool
{
    $trimmed = ltrim($line);

    return str_starts_with($trimmed, '//')
        || str_starts_with($trimmed, '#')
        || str_starts_with($trimmed, '*')
        || str_starts_with($trimmed, '/*');
}

$files = trackedFiles();

// The checker itself contains the patterns it looks for — never scan tooling.
$files = array_values(array_filter($files, static fn (string $f): bool => !isUnder($f, '.github')));

$phpFiles   = withExtension($files, 'php');
$jsFiles    = withExtension($files, 'js');
$cssFiles   = withExtension($files, 'css');
$sqlFiles   = withExtension($files, 'sql');

/** @var array<string, list<string>> file => lines */
$lines = [];
foreach (array_merge($phpFiles, $jsFiles, $cssFiles) as $file) {
    $contents     = (string) file_get_contents($file);
    $lines[$file] = explode("\n", $contents);
}

// ---------------------------------------------------------------------------
// 1. No frameworks, libraries, packages or CDNs (§2)
// ---------------------------------------------------------------------------

$forbiddenPaths = [
    'composer.json'     => 'Composer packages are not allowed',
    'composer.lock'     => 'Composer packages are not allowed',
    'package.json'      => 'npm packages are not allowed',
    'package-lock.json' => 'npm packages are not allowed',
    'yarn.lock'         => 'npm packages are not allowed',
];

foreach ($files as $file) {
    foreach ($forbiddenPaths as $needle => $why) {
        if (basename($file) === $needle) {
            fail('no-dependencies', $file, 0, $why . ' (§2).');
        }
    }

    if (isUnder($file, 'vendor') || isUnder($file, 'node_modules')) {
        fail('no-dependencies', $file, 0, 'Dependency directories must never be committed (§2).');
    }
}

$libraryNames = '/\b(jquery|bootstrap|tailwind|alpinejs|alpine\.js|htmx|react|vue\.js|angular|lodash|axios)\b/i';

foreach ($lines as $file => $fileLines) {
    foreach ($fileLines as $index => $line) {
        $number = $index + 1;

        if (isCommentLine($line)) {
            continue;
        }

        if (preg_match('/<(script|link|img)[^>]+(src|href)\s*=\s*["\']https?:\/\//i', $line) === 1) {
            fail('no-cdn', $file, $number, 'External asset URL — everything is served from /public (§2).');
        }

        if (preg_match('/@import\s+(url\()?["\']?https?:\/\//i', $line) === 1) {
            fail('no-cdn', $file, $number, 'Remote @import — no CDNs (§2).');
        }

        if (preg_match($libraryNames, $line) === 1) {
            fail('no-dependencies', $file, $number, 'Reference to a frontend library — vanilla HTML/CSS/JS only (§2).');
        }
    }
}

// ---------------------------------------------------------------------------
// 2. One stylesheet, no inline styles (§3, §11)
// ---------------------------------------------------------------------------

foreach ($cssFiles as $file) {
    if ($file !== 'public/css/main.css') {
        fail('single-stylesheet', $file, 0, 'public/css/main.css is the only stylesheet (§3).');
    }
}

foreach ($phpFiles as $file) {
    foreach ($lines[$file] as $index => $line) {
        if (preg_match('/<style[\s>]/i', $line) === 1) {
            fail('single-stylesheet', $file, $index + 1, 'Inline <style> block — put it in main.css (§3).');
        }

        if (preg_match('/\son[a-z]+\s*=\s*["\']/i', $line) === 1 && preg_match('/<[a-z]/i', $line) === 1) {
            fail('no-inline-handlers', $file, $index + 1, 'Inline event attribute — use addEventListener (§11).');
        }
    }
}

// ---------------------------------------------------------------------------
// 3. Database access: PDO only, SQL only in models (§2, §6)
// ---------------------------------------------------------------------------

foreach ($phpFiles as $file) {
    foreach ($lines[$file] as $index => $line) {
        $number = $index + 1;

        if (isCommentLine($line)) {
            continue;
        }

        if (preg_match('/\bmysqli|\bmysql_[a-z_]+\s*\(/i', $line) === 1) {
            fail('pdo-only', $file, $number, 'mysqli/mysql_* is forbidden — PDO only (§2).');
        }

        if (preg_match('/new\s+PDO\s*\(/', $line) === 1 && $file !== 'app/Core/Database.php') {
            fail('pdo-only', $file, $number, 'PDO is constructed once in app/Core/Database.php and injected (§6, DIP).');
        }

        $isModel = isUnder($file, 'app/Models') || isUnder($file, 'app/Core');
        if (!$isModel && preg_match('/->\s*(prepare|query|exec)\s*\(/', $line) === 1) {
            fail('sql-in-models', $file, $number, 'SQL lives in /app/Models and nowhere else (§6).');
        }
    }
}

// ---------------------------------------------------------------------------
// 4. PHP style (§5)
// ---------------------------------------------------------------------------

foreach ($phpFiles as $file) {
    $isTemplate = isUnder($file, 'views') || isUnder($file, 'partials') || isUnder($file, 'app/partials');
    $contents   = implode("\n", $lines[$file]);

    if (isUnder($file, 'app') && !$isTemplate) {
        if (!str_contains($contents, 'declare(strict_types=1)')) {
            fail('strict-types', $file, 1, 'Every PHP class file starts with declare(strict_types=1) (§5).');
        }

        if (preg_match('/\?>\s*$/', $contents) === 1) {
            fail('no-closing-tag', $file, count($lines[$file]), 'No closing ?> in pure-PHP files (§5).');
        }

        if (preg_match('/^class\s|^abstract\s+class\s|^final\s+class\s|^interface\s|^trait\s/m', $contents) === 1) {
            preg_match('/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/m', $contents, $match);
            $expected = pathinfo($file, PATHINFO_FILENAME);
            if (isset($match[1]) && $match[1] !== $expected) {
                fail('file-name-matches-class', $file, 1, "Class {$match[1]} must live in {$match[1]}.php (§4).");
            }
        }
    }

    foreach ($lines[$file] as $index => $line) {
        $number = $index + 1;

        if (isCommentLine($line)) {
            continue;
        }

        if (preg_match('/^\s*global\s+\$/', $line) === 1) {
            fail('no-globals', $file, $number, 'No `global` (§5).');
        }

        $exitAllowed = in_array($file, ['public/index.php', 'public/router.php', 'public/photo.php'], true)
            || isUnder($file, 'scripts')
            || isUnder($file, '.github');
        if (!$exitAllowed && preg_match('/\b(die|exit)\s*[(;]/', $line) === 1) {
            fail('no-exit', $file, $number, 'No die()/exit() outside the front controller and the photo proxy (§5).');
        }

        if (preg_match('/\b(var_dump|print_r|dd)\s*\(/', $line) === 1) {
            fail('no-debug-leftovers', $file, $number, 'Debug helper left in the code.');
        }
    }
}

// ---------------------------------------------------------------------------
// 5. Security: escaping and CSRF (§7)
// ---------------------------------------------------------------------------

foreach ($phpFiles as $file) {
    foreach ($lines[$file] as $index => $line) {
        $number = $index + 1;

        // Deliberate exceptions carry a reason: <!-- escape-ok: literal tag name -->
        if (str_contains($line, 'escape-ok:')) {
            continue;
        }

        // A short-echo tag holding nothing but a variable, with no escaping helper.
        $bareEcho = '/<\?=\s*\$[A-Za-z_]\w*(?:\[[^\]]*\]|->\w+)*\s*\?>/';
        if (preg_match($bareEcho, $line) === 1) {
            fail('escape-output', $file, $number, 'Echoed value is not escaped — wrap it in e() (§7.2).');
        }

        $bareEchoStatement = '/\becho\s+\$[A-Za-z_]\w*(?:\[[^\]]*\]|->\w+)*\s*;/';
        if (preg_match($bareEchoStatement, $line) === 1) {
            fail('escape-output', $file, $number, 'Echoed value is not escaped — wrap it in e() (§7.2).');
        }

        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SESSION)\b/', $line) === 1 && isUnder($file, 'app/Services')) {
            fail('services-are-pure', $file, $number, 'Services never read superglobals — pass values in (§6).');
        }
    }

    // Every POST form carries a CSRF token.
    $contents = implode("\n", $lines[$file]);
    $chunks   = preg_split('/<form\b/i', $contents) ?: [];
    array_shift($chunks);

    foreach ($chunks as $chunk) {
        $form   = preg_split('/<\/form>/i', $chunk)[0] ?? $chunk;
        $isPost = preg_match('/method\s*=\s*["\']?post/i', $form) === 1;

        if ($isPost && !str_contains($form, 'csrf_field')) {
            fail('csrf-token', $file, 0, 'POST form without csrf_field() (§7.3).');
        }
    }
}

// ---------------------------------------------------------------------------
// 6. Nothing secret or generated is committed (§12)
// ---------------------------------------------------------------------------

foreach ($files as $file) {
    if ($file === 'config/config.php') {
        fail('no-secrets', $file, 0, 'config/config.php is local-only and git-ignored (§12).');
    }

    if (preg_match('/^\.env/', basename($file)) === 1) {
        fail('no-secrets', $file, 0, 'Environment files are never committed (§12).');
    }

    if (isUnder($file, 'storage/uploads') && basename($file) !== '.gitkeep') {
        fail('no-secrets', $file, 0, 'Uploaded files are never committed (§12).');
    }
}

// ---------------------------------------------------------------------------
// 7. Migrations are numbered and append-only (§3, §12)
// ---------------------------------------------------------------------------

foreach ($sqlFiles as $file) {
    if (!isUnder($file, 'migrations')) {
        continue;
    }

    if (preg_match('/^\d{3}_[a-z0-9]+(_[a-z0-9]+)*\.sql$/', basename($file)) !== 1) {
        fail('migration-naming', $file, 0, 'Migrations are named NNN_verb_noun.sql (§4).');
    }
}

$baseRef = getenv('BASE_REF');
if (is_string($baseRef) && $baseRef !== '') {
    $escaped = escapeshellarg($baseRef);
    exec("git diff --name-status {$escaped}...HEAD", $diff, $diffStatus);

    if ($diffStatus === 0) {
        foreach ($diff as $row) {
            $parts  = preg_split('/\t/', trim($row)) ?: [];
            $status = $parts[0] ?? '';
            $path   = $parts[1] ?? '';

            if (!isUnder($path, 'migrations') || !str_ends_with($path, '.sql')) {
                continue;
            }

            if (str_starts_with($status, 'M') || str_starts_with($status, 'D') || str_starts_with($status, 'R')) {
                fail(
                    'migrations-append-only',
                    $path,
                    0,
                    'An applied migration was changed — add a new numbered file instead (§3).'
                );
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 8. Advisory checks — reported, never blocking
// ---------------------------------------------------------------------------

$sqlKeywords = '/\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|WHERE|ORDER\s+BY|LIMIT|JOIN)\b/i';

foreach ($phpFiles as $file) {
    $carriesSql = isUnder($file, 'app/Models') || isUnder($file, 'app/Core') || isUnder($file, 'scripts');
    if (!$carriesSql) {
        continue;
    }

    foreach ($lines[$file] as $index => $line) {
        $number = $index + 1;

        if (isCommentLine($line) || preg_match($sqlKeywords, $line) !== 1) {
            continue;
        }

        // {$this->table} is structural; anything else interpolated needs a whitelist.
        $interpolated = preg_replace('/\{?\$this->\w+\}?/', '', $line) ?? $line;

        if (preg_match('/\{\$\w+/', $interpolated) === 1 || preg_match('/["\']\s*\.\s*\$\w+/', $interpolated) === 1) {
            warn('sql-interpolation', $file, $number, 'Variable inside SQL — confirm it is a whitelisted identifier, never a value (§7.1).');
        }

        if (preg_match('/SELECT\s+\*/i', $line) === 1 && preg_match('/COUNT\(\*\)/i', $line) !== 1) {
            warn('no-select-star', $file, $number, 'SELECT * — name the columns (§9).');
        }
    }
}

foreach ($jsFiles as $file) {
    foreach ($lines[$file] as $index => $line) {
        if (preg_match('/\.innerHTML\s*=/', $line) === 1) {
            warn('no-innerhtml', $file, $index + 1, 'innerHTML with user-derived data is an XSS risk — use textContent (§11).');
        }

        if (preg_match('/console\.log\s*\(/', $line) === 1) {
            warn('no-debug-leftovers', $file, $index + 1, 'console.log left in the code.');
        }
    }
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

/**
 * @param list<array{rule:string,file:string,line:int,message:string}> $items
 */
function render(array $items, string $bullet): string
{
    $out = '';
    foreach ($items as $item) {
        $where = $item['line'] > 0 ? "{$item['file']}:{$item['line']}" : $item['file'];
        $out  .= "{$bullet} `{$where}` — {$item['message']} _[{$item['rule']}]_\n";
    }

    return $out;
}

$summary  = "## Convention check\n\n";
$summary .= $errors === []
    ? "**Passed** — no hard-rule violations.\n\n"
    : '**' . count($errors) . " violation(s) of Rules/CONVENTIONS.md**\n\n" . render($errors, '-') . "\n";

if ($warnings !== []) {
    $summary .= '<details><summary>' . count($warnings) . " advisory note(s) for the reviewer</summary>\n\n"
        . render($warnings, '-') . "\n</details>\n";
}

echo $summary;

$summaryFile = getenv('GITHUB_STEP_SUMMARY');
if (is_string($summaryFile) && $summaryFile !== '') {
    file_put_contents($summaryFile, $summary, FILE_APPEND);
}

$reportFile = getenv('REPORT_FILE');
if (is_string($reportFile) && $reportFile !== '') {
    file_put_contents($reportFile, $summary);
}

exit($errors === [] ? 0 : 1);
