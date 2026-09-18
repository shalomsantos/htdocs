<?php
declare(strict_types=1);
if (!defined('CORE_DOCS_INDEX')) { http_response_code(403); exit; }
session_start();

$root = dirname(__DIR__);
$phpMyAdminUrl = '/phpmyadmin/'; // Use null para ocultar o atalho.

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path): string {
    $clean = str_replace('\\', '/', $path);
    $result = '/' . implode('/', array_map('rawurlencode', explode('/', trim($clean, '/'))));
    return substr($clean, -1) === '/' ? $result . '/' : $result;
}
function vscodeUrl(string $root, string $folder): ?string {
    $path = realpath($root . '/' . $folder);
    if ($path === false || !is_dir($path)) return null;
    $parts = explode('/', str_replace('\\', '/', $path));
    $encoded = array_map('rawurlencode', $parts);
    if (preg_match('/^[a-z]%3A$/i', $encoded[0])) {
        $encoded[0] = strtolower(substr($parts[0], 0, 1)) . ':';
    }
    return 'vscode://file/' . implode('/', $encoded) . '/';
}
function entryPoint(string $directory, string $folder): ?string {
    foreach (['index.php', 'home.php', 'public/index.php', 'public/home.php'] as $file) {
        if (is_file($directory . '/' . $file)) return $folder . '/' . $file;
    }
    return null;
}
function phpScreens(string $directory, string $prefix, bool $recursive = false, bool $htmlOnly = false): array {
    if (!is_dir($directory)) return [];
    $files = [];
    $iterator = $recursive
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS))
        : new DirectoryIterator($directory);
    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') continue;
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($directory) + 1));
        if (substr(basename($relative), 0, 1) === '_') continue;
        if ($htmlOnly) {
            $source = file_get_contents($item->getPathname(), false, null, 0, 65536);
            if (!is_string($source) || !preg_match('/<(?:!doctype\\s+html|html|body|main|section|header|div|form|article|h[1-6])\\b/i', $source)) continue;
        }
        $files[] = $prefix . '/' . $relative;
    }
    natcasesort($files);
    return array_values($files);
}
function screenFolders(string $directory): array {
    $found = ['views' => [], 'pages' => []];
    $parents = ['' => $directory];
    foreach (new DirectoryIterator($directory) as $item) {
        if ($item->isDir() && !$item->isDot() && substr($item->getFilename(), 0, 1) !== '.'
            && !in_array(strtolower($item->getFilename()), ['vendor', 'node_modules', 'view', 'views', 'page', 'pages'], true)) {
            $parents[$item->getFilename()] = $item->getPathname();
        }
    }
    foreach ($parents as $parentName => $parentPath) {
        foreach (new DirectoryIterator($parentPath) as $item) {
            if (!$item->isDir() || $item->isDot()) continue;
            $name = strtolower($item->getFilename());
            $kind = in_array($name, ['view', 'views'], true) ? 'views'
                : (in_array($name, ['page', 'pages'], true) ? 'pages' : null);
            if ($kind !== null) {
                $relative = $parentName === '' ? $item->getFilename() : $parentName . '/' . $item->getFilename();
                $found[$kind][$relative] = $item->getPathname();
            }
        }
    }
    return $found;
}
function routesIn(string $file): array {
    if (!is_file($file)) return [];
    $source = file_get_contents($file);
    if ($source === false) return [];
    // Analisa declarações simples; não executa o Laravel nem lê controllers.
    $source = preg_replace('~/\*.*?\*/~s', '', $source);
    $routes = [];
    foreach (explode("\n", $source) as $line) {
        if (preg_match('/^\s*\/\//', $line)) continue;
        if (preg_match('/^\s*Route::(get|post|put|patch|delete|any)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $line, $match)) {
            $methods = strtoupper($match[1]);
            $path = $match[2];
        } elseif (preg_match('/^\s*Route::match\s*\(\s*\[([^\]]+)\]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $line, $match)) {
            preg_match_all('/[\'"]([a-z]+)[\'"]/i', $match[1], $methodMatches);
            $methods = strtoupper(implode(', ', $methodMatches[1]));
            $path = $match[2];
        } else {
            continue;
        }
        preg_match_all('/\{([^}]+)\}/', $path, $params);
        $routes[] = ['methods' => $methods, 'path' => $path, 'params' => $params[1]];
    }
    return $routes;
}
function screenUrl(string $folder, string $path): string {
    if ($folder === 'shalom') {
        $routes = [
            'shalom/app/Views/landing.php' => 'shalom/public/',
            'shalom/app/Views/login.php' => 'shalom/public/login',
            'shalom/app/Views/restricted-area.php' => 'shalom/public/area-restrita',
        ];
        if (isset($routes[$path])) return url($routes[$path]);
    }
    return url($path);
}
function screenTitle(string $path): string {
    $name = pathinfo($path, PATHINFO_FILENAME);
    return ucwords(str_replace(['-', '_'], ' ', $name === 'index' ? basename(dirname($path)) : $name));
}
function scanProject(string $root, string $folder): array {
    $directory = $root . '/' . $folder;
    $entry = entryPoint($directory, $folder);
    $type = 'Site';
    $sections = [];
    $buttons = [];
    $apiRoutes = [];
    $webRoutes = [];

    if ($folder === 'wordpress') {
        $buttons = [
            ['Home', url('wordpress/')],
            ['Administração', url('wordpress/wp-admin/')],
            ['Temas', url('wordpress/wp-admin/themes.php')],
        ];
    } elseif ($folder === 'portal') {
        $buttons = [['Abrir portal', 'http://portal.local.com.br/']];
    } elseif (is_file($directory . '/artisan') && is_dir($directory . '/routes')) {
        $apiRoutes = routesIn($directory . '/routes/api.php');
        $webRoutes = routesIn($directory . '/routes/web.php');
        $type = $webRoutes ? 'Laravel com telas' : 'Laravel API';
        if ($webRoutes && $entry !== null) $buttons[] = ['Abrir aplicação', url($entry)];
    } else {
        if ($folder === 'front-ds-portal') {
            $entry = 'front-ds-portal/views/home-view/index.php';
            $sections['Listagens'] = phpScreens($directory . '/views/listings', $folder . '/views/listings', true);
            $sections['Páginas internas'] = phpScreens($directory . '/views/internal', $folder . '/views/internal', true);
            $buttons[] = ['Autores', url('front-ds-portal/views/listings/author/author.php')];
        } elseif ($folder === 'shalom' && is_file($directory . '/public/index.php')) {
            $entry = 'shalom/public/';
        }
        if ($folder !== 'front-ds-portal') {
            // Primeiro views, depois pages; só por último lemos PHPs da raiz.
            foreach (screenFolders($directory) as $folders) {
                foreach ($folders as $relative => $screenDir) {
                    $paths = phpScreens($screenDir, $folder . '/' . $relative, true, true);
                    $sections[$relative] = array_values(array_filter($paths, function ($path) {
                        return !preg_match('~/(?:components?|partials?|layouts?|helpers?|includes?)/~i', $path);
                    }));
                }
            }
        }
        $sections['./*.php (raiz)'] = phpScreens($directory, $folder, false, true);
        if ($entry !== null) array_unshift($buttons, ['Home', url($entry)]);
    }
    $scannedAt = date('c');
    return compact('folder', 'entry', 'type', 'sections', 'buttons', 'apiRoutes', 'webRoutes', 'scannedAt');
}
function emptyProject(string $folder): array {
    return [
        'folder' => $folder, 'entry' => null, 'type' => 'Pendente',
        'sections' => [], 'buttons' => [], 'apiRoutes' => [], 'webRoutes' => [], 'scannedAt' => null,
    ];
}
function screenIndex(array $project): array {
    $index = [];
    foreach ($project['sections'] ?? [] as $section => $paths) {
        $kind = stripos($section, 'list') !== false ? 'listagem'
            : (stripos($section, 'intern') !== false ? 'página interna' : 'página');
        foreach ($paths as $path) $index[$path] = $kind;
    }
    return $index;
}
function changesText(array $old, array $new): string {
    $previous = screenIndex($old);
    $current = screenIndex($new);
    $parts = [];
    foreach (['+' => array_diff_key($current, $previous), '-' => array_diff_key($previous, $current)] as $sign => $changes) {
        foreach (array_count_values($changes) as $kind => $count) {
            $plural = ['listagem' => 'listagens', 'página' => 'páginas', 'página interna' => 'páginas internas'];
            $label = $count === 1 ? $kind : $plural[$kind];
            $parts[] = $sign . ' ' . $count . ' ' . $label . ($sign === '+' ? ' encontrada' : ' removida') . ($count === 1 ? '' : 's');
        }
    }
    $oldRoutes = count($old['apiRoutes'] ?? []);
    $newRoutes = count($new['apiRoutes'] ?? []);
    if ($newRoutes !== $oldRoutes) {
        $delta = $newRoutes - $oldRoutes;
        $parts[] = ($delta > 0 ? '+' : '-') . ' ' . abs($delta) . ' ' . (abs($delta) === 1 ? 'rota API' : 'rotas API');
    }
    return $parts ? implode('; ', $parts) . '.' : 'nenhuma mudança encontrada.';
}

if (!isset($_SESSION['htdocs_projects']) || !is_array($_SESSION['htdocs_projects'])) {
    $_SESSION['htdocs_projects'] = [];
}
unset($_SESSION['htdocs_logs']);
if (isset($_SESSION['htdocs_projects']['shalom']['buttons'])) {
    foreach ($_SESSION['htdocs_projects']['shalom']['buttons'] as &$button) {
        if ($button[1] === '/shalom/public/index.php') $button[1] = '/shalom/public/';
    }
    unset($button);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? '';
$target = $_POST['folder'] ?? '';
if ($method === 'POST' && $action === 'clear') {
    $_SESSION['htdocs_projects'] = [];
    unset($_SESSION['htdocs_flash'], $_SESSION['htdocs_logs']);
    $_SESSION['htdocs_clear_client'] = true;
}
if ($method === 'POST' && $action === 'refresh' && is_string($target)
    && preg_match('/^[a-zA-Z0-9._-]+$/', $target)
    && is_dir($root . '/' . $target)
    && $target !== 'core-docs-index'
    && substr($target, 0, 1) !== '.') {
    $old = $_SESSION['htdocs_projects'][$target] ?? emptyProject($target);
    $new = scanProject($root, $target);
    $_SESSION['htdocs_projects'][$target] = $new;
    $_SESSION['htdocs_flash'] = [
        'project' => '/' . $target,
        'message' => 'releitura realizada: ' . changesText($old, $new),
    ];
}
if ($method !== 'POST' || $action === 'scan') {
    $newCount = 0;
    foreach (new DirectoryIterator($root) as $item) {
        if (!$item->isDir() || $item->isDot() || substr($item->getFilename(), 0, 1) === '.' || $item->getFilename() === 'core-docs-index') continue;
        $folder = $item->getFilename();
        if (isset($_SESSION['htdocs_projects'][$folder])) continue;
        $_SESSION['htdocs_projects'][$folder] = emptyProject($folder);
        $newCount++;
    }
    if ($action === 'scan') {
        $_SESSION['htdocs_flash'] = [
            'project' => 'Vasculhar htdocs',
            'message' => 'varredura realizada: + ' . $newCount . ' ' . ($newCount === 1 ? 'projeto encontrado' : 'projetos encontrados')
                . ($newCount ? ', pronto para vasculhar suas páginas.' : '.'),
        ];
    }
}
if ($method === 'POST') {
    header('Location: ' . ($_SERVER['PHP_SELF'] ?? '/index.php'));
    exit;
}
$scanEvent = $_SESSION['htdocs_flash'] ?? null;
$clearClient = !empty($_SESSION['htdocs_clear_client']);
unset($_SESSION['htdocs_flash'], $_SESSION['htdocs_clear_client']);
$projects = array_values($_SESSION['htdocs_projects']);
usort($projects, function ($a, $b) {
    $order = ['wordpress', 'shalom', 'portal', 'front-ds-portal', 'fdr-institucional'];
    $left = array_search($a['folder'], $order, true);
    $right = array_search($b['folder'], $order, true);
    if ($left !== false || $right !== false) return ($left === false ? 999 : $left) <=> ($right === false ? 999 : $right);
    return strnatcasecmp($a['folder'], $b['folder']);
});
