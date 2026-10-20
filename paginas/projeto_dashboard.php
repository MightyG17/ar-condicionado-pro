<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/calculo_horario.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
if (!$projeto_id) { header("Location: residencial_projetos.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
if (!$proj) { header("Location: residencial_projetos.php"); exit; }

$msg = '';
$tipo_msg = 'ok';

// ============================================================
// AÇÃO: RECALCULAR TODOS OS AMBIENTES (Etapa A)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'recalcular_todos') {
    $ambientes = $conn->query("SELECT id, nome FROM ambientes WHERE projeto_id = $projeto_id");
    $total = 0; $ok = 0; $erros = [];
    while ($a = $ambientes->fetch_assoc()) {
        $total++;
        $r = calcular_perfil_horario($conn, $a['id']);
        if (!empty($r['sucesso'])) $ok++;
        else $erros[] = $a['nome'] . ': ' . ($r['erro'] ?? '?');
    }
    $msg = "✅ Recalculados: $ok/$total ambientes" . (empty($erros) ? '' : " · Erros: " . implode(', ', $erros));
    if (!empty($erros)) $tipo_msg = 'warn';
}

// ============================================================
// CARREGA AMBIENTES DO PROJETO
// ============================================================
$ambientes = [];
$r = $conn->query("
    SELECT a.*,
           (a.comprimento_m * a.largura_m) AS area_m2,
           (a.comprimento_m * a.largura_m * a.peDireita_m) AS volume_m3
    FROM ambientes a
    WHERE a.projeto_id = $projeto_id
    ORDER BY a.id
");
while ($row = $r->fetch_assoc()) $ambientes[] = $row;

// ============================================================
// PICO HORÁRIO (Etapa A) por ambiente
// ============================================================
$pico_horario = [];
foreach ($ambientes as $a) {
    $q = $conn->query("
        SELECT mes, hora, carga_total_w, carga_solar_w, carga_interna_w, carga_conducao_w
        FROM carga_horaria
        WHERE ambiente_id = {$a['id']}
        ORDER BY carga_total_w DESC LIMIT 1
    ");
    if ($q && $row = $q->fetch_assoc()) {
        $pico_horario[$a['id']] = $row;
    }
}

$dados_componentes = [];
foreach ($ambientes as $a) {
    $q = $conn->query("
        SELECT carga_solar_w, carga_interna_w, carga_latente_pessoas_w,
               carga_infiltracao_sens_w, carga_infiltracao_lat_w,
               carga_conducao_w, margem_seguranca_w
        FROM carga_horaria
        WHERE ambiente_id = {$a['id']}
        ORDER BY carga_total_w DESC LIMIT 1
    ");
    if ($q && $row = $q->fetch_assoc()) {
        $dados_componentes[$a['id']] = $row;
    }
}

// ============================================================
// RESULTADO FIXO (Etapa 5) por ambiente
// ============================================================
$resultado_fixo = [];
$r = $conn->query("
    SELECT * FROM resultados_calculo
    WHERE projeto_id = $projeto_id
");
if ($r) {
    while ($row = $r->fetch_assoc()) $resultado_fixo[$row['ambiente_id']] = $row;
}

// ============================================================
// TOTAIS
// ============================================================
$total_horario_w = 0; $total_fixo_w = 0; $total_btu_horario = 0; $total_btu_fixo = 0;
foreach ($ambientes as $a) {
    if (isset($pico_horario[$a['id']])) {
        $total_horario_w += (float)$pico_horario[$a['id']]['carga_total_w'];
        $total_btu_horario += (float)$pico_horario[$a['id']]['carga_total_w'] * 3.412;
    }
    if (isset($resultado_fixo[$a['id']])) {
        $total_fixo_w += (float)$resultado_fixo[$a['id']]['carga_total_w'];
        $total_btu_fixo += (float)$resultado_fixo[$a['id']]['carga_total_btu'];
    }
}
$total_horario_tr = $total_btu_horario / 12000;
$total_fixo_tr = $total_btu_fixo / 12000;

// ============================================================
// ALERTAS
// ============================================================
$alertas = [];
foreach ($ambientes as $a) {
    $tem_h = isset($pico_horario[$a['id']]);
    $tem_f = isset($resultado_fixo[$a['id']]);
    if (!$tem_h) $alertas[] = "⚠️ Ambiente '{$a['nome']}' sem cálculo horário (Etapa A).";
    if ($tem_h && $tem_f) {
        $h = (float)$pico_horario[$a['id']]['carga_total_w'];
        $f = (float)$resultado_fixo[$a['id']]['carga_total_w'];
        if ($f > 0) {
            $dif = abs($h - $f) / $f * 100;
            if ($dif > 20) {
                $alertas[] = sprintf(
                    "ℹ️ Ambiente '%s': diferença de %.1f%% entre pico real (%.0f W) e pior caso (%.0f W). Valores podem diferir — as duas etapas medem coisas diferentes.",
                    $a['nome'], $dif, $h, $f
                );
            }
        }
    }
}

// ============================================================
// SVG: BARRAS HORIZONTAIS
// ============================================================
$max_carga = 1;
foreach ($ambientes as $a) {
    if (isset($pico_horario[$a['id']])) {
        $max_carga = max($max_carga, (float)$pico_horario[$a['id']]['carga_total_w']);
    }
}

// Paleta de cores
$cores = ['#3498db','#e74c3c','#f39c12','#27ae60','#9b59b6','#1abc9c','#e67e22','#34495e'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Dashboard — <?= htmlspecialchars($proj['nome']) ?></title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 24px; margin: 0 auto 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 1200px; }
h1, h2 { color: #1a3a5c; margin-top: 0; }
.voltar { display: inline-block; margin-bottom: 16px; color: #3498db; text-decoration: none; font-weight: 500; }
.voltar:hover { text-decoration: underline; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
.stat { background: #f0f4f8; padding: 16px; border-radius: 10px; border-left: 4px solid #3498db; }
.stat.warn { border-left-color: #e74c3c; background: #fdeaea; }
.stat.ok { border-left-color: #27ae60; background: #eafaf1; }
.stat-label { font-size: .75em; color: #666; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.stat-value { font-size: 1.6em; color: #1a3a5c; font-weight: bold; line-height: 1.1; }
.stat-sub { font-size: .85em; color: #888; margin-top: 4px; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: .9em; }
th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #1a3a5c; color: #fff; font-weight: 500; }
tr:hover { background: #f8fafc; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.btn { background: #27ae60; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1em; cursor: pointer; font-weight: bold; }
.btn:hover { background: #219150; }
.msg { padding: 12px 16px; border-radius: 8px; margin: 16px 0; }
.msg.ok { background: #d4edda; border-left: 4px solid #27ae60; }
.msg.warn { background: #fff3cd; border-left: 4px solid #f39c12; }
.alerta { background: #e8f4fd; border-left: 4px solid #3498db; padding: 10px 14px; border-radius: 6px; margin: 8px 0; font-size: .9em; }
.barra-container { display: flex; align-items: center; margin: 8px 0; }
.barra-label { width: 140px; font-size: .9em; color: #333; font-weight: 500; }
.barra-bg { flex: 1; height: 26px; background: #f0f4f8; border-radius: 6px; overflow: hidden; position: relative; }
.barra-fill { height: 100%; border-radius: 6px; transition: width .4s ease; }
.barra-valor { width: 110px; text-align: right; font-size: .85em; font-weight: 600; color: #1a3a5c; }
.dif-pos { color: #e74c3c; font-weight: bold; }
.dif-neg { color: #27ae60; font-weight: bold; }
.footer { text-align: center; color: #999; font-size: .8em; margin: 30px 0 10px; }
.acoes { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<a href="residencial_ambiente.php?projeto_id=<?= $projeto_id ?>" class="voltar">← Voltar para ambientes</a>

<div class="card">
    <h1>🏢 Dashboard — <?= htmlspecialchars($proj['nome']) ?></h1>
    <p style="color:#666; margin: 4px 0;">
        Cliente: <strong><?= htmlspecialchars($proj['nome_cliente'] ?? '—') ?></strong> ·
        Cidade: <strong><?= htmlspecialchars($proj['cidade'] ?? '—') ?>/<?= htmlspecialchars($proj['uf'] ?? '') ?></strong> ·
        Ambientes: <strong><?= count($ambientes) ?></strong>
    </p>

    <?php if ($msg): ?>
        <div class="msg <?= $tipo_msg ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (!empty($alertas)): ?>
        <div style="margin-top: 16px;">
            <?php foreach ($alertas as $al): ?>
                <div class="alerta"><?= htmlspecialchars($al) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>📊 Totais do Projeto</h2>
    <div class="grid">
        <div class="stat ok">
            <div class="stat-label">Pico Horário (Etapa A)</div>
            <div class="stat-value"><?= number_format($total_horario_w, 0, ',', '.') ?> W</div>
            <div class="stat-sub"><?= number_format($total_btu_horario, 0, ',', '.') ?> BTU/h · <?= number_format($total_horario_tr, 2, ',', '.') ?> TR</div>
        </div>
        <div class="stat warn">
            <div class="stat-label">Pico Fixo (Etapa 5)</div>
            <div class="stat-value"><?= number_format($total_fixo_w, 0, ',', '.') ?> W</div>
            <div class="stat-sub"><?= number_format($total_btu_fixo, 0, ',', '.') ?> BTU/h · <?= number_format($total_fixo_tr, 2, ',', '.') ?> TR</div>
        </div>
        <div class="stat">
            <div class="stat-label">Ambientes</div>
            <div class="stat-value"><?= count($ambientes) ?></div>
            <div class="stat-sub"><?= count($pico_horario) ?> com perfil horário</div>
        </div>
        <?php if ($total_fixo_w > 0): 
            $dif = ($total_fixo_w - $total_horario_w) / $total_fixo_w * 100;
        ?>
        <div class="stat">
            <div class="stat-label">Diferença</div>
            <div class="stat-value <?= $dif > 0 ? 'dif-pos' : 'dif-neg' ?>">
                <?= number_format($dif, 1, ',', '.') ?>%
            </div>
            <div class="stat-sub">Fixo vs Horário</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="acoes">
        <form method="POST" style="display:inline;">
            <input type="hidden" name="acao" value="recalcular_todos">
            <button type="submit" class="btn">🔄 Recalcular Todos os Ambientes</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>📈 Distribuição de Carga por Ambiente</h2>
    <?php if (empty($pico_horario)): ?>
        <p style="color:#999;">Nenhum cálculo horário encontrado. Clique em "Recalcular Todos" acima.</p>
    <?php else: ?>
        <?php foreach ($ambientes as $i => $a): 
            if (!isset($pico_horario[$a['id']])) continue;
            $carga = (float)$pico_horario[$a['id']]['carga_total_w'];
            $pct = $max_carga > 0 ? ($carga / $max_carga * 100) : 0;
            $cor = $cores[$i % count($cores)];
        ?>
        <div class="barra-container">
            <div class="barra-label"><?= htmlspecialchars($a['nome']) ?></div>
            <div class="barra-bg">
                <div class="barra-fill" style="width: <?= $pct ?>%; background: <?= $cor ?>;"></div>
            </div>
            <div class="barra-valor">
                <?= number_format($carga, 0, ',', '.') ?> W
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>📋 Detalhamento por Ambiente</h2>
    <table>
        <thead>
            <tr>
                <th>Ambiente</th>
                <th class="num">Área (m²)</th>
                <th class="num">Solar</th><th class="num">Interna</th><th class="num">Infiltração</th><th class="num">Condução</th><th class="num">Pico (W)</th>
                <th class="num">Pico Horário (BTU/h)</th>
                <th class="num">Pico Fixo (W)</th>
                <th class="num">Diferença</th>
                <th>Equipamento (Etapa 5)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ambientes as $a): 
            $h = isset($pico_horario[$a['id']]) ? (float)$pico_horario[$a['id']]['carga_total_w'] : null;
            $f = isset($resultado_fixo[$a['id']]) ? (float)$resultado_fixo[$a['id']]['carga_total_w'] : null;
            $equip = isset($resultado_fixo[$a['id']]) ? $resultado_fixo[$a['id']]['aparelho_recomendado'] : '—';
            
            $dif_html = '—';
            if ($h !== null && $f !== null && $f > 0) {
                $dif = ($f - $h) / $f * 100;
                $classe = $dif > 0 ? 'dif-pos' : 'dif-neg';
                $dif_html = '<span class="' . $classe . '">' . number_format($dif, 1, ',', '.') . '%</span>';
            }
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['nome']) ?></strong></td>
                <td class="num"><?= number_format($a['area_m2'], 2, ',', '.') ?></td>
                <?php 
            $comp = $dados_componentes[$a['id']] ?? [];
            $inf_total = (float)($comp['carga_infiltracao_sens_w'] ?? 0) + (float)($comp['carga_infiltracao_lat_w'] ?? 0);
        ?>
                <td class="num"><?= number_format((float)($comp['carga_solar_w'] ?? 0), 0, ',', '.') ?></td>
                <td class="num"><?= number_format((float)($comp['carga_interna_w'] ?? 0) + (float)($comp['carga_latente_pessoas_w'] ?? 0), 0, ',', '.') ?></td>
                <td class="num"><?= number_format($inf_total, 0, ',', '.') ?></td>
                <td class="num"><?= number_format((float)($comp['carga_conducao_w'] ?? 0), 0, ',', '.') ?></td>
                <td class="num"><?= $h !== null ? number_format($h, 0, ',', '.') : '—' ?></td>
                <td class="num"><?= $h !== null ? number_format($h * 3.412, 0, ',', '.') : '—' ?></td>
                <td class="num"><?= $f !== null ? number_format($f, 0, ',', '.') : '—' ?></td>
                <td class="num"><?= $dif_html ?></td>
                <td><?= htmlspecialchars($equip) ?>
                    <a href="equipamentos_sugeridos.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $a['id'] ?>" 
                       style="display:inline-block;margin-left:8px;padding:4px 10px;background:#3498db;color:#fff;border-radius:6px;text-decoration:none;font-size:.8em;">
                        🔧 Ver modelos
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="footer">
    Sistema de Dimensionamento de Ar Condicionado · Dashboard v1 (Etapa B) · <?= date('d/m/Y H:i') ?>
</div>

</body>
</html>
