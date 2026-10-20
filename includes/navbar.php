<?php
/**
 * NAVBAR — Componente de navegação unificada
 * Uso: include __DIR__ . '/../includes/navbar.php';
 * Variáveis opcionais antes do include:
 *   $nav_projeto_id  — ID do projeto atual
 *   $nav_ambiente_id — ID do ambiente atual
 *   $nav_ativo       — 'projetos' | 'dashboard' | 'horario' | 'equipamentos' | 'cresesb'
 */

$nav_projeto_id  = $nav_projeto_id  ?? (int)($_GET['projeto_id'] ?? 0);
$nav_ambiente_id = $nav_ambiente_id ?? (int)($_GET['ambiente_id'] ?? $_GET['id'] ?? 0);
$nav_ativo       = $nav_ativo       ?? '';

// Detecta se estamos em paginas/ ou na raiz
$nav_em_paginas = strpos($_SERVER['PHP_SELF'], '/paginas/') !== false;
$nav_base = $nav_em_paginas ? '' : 'paginas/';

// Helper para link ativo
function nav_classe($ativo, $atual) {
    return $ativo === $atual ? 'nav-ativo' : '';
}
?>
<style>
.navbar-sistema {
    background: #1a3a5c;
    color: #fff;
    padding: 0;
    margin: 0 0 20px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    font-family: system-ui, -apple-system, sans-serif;
}
.navbar-sistema .nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.navbar-sistema .nav-logo {
    color: #fff;
    font-weight: bold;
    padding: 16px 20px 16px 0;
    font-size: 1.05em;
    text-decoration: none;
    white-space: nowrap;
}
.navbar-sistema .nav-links {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.navbar-sistema a.nav-link {
    color: rgba(255,255,255,.85);
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: .9em;
    transition: background .2s;
    white-space: nowrap;
}
.navbar-sistema a.nav-link:hover {
    background: rgba(255,255,255,.12);
    color: #fff;
}
.navbar-sistema a.nav-link.nav-ativo {
    background: #27ae60;
    color: #fff;
    font-weight: 600;
}
.navbar-sistema a.nav-link.nav-disabled {
    opacity: .35;
    pointer-events: none;
    cursor: not-allowed;
}
.navbar-sistema .nav-breadcrumb {
    background: rgba(0,0,0,.2);
    padding: 8px 20px;
    font-size: .85em;
    color: rgba(255,255,255,.8);
    border-top: 1px solid rgba(255,255,255,.1);
}
.navbar-sistema .nav-breadcrumb a {
    color: rgba(255,255,255,.9);
    text-decoration: none;
}
.navbar-sistema .nav-breadcrumb a:hover {
    text-decoration: underline;
}
.navbar-sistema .nav-breadcrumb .sep {
    margin: 0 8px;
    opacity: .5;
}
@media (max-width: 600px) {
    .navbar-sistema .nav-container { padding: 8px 12px; }
    .navbar-sistema .nav-logo { padding: 8px 8px 8px 0; }
    .navbar-sistema a.nav-link { padding: 8px 10px; font-size: .85em; }
}
</style>

<nav class="navbar-sistema">
    <div class="nav-container">
        <a href="<?= $nav_base ?>../index.php" class="nav-logo">🌡️ Ar Condicionado</a>
        <div class="nav-links">
            <a href="<?= $nav_base ?>residencial_projetos.php"
               class="nav-link <?= nav_classe('projetos', $nav_ativo) ?>">
                🏠 Projetos
            </a>
            <a href="<?= $nav_base ?>projeto_dashboard.php?projeto_id=<?= $nav_projeto_id ?>"
               class="nav-link <?= nav_classe('dashboard', $nav_ativo) ?> <?= $nav_projeto_id ? '' : 'nav-disabled' ?>">
                📊 Dashboard
            </a>
            <a href="<?= $nav_em_paginas ? '../' : '' ?>ambiente_horario.php?id=<?= $nav_ambiente_id ?>"
               class="nav-link <?= nav_classe('horario', $nav_ativo) ?> <?= $nav_ambiente_id ? '' : 'nav-disabled' ?>">
                📅 Horário
            </a>
            <a href="<?= $nav_base ?>equipamentos_sugeridos.php?projeto_id=<?= $nav_projeto_id ?>&ambiente_id=<?= $nav_ambiente_id ?>"
               class="nav-link <?= nav_classe('equipamentos', $nav_ativo) ?> <?= ($nav_projeto_id && $nav_ambiente_id) ? '' : 'nav-disabled' ?>">
                🔧 Equipamentos
            </a>
            <a href="<?= $nav_base ?>cidades_cresesb.php"
               class="nav-link <?= nav_classe('cresesb', $nav_ativo) ?>">
                🌞 CRESESB
            </a>
        </div>
    </div>
    <div class="nav-breadcrumb">
        <a href="<?= $nav_base ?>residencial_projetos.php">Projetos</a>
        <?php if ($nav_projeto_id): ?>
            <span class="sep">›</span>
            <a href="<?= $nav_base ?>residencial_ambiente.php?projeto_id=<?= $nav_projeto_id ?>">Ambientes</a>
        <?php endif; ?>
        <?php if ($nav_ambiente_id): ?>
            <span class="sep">›</span>
            <span>Ambiente #<?= $nav_ambiente_id ?></span>
        <?php endif; ?>
    </div>
</nav>
