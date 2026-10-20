<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);
if (!$projeto_id || !$ambiente_id) { header("Location: residencial_ambiente.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
$amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();
if (!$proj || !$amb) { header("Location: residencial_ambiente.php?projeto_id=$projeto_id"); exit; }

function val($arr, $chave, $padrao = '') {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

// ============================================================
// CARREGAR RESULTADOS
// ============================================================
$res = $conn->query("SELECT * FROM resultados_calculo WHERE ambiente_id = $ambiente_id ORDER BY id DESC LIMIT 1")->fetch_assoc();

if (!$res) {
    // Sem resultado salvo — redireciona para dimensionamento
    header("Location: ambiente_dimensionamento.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
    exit;
}

// ============================================================
// CALCULAR PERCENTUAIS
// ============================================================
$q_conducao  = (float)($res['carga_conducao_w'] ?? 0);
$q_solar     = (float)($res['carga_solar_w'] ?? 0);
$q_interna   = (float)($res['carga_interna_w'] ?? 0);
$q_ventilacao = (float)($res['carga_ventilacao_w'] ?? 0);
$q_bruta     = (float)($res['carga_bruta_w'] ?? 0);
$q_total     = (float)($res['carga_total_w'] ?? 0);
$q_sensivel  = (float)($res['carga_sensivel_w'] ?? 0);
$q_latente   = (float)($res['carga_latente_w'] ?? 0);
$shr         = (float)($res['shr'] ?? 0);
$vazao       = (float)($res['vazao_ar_m3h'] ?? 0);
$btu         = (float)($res['carga_total_btu'] ?? 0);
$equip       = $res['aparelho_recomendado'] ?? '';
$delta_t     = (float)($res['delta_t_c'] ?? 0);
$volume      = (float)($res['volume_m3'] ?? 0);

// Percentuais (baseado no q_bruta para ter sentido)
$total_pct = $q_bruta > 0 ? $q_bruta : 1;
$pct_conducao  = $q_conducao / $total_pct * 100;
$pct_solar     = $q_solar / $total_pct * 100;
$pct_interna   = $q_interna / $total_pct * 100;
$pct_ventilacao = $q_ventilacao / $total_pct * 100;

// ============================================================
// BUSCAR OUTROS AMBIENTES DO PROJETO
// ============================================================
$outros = [];
$res_outros = $conn->query("
    SELECT a.id, a.nome, r.carga_total_btu, r.aparelho_recomendado
    FROM ambientes a
    LEFT JOIN resultados_calculo r ON r.ambiente_id = a.id
    WHERE a.projeto_id = $projeto_id AND a.id != $ambiente_id
    ORDER BY a.nome
");
while ($o = $res_outros->fetch_assoc()) {
    $outros[] = $o;
}

// Data
$data_calculo = $res['data_calculo'] ?? date('Y-m-d H:i:s');
$data_formatada = date('d/m/Y', strtotime($data_calculo));

// SHR status
if ($shr >= 0.90) $shr_status = ['texto' => 'Muito Seco', 'cor' => '#f59e0b', 'bg' => '#fffbeb'];
elseif ($shr >= 0.80) $shr_status = ['texto' => 'Ideal', 'cor' => '#10b981', 'bg' => '#ecfdf5'];
elseif ($shr >= 0.70) $shr_status = ['texto' => 'Úmido — OK', 'cor' => '#10b981', 'bg' => '#ecfdf5'];
else $shr_status = ['texto' => 'Muito Úmido', 'cor' => '#ef4444', 'bg' => '#fef2f2'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 6 — Resultado Final</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1100px; margin:0 auto; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { flex:1; text-align:center; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#10b981; border-bottom:3px solid #10b981; font-weight:700; }
.passo-link.concluido { color:#2563eb; border-bottom:2px solid #bfdbfe; }
.cabecalho { background:linear-gradient(135deg, #ecfdf5, #d1fae5); padding:28px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.5rem; color:#065f46; margin-bottom:8px; }
h2 { font-size:1.1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.subtitulo { color:#047857; margin-bottom:12px; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
table { width:100%; border-collapse:collapse; margin-top:8px; }
th { background:#f1f5f9; padding:10px 12px; text-align:left; font-weight:700; color:#475569; font-size:0.8rem; text-transform:uppercase; }
td { padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:0.9rem; color:#334155; }
tfoot td { background:#f1f5f9; font-weight:700; color:#0f172a; }
.btn { padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; gap:6px; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-print { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:#fff; }
.btn-finalizar { background:linear-gradient(135deg, #10b981, #059669); color:#fff; }
.btn-outro { background:#dbeafe; color:#1e40af; padding:8px 16px; font-size:0.85rem; }
.botoes { display:flex; gap:12px; margin-top:24px; justify-content:space-between; padding-top:20px; border-top:2px solid #e2e8f0; flex-wrap:wrap; }
.card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-top:16px; }
.card { background:#fff; padding:18px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.card-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.card-valor { font-size:1.6rem; font-weight:800; }
.card-sub { font-size:0.75rem; color:#64748b; margin-top:4px; }
.destaque { background:linear-gradient(135deg, #10b981, #059669); color:#fff; padding:32px; border-radius:12px; text-align:center; margin:24px 0; box-shadow:0 4px 12px rgba(16,185,129,0.3); }
.destaque h3 { font-size:0.9rem; text-transform:uppercase; letter-spacing:2px; opacity:0.9; margin-bottom:12px; }
.destaque .valor { font-size:2.8rem; font-weight:900; }
.destaque .sub { font-size:1rem; margin-top:12px; opacity:0.95; }
.grafico { display:flex; height:40px; border-radius:8px; overflow:hidden; margin-top:12px; }
.grafico-seg { display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:0.8rem; transition:transform 0.2s; }
.grafico-seg:hover { transform:scaleY(1.1); }
.legenda { display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; font-size:0.85rem; }
.legenda-item { display:flex; align-items:center; gap:6px; }
.legenda-cor { width:14px; height:14px; border-radius:3px; }
.ambientes-lista { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-top:12px; }
.ambiente-card { background:#f8fafc; padding:14px; border-radius:8px; border-left:4px solid #3b82f6; text-decoration:none; color:#334155; transition:transform 0.2s; }
.ambiente-card:hover { transform:translateY(-2px); background:#eff6ff; }
.ambiente-card strong { display:block; font-size:0.95rem; margin-bottom:4px; color:#1e40af; }
.ambiente-card small { color:#64748b; font-size:0.8rem; }
.rodape { text-align:center; color:#94a3b8; font-size:0.8rem; margin-top:32px; padding-top:16px; border-top:1px solid #e2e8f0; }

/* ============================================================ */
/* ESTILOS DE IMPRESSÃO */
/* ============================================================ */
@media print {
    body { background:#fff; padding:0; max-width:100%; }
    .barra-progresso, .btn, .botoes, .ambiente-card, .ambientes-lista h2 { display:none !important; }
    .cabecalho { background:#fff !important; border:2px solid #065f46; box-shadow:none; }
    .form-card { box-shadow:none; border:1px solid #e2e8f0; page-break-inside:avoid; }
    .destaque { background:#065f46 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    h1 { color:#065f46 !important; }
    @page { margin: 1.5cm; }
}
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php $nav_ambiente_id = (int)($ambiente_id ?? ($_GET['ambiente_id'] ?? ($_GET['id'] ?? 0))); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<div class="barra-progresso">
    <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 1. Dados</a>
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 2. Paredes</a>
    <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 3. Solar</a>
    <a href="ambiente_cargas_internas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 4. Internas</a>
    <a href="ambiente_dimensionamento.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 5. Dimensionamento</a>
    <a class="passo-link ativo">🏆 6. Resultado</a>
</div>

<div class="cabecalho">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div>
            <h1>🏆 Memorial de Cálculo — Resultado Final</h1>
            <p class="subtitulo">
                <strong><?= htmlspecialchars($proj['nome']) ?></strong>
                · Cliente: <?= htmlspecialchars(val($proj, 'nome_cliente', '—')) ?>
                · <?= htmlspecialchars($data_formatada) ?>
            </p>
        </div>
        <div style="background:#fff; padding:12px 20px; border-radius:8px;">
            <div style="font-size:0.7rem; color:#047857; text-transform:uppercase; font-weight:700;">Ambiente</div>
            <div style="font-size:1.1rem; color:#065f46; font-weight:700;"><?= htmlspecialchars($amb['nome']) ?></div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- IDENTIFICAÇÃO -->
<!-- ============================================================ -->
<div class="form-card">
    <h2>📋 Identificação</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div>
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; font-weight:700;">Projeto</div>
            <div style="font-size:1rem; color:#1e293b; font-weight:600; margin-top:4px;"><?= htmlspecialchars($proj['nome']) ?></div>
        </div>
        <div>
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; font-weight:700;">Cliente</div>
            <div style="font-size:1rem; color:#1e293b; font-weight:600; margin-top:4px;"><?= htmlspecialchars(val($proj, 'nome_cliente', '—')) ?></div>
        </div>
        <div>
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; font-weight:700;">Local</div>
            <div style="font-size:1rem; color:#1e293b; font-weight:600; margin-top:4px;">
                <?= htmlspecialchars(val($proj, 'cidade', '—')) ?><?= val($proj, 'uf') ? '/' . htmlspecialchars($proj['uf']) : '' ?>
            </div>
        </div>
        <div>
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; font-weight:700;">Data do Cálculo</div>
            <div style="font-size:1rem; color:#1e293b; font-weight:600; margin-top:4px;"><?= $data_formatada ?></div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CARACTERÍSTICAS DO AMBIENTE -->
<!-- ============================================================ -->
<div class="form-card">
    <h2>🏠 Características do Ambiente</h2>
    <div class="card-grid">
        <div class="card" style="border-left:4px solid #0ea5e9;">
            <div class="card-label" style="color:#0369a1;">ÁREA</div>
            <div class="card-valor" style="color:#0369a1;">
                <?= number_format(($comp = (float)val($amb, 'comprimento_m', 0)) * (float)val($amb, 'largura_m', 0), 1, ',', '.') ?> m²
            </div>
            <div class="card-sub">
                <?= number_format($comp, 1, ',', '.') ?> × <?= number_format((float)val($amb, 'largura_m', 0), 1, ',', '.') ?> m
            </div>
        </div>
        <div class="card" style="border-left:4px solid #10b981;">
            <div class="card-label" style="color:#047857;">VOLUME</div>
            <div class="card-valor" style="color:#047857;"><?= number_format($volume, 1, ',', '.') ?> m³</div>
            <div class="card-sub">pé-direito <?= number_format((float)val($amb, 'peDireita_m', 2.8), 2, ',', '.') ?> m</div>
        </div>
        <div class="card" style="border-left:4px solid #f59e0b;">
            <div class="card-label" style="color:#92400e;">ΔT PROJETO</div>
            <div class="card-valor" style="color:#92400e;"><?= number_format($delta_t, 1, ',', '.') ?> °C</div>
            <div class="card-sub">
                <?= number_format((float)val($proj, 'temp_externa_verao', 30), 1, ',', '.') ?>°C → <?= number_format((float)val($proj, 'temp_interna_verao', 23), 1, ',', '.') ?>°C
            </div>
        </div>
        <div class="card" style="border-left:4px solid #8b5cf6;">
            <div class="card-label" style="color:#5b21b6;">USO</div>
            <div class="card-valor" style="color:#5b21b6; font-size:1.2rem;">
                <?= htmlspecialchars(val($amb, 'uso_principal', '—')) ?>
            </div>
            <div class="card-sub"><?= (int)val($amb, 'pessoas', 2) ?> pessoa(s)</div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- BALANÇO TÉRMICO -->
<!-- ============================================================ -->
<div class="form-card">
    <h2>📊 Balanço Térmico</h2>
    <table>
        <thead>
            <tr>
                <th>Componente</th>
                <th style="text-align:right;">Carga (W)</th>
                <th style="text-align:right;">Percentual</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>🧱 Condução (paredes + janelas)</td>
                <td style="text-align:right;"><?= number_format($q_conducao, 1, ',', '.') ?></td>
                <td style="text-align:right; color:#64748b;"><?= number_format($pct_conducao, 1, ',', '.') ?>%</td>
            </tr>
            <tr>
                <td>☀️ Radiação Solar</td>
                <td style="text-align:right;"><?= number_format($q_solar, 1, ',', '.') ?></td>
                <td style="text-align:right; color:#64748b;"><?= number_format($pct_solar, 1, ',', '.') ?>%</td>
            </tr>
            <tr>
                <td>💨 Infiltração de Ar</td>
                <td style="text-align:right;"><?= number_format($q_ventilacao, 1, ',', '.') ?></td>
                <td style="text-align:right; color:#64748b;"><?= number_format($pct_ventilacao, 1, ',', '.') ?>%</td>
            </tr>
            <tr>
                <td>👥 Cargas Internas</td>
                <td style="text-align:right;"><?= number_format($q_interna, 1, ',', '.') ?></td>
                <td style="text-align:right; color:#64748b;"><?= number_format($pct_interna, 1, ',', '.') ?>%</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>CARGA BRUTA TOTAL</td>
                <td style="text-align:right; color:#065f46;"><?= number_format($q_bruta, 1, ',', '.') ?> W</td>
                <td style="text-align:right; color:#065f46;">100%</td>
            </tr>
        </tfoot>
    </table>

    <!-- Gráfico de Barras -->
    <div class="grafico">
        <?php if ($pct_conducao > 0): ?>
        <div class="grafico-seg" style="width:<?= $pct_conducao ?>%; background:#3b82f6;">
            <?= $pct_conducao > 8 ? number_format($pct_conducao, 0) . '%' : '' ?>
        </div>
        <?php endif; ?>
        <?php if ($pct_solar > 0): ?>
        <div class="grafico-seg" style="width:<?= $pct_solar ?>%; background:#f59e0b;">
            <?= $pct_solar > 8 ? number_format($pct_solar, 0) . '%' : '' ?>
        </div>
        <?php endif; ?>
        <?php if ($pct_ventilacao > 0): ?>
        <div class="grafico-seg" style="width:<?= $pct_ventilacao ?>%; background:#06b6d4;">
            <?= $pct_ventilacao > 8 ? number_format($pct_ventilacao, 0) . '%' : '' ?>
        </div>
        <?php endif; ?>
        <?php if ($pct_interna > 0): ?>
        <div class="grafico-seg" style="width:<?= $pct_interna ?>%; background:#8b5cf6;">
            <?= $pct_interna > 8 ? number_format($pct_interna, 0) . '%' : '' ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="legenda">
        <div class="legenda-item"><div class="legenda-cor" style="background:#3b82f6;"></div> Condução</div>
        <div class="legenda-item"><div class="legenda-cor" style="background:#f59e0b;"></div> Radiação Solar</div>
        <div class="legenda-item"><div class="legenda-cor" style="background:#06b6d4;"></div> Infiltração</div>
        <div class="legenda-item"><div class="legenda-cor" style="background:#8b5cf6;"></div> Cargas Internas</div>
    </div>
</div>

<!-- ============================================================ -->
<!-- DISTRIBUIÇÃO SENSÍVEL/LATENTE -->
<!-- ============================================================ -->
<div class="form-card">
    <h2>📈 Distribuição Sensível / Latente</h2>
    <div class="card-grid">
        <div class="card" style="border-left:4px solid #3b82f6;">
            <div class="card-label" style="color:#1e40af;">Q SENSÍVEL</div>
            <div class="card-valor" style="color:#1e40af;"><?= number_format($q_sensivel, 0, ',', '.') ?> W</div>
            <div class="card-sub"><?= number_format($q_sensivel / max($q_bruta, 1) * 100, 1, ',', '.') ?>% do total</div>
        </div>
        <div class="card" style="border-left:4px solid #06b6d4;">
            <div class="card-label" style="color:#0e7490;">Q LATENTE</div>
            <div class="card-valor" style="color:#0e7490;"><?= number_format($q_latente, 0, ',', '.') ?> W</div>
            <div class="card-sub"><?= number_format($q_latente / max($q_bruta, 1) * 100, 1, ',', '.') ?>% do total</div>
        </div>
        <div class="card" style="border-left:4px solid <?= $shr_status['cor'] ?>; background:<?= $shr_status['bg'] ?>;">
            <div class="card-label" style="color:<?= $shr_status['cor'] ?>;">SHR</div>
            <div class="card-valor" style="color:<?= $shr_status['cor'] ?>;"><?= number_format($shr, 2, ',', '.') ?></div>
            <div class="card-sub"><?= $shr_status['texto'] ?></div>
        </div>
        <div class="card" style="border-left:4px solid #0ea5e9;">
            <div class="card-label" style="color:#0369a1;">VAZÃO DE AR</div>
            <div class="card-valor" style="color:#0369a1;"><?= number_format($vazao, 0, ',', '.') ?> m³/h</div>
            <div class="card-sub">ΔT_ar = 12°C</div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- DIMENSIONAMENTO FINAL -->
<!-- ============================================================ -->
<div class="destaque">
    <h3>❄️ CAPACIDADE RECOMENDADA</h3>
    <div class="valor"><?= number_format($btu, 0, ',', '.') ?> BTU/h</div>
    <div class="sub">
        <?= number_format($btu / 12000, 2, ',', '.') ?> TR ·
        <?= number_format($q_total, 0, ',', '.') ?> W (com margem +10%)
    </div>
</div>

<div class="form-card">
    <h2>🎯 Equipamento Recomendado</h2>
    <div style="display:flex; align-items:center; gap:20px; padding:24px; background:linear-gradient(135deg, #ecfdf5, #d1fae5); border-radius:12px; border-left:5px solid #10b981;">
        <div style="font-size:3rem;">❄️</div>
        <div>
            <div style="font-size:1.5rem; font-weight:800; color:#065f46;">
                <?= htmlspecialchars($equip) ?>
            </div>
            <div style="font-size:0.9rem; color:#047857; margin-top:6px;">
                Para atender uma carga total de <?= number_format($btu, 0, ',', '.') ?> BTU/h
            </div>
            <div style="font-size:0.85rem; color:#059669; margin-top:4px;">
                SHR de projeto: <?= number_format($shr, 2, ',', '.') ?> (<?= $shr_status['texto'] ?>)
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- OUTROS AMBIENTES -->
<!-- ============================================================ -->
<?php if (!empty($outros)): ?>
<div class="form-card">
    <h2>🏠 Outros Ambientes do Projeto</h2>
    <div class="ambientes-lista">
        <?php foreach ($outros as $o): ?>
        <a class="ambiente-card" href="ambiente_resultado.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $o['id'] ?>">
            <strong><?= htmlspecialchars($o['nome']) ?></strong>
            <?php if ($o['carga_total_btu']): ?>
            <small><?= number_format((float)$o['carga_total_btu'], 0, ',', '.') ?> BTU/h</small><br>
            <small style="color:#059669;"><?= htmlspecialchars($o['aparelho_recomendado'] ?? '—') ?></small>
            <?php else: ?>
            <small style="color:#f59e0b;">⚠️ Sem cálculo</small>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- BOTÕES -->
<!-- ============================================================ -->
<div class="botoes">
    <a href="ambiente_dimensionamento.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">
        ← Voltar ao Passo 5
    </a>
    <div style="display:flex; gap:12px; margin-left:auto; flex-wrap:wrap;">
        <button type="button" onclick="window.print()" class="btn btn-print">
            🖨️ Imprimir / PDF
        </button>
        <a href="residencial_ambiente.php?projeto_id=<?= $projeto_id ?>" class="btn btn-finalizar">
            ✓ Finalizar Projeto
        </a>
    </div>
</div>

<div class="rodape">
    Memorial gerado automaticamente pelo sistema de cálculo de carga térmica<br>
    Baseado em ASHRAE Fundamentals · NBR 16401 · Fórmulas psicrométricas
</div>

</body>
</html>
