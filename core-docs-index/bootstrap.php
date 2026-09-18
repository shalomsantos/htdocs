<?php
declare(strict_types=1);
if (!defined('CORE_DOCS_INDEX')) { http_response_code(403); exit; }
session_start();

$root = dirname(__DIR__);

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path): string {
    return '/' . implode('/', array_map('rawurlencode', explode('/', trim(str_replace('\\', '/', $path), '/'))));
}
function entryPoint(string $directory, string $folder): ?string {
    foreach (['index.php', 'home.php', 'public/index.php', 'public/home.php'] as $file) {
        if (is_file($directory . '/' . $file)) return $folder . '/' . $file;
    }
    return null;
}
function phpScreens(string $directory, string $prefix, bool $recursive = false): array {
    if (!is_dir($directory)) return [];
    $files = [];
    $iterator = $recursive
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS))
        : new DirectoryIterator($directory);
    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') continue;
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($directory) + 1));
        if (substr(basename($relative), 0, 1) === '_') continue;
        $files[] = $prefix . '/' . $relative;
    }
    natcasesort($files);
    return array_values($files);
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
            $entry = 'shalom/public/index.php';
        }
        $sections['Páginas'] = phpScreens($directory, $folder);
        foreach (['views', 'Views', 'pages', 'Pages'] as $viewFolder) {
            if ($folder !== 'front-ds-portal' && is_dir($directory . '/' . $viewFolder)) {
                $sections[$viewFolder] = phpScreens($directory . '/' . $viewFolder, $folder . '/' . $viewFolder, true);
            }
        }
        if ($entry !== null) array_unshift($buttons, ['Home', url($entry)]);
    }
    return compact('folder', 'entry', 'type', 'sections', 'buttons', 'apiRoutes', 'webRoutes');
}
function addLog(string $message): void {
    array_unshift($_SESSION['htdocs_logs'], ['time' => date('d/m/Y H:i:s'), 'message' => $message]);
    $_SESSION['htdocs_logs'] = array_slice($_SESSION['htdocs_logs'], 0, 60);
}

if (!isset($_SESSION['htdocs_projects']) || !is_array($_SESSION['htdocs_projects'])) {
    $_SESSION['htdocs_projects'] = [];
    $_SESSION['htdocs_logs'] = [];
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? '';
$target = $_POST['folder'] ?? '';
if ($method === 'POST' && $action === 'refresh' && is_string($target)
    && preg_match('/^[a-zA-Z0-9._-]+$/', $target)
    && is_dir($root . '/' . $target)
    && $target !== 'core-docs-index'
    && substr($target, 0, 1) !== '.') {
    $_SESSION['htdocs_projects'][$target] = scanProject($root, $target);
    addLog($target . ': releitura concluída. ' . count($_SESSION['htdocs_projects'][$target]['apiRoutes']) . ' rotas API detectadas.');
}
if ($method !== 'POST' || $action === 'scan') {
    $new = 0;
    foreach (new DirectoryIterator($root) as $item) {
        if (!$item->isDir() || $item->isDot() || substr($item->getFilename(), 0, 1) === '.' || $item->getFilename() === 'core-docs-index') continue;
        $folder = $item->getFilename();
        if (isset($_SESSION['htdocs_projects'][$folder])) continue;
        $_SESSION['htdocs_projects'][$folder] = scanProject($root, $folder);
        addLog($folder . ': novo projeto mapeado como ' . $_SESSION['htdocs_projects'][$folder]['type'] . '.');
        $new++;
    }
    if ($action === 'scan') addLog($new ? $new . ' novo(s) projeto(s) encontrado(s).' : 'Nenhum projeto novo encontrado; os já mapeados foram mantidos em cache.');
}
if ($method === 'POST') {
    header('Location: ' . ($_SERVER['PHP_SELF'] ?? '/index.php'));
    exit;
}
$projects = array_values($_SESSION['htdocs_projects']);
usort($projects, function ($a, $b) {
    $order = ['wordpress', 'shalom', 'portal', 'front-ds-portal', 'fdr-institucional'];
    $left = array_search($a['folder'], $order, true);
    $right = array_search($b['folder'], $order, true);
    if ($left !== false || $right !== false) return ($left === false ? 999 : $left) <=> ($right === false ? 999 : $right);
    return strnatcasecmp($a['folder'], $b['folder']);
});
