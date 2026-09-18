<?php
declare(strict_types=1);
define('CORE_DOCS_INDEX', true);
require __DIR__ . '/core-docs-index/bootstrap.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Módulos locais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link href="/core-docs-index/style.css" rel="stylesheet">
</head>
<body data-clear-panel="<?= $clearClient ? '1' : '0' ?>">
<header class="hero py-3 mb-4">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="h2 fw-bold mb-0"><i class="fa-solid fa-layer-group me-2" aria-hidden="true"></i>Módulos locais</h1>
        <p class="mb-0 opacity-75">Acesso rápido aos projetos e telas em htdocs.</p>
    </div>
</header>
<main class="container pb-5">
    <div class="row align-items-center g-3 mb-4">
        <div class="col"><h2 class="h4 mb-0"><i class="fa-solid fa-folder-open me-2 text-primary" aria-hidden="true"></i>Projetos <span class="badge text-bg-secondary"><?= count($projects) ?></span></h2></div>
        <div class="col-md-7 d-flex flex-wrap gap-2">
            <input type="search" id="search" class="form-control" placeholder="Buscar projeto ou tela" aria-label="Buscar projeto ou tela">
            <form method="post" class="m-0"><input type="hidden" name="action" value="scan"><button class="btn btn-outline-primary text-nowrap"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Vasculhar htdocs</button></form>
            <button class="btn btn-outline-secondary text-nowrap" data-bs-toggle="modal" data-bs-target="#logsModal"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Logs <span id="logs-count" class="badge rounded-pill text-bg-danger ms-1 d-none" aria-label="Novos registros"></span></button>
            <form method="post" class="m-0"><input type="hidden" name="action" value="clear"><button class="btn btn-outline-danger text-nowrap"><i class="fa-solid fa-eraser me-1" aria-hidden="true"></i>Limpar sessão</button></form>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-center small text-secondary mb-3" aria-label="Legenda das telas">
        <span class="fw-semibold">Legenda:</span>
        <span><i class="fa-solid fa-list text-primary me-1" aria-hidden="true"></i>Listagens</span>
        <span><i class="fa-regular fa-file-lines text-success me-1" aria-hidden="true"></i>Páginas</span>
        <span><i class="fa-solid fa-window-maximize text-warning me-1" aria-hidden="true"></i>Internas</span>
    </div>
    <div class="row g-4" id="projects">
        <?php foreach ($projects as $project):
            $folder = $project['folder'];
            $screens = [];
            foreach ($project['sections'] as $items) $screens = array_merge($screens, $items);
            $searchData = strtolower($folder . ' ' . $project['type'] . ' ' . implode(' ', $screens));
        ?>
        <div class="col-md-6 col-xl-4 project" data-search="<?= h($searchData) ?>">
            <article class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h3 class="h5 card-title mb-0"><i class="fa-solid fa-folder text-secondary me-2" aria-hidden="true"></i><?= h($folder) ?></h3>
                        <span class="badge text-bg-light border"><?= h($project['type']) ?></span>
                    </div>
                    <p class="path text-secondary mb-3">/<?= h($folder) ?>/</p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($project['buttons'] as $button): ?>
                            <a class="btn btn-sm btn-<?= $button[0] === 'Home' || $button[0] === 'Abrir portal' ? 'primary' : 'outline-primary' ?>" href="<?= h($button[1]) ?>"><i class="fa-solid <?= $button[0] === 'Home' ? 'fa-house' : ($button[0] === 'Temas' ? 'fa-palette' : 'fa-arrow-up-right-from-square') ?> me-1" aria-hidden="true"></i><?= h($button[0]) ?></a>
                        <?php endforeach; ?>
                        <?php if ($project['apiRoutes']): ?>
                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#api-<?= h($folder) ?>"><i class="fa-solid fa-code me-1" aria-hidden="true"></i>Rotas API (<?= count($project['apiRoutes']) ?>)</button>
                        <?php endif; ?>
                        <form method="post" class="m-0">
                            <input type="hidden" name="action" value="refresh">
                            <input type="hidden" name="folder" value="<?= h($folder) ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Vasculhar projeto</button>
                        </form>
                    </div>
                    <?php if ($project['type'] === 'Laravel API'): ?>
                        <p class="small text-secondary mt-3 mb-0">Somente rotas API; nenhuma tela web detectada.</p>
                    <?php endif; ?>
                    <?php foreach ($project['sections'] as $section => $items): ?>
                        <?php if (!$items) continue; ?>
                        <?php
                            $isListing = stripos($section, 'list') !== false;
                            $isInternal = stripos($section, 'intern') !== false;
                            $icon = $isListing ? 'fa-solid fa-list text-primary' : ($isInternal ? 'fa-solid fa-window-maximize text-warning' : 'fa-regular fa-file-lines text-success');
                        ?>
                        <h4 class="h6 mt-4 mb-2"><i class="<?= $icon ?> me-2" aria-hidden="true"></i><?= h($section) ?></h4>
                        <div class="d-flex flex-wrap gap-2" aria-label="<?= h($section) ?>">
                            <?php foreach ($items as $path): ?>
                                <a class="btn btn-sm btn-outline-<?= $isListing ? 'primary' : ($isInternal ? 'warning' : 'success') ?>"
                                   href="<?= h(screenUrl($folder, $path)) ?>"
                                   title="<?= h(substr($path, strlen($folder) + 1)) ?>">
                                    <i class="<?= $icon ?> me-1" aria-hidden="true"></i><?= h(screenTitle($path)) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
        <?php endforeach; ?>
    </div>
    <p id="empty" class="text-secondary mt-4 d-none">Nenhum projeto ou tela encontrado.</p>
</main>

<div class="modal fade" id="logsModal" tabindex="-1" aria-labelledby="logsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="logsTitle"><i class="fa-solid fa-clock-rotate-left me-2 text-primary" aria-hidden="true"></i>Registro da varredura</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body"><div id="log-cards" class="row g-3"></div></div>
            <div class="modal-footer"><button type="button" id="clear-logs" class="btn btn-outline-danger btn-sm">Limpar histórico</button></div>
        </div>
    </div>
</div>

<?php foreach ($projects as $project): if (!$project['apiRoutes']) continue; ?>
    <div class="modal fade" id="api-<?= h($project['folder']) ?>" tabindex="-1" aria-labelledby="api-title-<?= h($project['folder']) ?>" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h2 class="modal-title fs-5" id="api-title-<?= h($project['folder']) ?>">Rotas API: <?= h($project['folder']) ?></h2><p class="small text-secondary mb-0">Extraídas de routes/api.php. Campos do body são exemplos ilustrativos; consulte o controller para o formato real.</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <?php foreach ($project['apiRoutes'] as $route):
                            $body = preg_match('/POST|PUT|PATCH/i', $route['methods']) ? "{\n  \"campo\": \"valor\"\n}" : 'Não se aplica a GET/DELETE.';
                        ?>
                        <div class="col-md-6"><div class="card border shadow-sm h-100"><div class="card-body">
                            <span class="badge text-bg-primary mb-2"><?= h($route['methods']) ?></span>
                            <div class="small fw-semibold">URL</div>
                            <code class="route-url d-block mb-2">/<?= h($project['folder']) ?>/public/api<?= h('/' . ltrim($route['path'], '/')) ?></code>
                            <div class="small fw-semibold">PARAMS</div>
                            <p class="small mb-2"><?= $route['params'] ? h(implode(', ', $route['params'])) : 'Nenhum parâmetro na URL identificado.' ?></p>
                            <div class="small fw-semibold">BODY exemplo</div>
                            <pre class="route-body bg-light rounded p-2 small mb-0"><?= h($body) ?></pre>
                        </div></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script id="scan-event" type="application/json"><?= json_encode($scanEvent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/core-docs-index/app.js"></script>
</body>
</html>
