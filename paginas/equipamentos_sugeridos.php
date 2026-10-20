<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);

if (!$projeto_id || !$ambiente_id) {
    header("Location: residencial_projetos.php");
    exit;
}

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
$amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();
if (!$proj || !$amb) {
    header("Location: residencial_ambiente.php?projeto_id=$projeto_id");
    exit;
}

function extrai_preco_min($faixa) {
    if (empty($faixa)) return 0;
    $faixa = str_replace(['–', '—', ' a ', ' até '], '|', $faixa);
    $faixa = preg_replace('/[R$\s\.]/', '', $faixa);
    $partes = explode('|', $faixa);
    $primeiro = $partes[0] ?? '0';
    $primeiro = str_replace(',', '.', $primeiro);
    return (float)$primeiro;
}

$pico_horario = $conn->query("
    SELECT mes, hora, carga_total_w, temp_externa_c
    FROM carga_horaria
    WHERE ambiente_id = $ambiente_id
    ORDER BY carga_total_w DESC LIMIT 1
")->fetch_assoc();

$res_fixo = $conn->query("
    SELECT carga_total_w, carga_total_btu, aparelho_recomendado
    FROM resultados_calculo
    WHERE ambiente_id = $ambiente_id
    ORDER BY id DESC LIMIT 1
")->fetch_assoc();

$carga_w = 0; $carga_btu = 0; $fonte = '';
if ($pico_horario) {
    $carga_w = (float)$pico_horario['carga_total_w'];
    $carga_btu = $carga_w * 3.412;
    $fonte = "Pico horário (Etapa A) — {$pico_horario['mes']}/{$pico_horario['hora']}h";
} elseif ($res_fixo) {
    $carga_w = (float)$res_fixo['carga_total_w'];
    $carga_btu = (float)$res_fixo['carga_total_btu'];
    $fonte = "Resultado fixo (Etapa 5)";
}

$btu_necessario = ceil($carga_btu * 1.10);

$equips_raw = $conn->query("
    SELECT * FROM equipamentos
    WHERE ativo = 1 AND capacidade_btu >= $btu_necessario
    ORDER BY capacidade_btu ASC, seer DESC
")->fetch_all(MYSQLI_ASSOC);

$equips = [];
foreach ($equips_raw as $e) {
    $e['_preco_min'] = extrai_preco_min($e['faixa_preco'] ?? '');
    $equips[] = $e;
}

$economico = null; $recomendado = null; $premium = null;

if (!empty($equips)) {
    $candidatos = array_filter($equips, fn($e) => $e['_preco_min'] > 0);
    if (empty($candidatos)) $candidatos = $equips;
    $economico = array_reduce($candidatos, function($c, $i) {
        return (!$c || $i['_preco_min'] < $c['_preco_min']) ? $i : $c;
    });
    
    $premium = array_reduce($equips, function($c, $i) {
        return (!$c || (float)$i['seer'] > (float)$c['seer']) ? $i : $c;
    });
    
    $por_preco = $equips;
    usort($por_preco, fn($a, $b) => $a['_preco_min'] <=> $b['_preco_min']);
    $top5 = array_slice($por_preco, 0, min(5, count($por_preco)));
    $recomendado = array_reduce($top5, function($c, $i) {
        return (!$c || (float)$i['seer'] > (float)$c['seer']) ? $i : $c;
    });
    
    if ($recomendado && $economico && $recomendado['id'] == $economico['id']) {
        $alt = array_filter($top5, fn($e) => $e['id'] != $economico['id']);
        if (!empty($alt)) {
            $recomendado = array_reduce($alt, function($c, $i) {
                return (!$c || (float)$i['seer'] > (float)$c['seer']) ? $i : $c;
            });
        }
    }
    if ($premium && $recomendado && $premium['id'] == $recomendado['id']) {
        $alt = array_filter($equips, fn($e) => $e['id'] != $recomendado['id']);
        if (!empty($alt)) {
            $premium = array_reduce($alt, function($c, $i) {
                return (!$c || (float)$i['seer'] > (float)$c['seer']) ? $i : $c;
            });
        }
    }
    
    usort($equips, fn($a, $b) => $a['_preco_min'] <=> $b['_preco_min']);
}

function render_card($tipo, $emoji, $titulo, $e) {
    if (!$e) return;
    ?>
    <div class="tipo-card <?= $tipo ?>">
        <div class="tipo-header"><?= $emoji ?> <?= $titulo ?></div>
        <div class="modelo"><?= htmlspecialchars($e['marca']) ?></div>
        <div style="color:#666; font-size:.9em;"><?= htmlspecialchars($e['modelo']) ?></div>
        <div class="spec"><span class="spec-label">Capacidade</span><span class="spec-valor"><?= number_format($e['capacidade_btu'], 0, ',', '.') ?> BTU/h</span></div>
        <div class="spec"><span class="spec-label">SEER</span><span class="spec-valor"><?= number_format((float)$e['seer'], 1, ',', '.') ?></span></div>
        <div class="spec"><span class="spec-label">Procel</span><span class="spec-valor"><?= $e['selo_procel'] ?></span></div>
        <div class="spec"><span class="spec-label">Ruído</span><span class="spec-valor"><?= $e['ruido_min'] ?>–<?= $e['ruido_max'] ?> dB</span></div>
        <div class="spec"><span class="spec-label">Consumo</span><span class="spec-valor"><?= $e['consumo_kwh_mes'] ?? '—' ?> kWh/mês</span></div>
        <div class="preco"><?= htmlspecialchars($e['faixa_preco']) ?></div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Equipamentos Sugeridos — <?= htmlspecialchars($amb['nome']) ?></title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 24px; margin: 0 auto 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 1200px; }
h1, h2 { color: #1a3a5c; margin-top: 0; }
.voltar { display: inline-block; margin-bottom: 16px; color: #3498db; text-decoration: none; font-weight: 500; }
.voltar:hover { text-decoration: underline; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 20px 0; }
.tipo-card { padding: 20px; border-radius: 12px; border: 2px solid #eee; background: #fff; }
.tipo-card.economico { border-color: #27ae60; background: #eafaf1; }
.tipo-card.recomendado { border-color: #3498db; background: #e8f4fd; }
.tipo-card.premium { border-color: #f39c12; background: #fff8e1; }
.tipo-header { font-size: 1.1em; font-weight: bold; margin-bottom: 12px; }
.tipo-card.economico .tipo-header { color: #27ae60; }
.tipo-card.recomendado .tipo-header { color: #3498db; }
.tipo-card.premium .tipo-header { color: #f39c12; }
.modelo { font-size: 1.2em; font-weight: bold; color: #1a3a5c; margin: 8px 0; }
.spec { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,.06); font-size: .9em; }
.spec-label { color: #666; }
.spec-valor { font-weight: 600; color: #1a3a5c; }
.preco { font-size: 1.3em; font-weight: bold; color: #27ae60; margin-top: 12px; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: .9em; }
th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #1a3a5c; color: #fff; font-weight: 500; }
tr:hover { background: #f8fafc; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.info-box { background: #e8f4fd; border-left: 4px solid #3498db; padding: 14px 18px; border-radius: 8px; margin: 16px 0; }
.info-box strong { color: #1a3a5c; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php $nav_ambiente_id = (int)($ambiente_id ?? ($_GET['ambiente_id'] ?? ($_GET['id'] ?? 0))); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<a href="projeto_dashboard.php?projeto_id=<?= $projeto_id ?>" class="voltar">← Voltar para Dashboard</a>

<div class="card">
    <h1>❄️ Equipamentos Sugeridos — <?= htmlspecialchars($amb['nome']) ?></h1>
    <p style="color:#666;">
        Projeto: <strong><?= htmlspecialchars($proj['nome']) ?></strong> ·
        Área: <strong><?= number_format($amb['comprimento_m'] * $amb['largura_m'], 2, ',', '.') ?> m²</strong>
    </p>
    <div class="info-box">
        <strong>📊 Carga calculada:</strong> <?= number_format($carga_w, 0, ',', '.') ?> W 
        = <?= number_format($carga_btu, 0, ',', '.') ?> BTU/h<br>
        <strong>📌 Fonte:</strong> <?= htmlspecialchars($fonte) ?><br>
        <strong>🎯 Capacidade necessária (com 10% de margem):</strong> 
        <span style="font-size:1.3em; color:#c0392b; font-weight:bold;">
            <?= number_format($btu_necessario, 0, ',', '.') ?> BTU/h
        </span>
    </div>
</div>
<?php if (!empty($equips)): ?>
<div class="card">
    <h2>🏆 Sugestões</h2>
    <div class="grid">
        <?php render_card('economico', '💰', 'ECONÔMICO', $economico); ?>
        <?php render_card('recomendado', '🥇', 'RECOMENDADO', $recomendado); ?>
        <?php render_card('premium', '🏆', 'PREMIUM', $premium); ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>📋 Todos os Modelos Compatíveis (<?= count($equips) ?>)</h2>
    <?php if (empty($equips)): ?>
        <p style="color:#999;">Nenhum equipamento compatível encontrado.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Marca</th><th>Modelo</th>
                <th class="num">BTU/h</th>
                <th class="num">SEER</th>
                <th>Procel</th>
                <th class="num">Ruído</th>
                <th class="num">kWh/mês</th>
                <th>Faixa de Preço</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($equips as $e): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['marca']) ?></strong></td>
                <td><?= htmlspecialchars($e['modelo']) ?></td>
                <td class="num"><?= number_format($e['capacidade_btu'], 0, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$e['seer'], 1, ',', '.') ?></td>
                <td><?= $e['selo_procel'] ?></td>
                <td class="num"><?= $e['ruido_min'] ?>–<?= $e['ruido_max'] ?></td>
                <td class="num"><?= $e['consumo_kwh_mes'] ?? '—' ?></td>
                <td><?= htmlspecialchars($e['faixa_preco']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
