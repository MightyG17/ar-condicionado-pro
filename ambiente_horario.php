<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/calculo_horario.php';

$ambiente_id = intval($_GET['id'] ?? 0);
$msg = '';

if (isset($_POST['acao']) && $_POST['acao'] === 'calcular' && $ambiente_id > 0) {
    $r = calcular_perfil_horario($conn, $ambiente_id);
    if (!empty($r['sucesso'])) {
        $msg = "✅ Pico: " . number_format($r['pico']['carga'], 0, ',', '.') 
             . " W (" . number_format($r['pico']['carga'] * 3.412, 0, ',', '.') . " BTU/h) em {$r['pico']['mes']}/{$r['pico']['hora']}h · "
             . "Área: " . number_format($r['area'], 2, ',', '.') . " m² · "
             . "Janelas: {$r['janelas']} · Paredes: {$r['paredes']}";
    } else {
        $msg = "❌ Erro: " . ($r['erro'] ?? 'desconhecido');
    }
}

$amb = null;
if ($ambiente_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.*, p.nome AS projeto_nome, p.cidade, p.uf 
        FROM ambientes a 
        JOIN projetos p ON p.id = a.projeto_id 
        WHERE a.id = ?
    ");
    $stmt->bind_param('i', $ambiente_id);
    $stmt->execute();
    $amb = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$amb) {
    die("<h1>Ambiente não encontrado</h1><p>Use ?id=N na URL.</p>");
}

$dados = [];
$r = $conn->query("
    SELECT mes, hora, carga_total_w, carga_solar_w, carga_interna_w, carga_conducao_w,
           irr_norte, irr_sul, irr_leste, irr_oeste, temp_externa_c
    FROM carga_horaria 
    WHERE ambiente_id = $ambiente_id 
    ORDER BY mes, hora
");
if ($r) { while ($row = $r->fetch_assoc()) $dados[] = $row; }

$pico = null;
foreach ($dados as $d) {
    if (!$pico || $d['carga_total_w'] > $pico['carga_total_w']) $pico = $d;
}

// Área
$area_piso = floatval($amb['comprimento_m']) * floatval($amb['largura_m']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Análise Horária — <?= htmlspecialchars($amb['nome']) ?></title>
<style>
body { font-family: system-ui, sans-serif; background: #f5f7fa; padding: 20px; margin: 0; }
.card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 1200px; margin-left: auto; margin-right: auto; }
h1 { color: #1a3a5c; margin-top: 0; }
.pico { font-size: 2em; color: #c0392b; font-weight: bold; margin: 8px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; }
th, td { padding: 8px; text-align: center; border-bottom: 1px solid #eee; font-size: .85em; }
th { background: #1a3a5c; color: #fff; }
tr:hover { background: #f0f4f8; }
.btn { background: #27ae60; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1em; cursor: pointer; font-weight: bold; }
.btn:hover { background: #219150; }
.grafico { width: 100%; height: 240px; display: block; }
.msg { padding: 12px; background: #d4edda; border-radius: 8px; margin: 16px 0; border-left: 4px solid #27ae60; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 16px 0; }
.stat { background: #f0f4f8; padding: 12px; border-radius: 8px; }
.stat-label { font-size: .8em; color: #666; text-transform: uppercase; }
.stat-value { font-size: 1.4em; color: #1a3a5c; font-weight: bold; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php $nav_ambiente_id = (int)($ambiente_id ?? ($_GET['ambiente_id'] ?? ($_GET['id'] ?? 0))); ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>


<div class="card">
    <h1>📅 Análise Horária — <?= htmlspecialchars($amb['nome']) ?></h1>
    <p style="color:#666;">
        Projeto: <strong><?= htmlspecialchars($amb['projeto_nome']) ?></strong> · 
        <?= htmlspecialchars($amb['cidade'] ?? '') ?>/<?= htmlspecialchars($amb['uf'] ?? '') ?> · 
        Área: <strong><?= number_format($area_piso, 2, ',', '.') ?> m²</strong>
    </p>
    
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="acao" value="calcular">
        <button type="submit" class="btn">🔄 Calcular Perfil Anual (156 pontos)</button>
    </form>
</div>

<?php if ($pico): ?>
<div class="card">
    <h2>📊 Resultado</h2>
    <div class="grid">
        <div class="stat">
            <div class="stat-label">Pico de Carga</div>
            <div class="stat-value"><?= number_format($pico['carga_total_w'], 0, ',', '.') ?> W</div>
        </div>
        <div class="stat">
            <div class="stat-label">Em BTU/h</div>
            <div class="stat-value"><?= number_format($pico['carga_total_w'] * 3.412, 0, ',', '.') ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Data/Hora do Pico</div>
            <div class="stat-value"><?= $pico['mes'] ?>/<?= $pico['hora'] ?>h</div>
        </div>
        <div class="stat">
            <div class="stat-label">Temp. Externa</div>
            <div class="stat-value"><?= $pico['temp_externa_c'] ?> °C</div>
        </div>
    </div>
</div>

<div class="card">
    <h2>📈 Curva de Carga — Janeiro · Julho · Dezembro</h2>
    <svg class="grafico" viewBox="0 0 600 240" preserveAspectRatio="none">
        <?php 
        $cores = [1 => '#3498db', 7 => '#e74c3c', 12 => '#f39c12'];
        $max_carga = 0;
        foreach ($dados as $d) if ($d['carga_total_w'] > $max_carga) $max_carga = $d['carga_total_w'];
        $max_carga = $max_carga ?: 1;
        
        // Linhas de grade
        for ($i = 0; $i <= 4; $i++):
            $y = 20 + ($i * 50);
            $val = round($max_carga * (1 - $i/4));
        ?>
        <line x1="40" y1="<?= $y ?>" x2="590" y2="<?= $y ?>" stroke="#eee" stroke-width="1"/>
        <text x="5" y="<?= $y + 4 ?>" font-size="9" fill="#999"><?= number_format($val, 0, ',', '.') ?></text>
        <?php endfor; ?>
        
        <?php foreach ($cores as $m => $cor):
            $path = ''; $i = 0;
            foreach ($dados as $d) {
                if ($d['mes'] != $m) continue;
                $x = 40 + ($d['hora'] - 6) * 42;
                $y = 220 - ($d['carga_total_w'] / $max_carga) * 200;
                $path .= ($i === 0 ? 'M' : 'L') . "$x,$y ";
                $i++;
            }
        ?>
        <path d="<?= $path ?>" stroke="<?= $cor ?>" stroke-width="2.5" fill="none"/>
        <?php endforeach; ?>
        
        <?php for ($h = 6; $h <= 18; $h += 2): ?>
            <text x="<?= 40 + ($h-6)*42 ?>" y="238" font-size="10" text-anchor="middle" fill="#666"><?= $h ?>h</text>
        <?php endfor; ?>
    </svg>
    <p style="font-size:.85em;color:#666;text-align:center;">🔵 Janeiro · 🔴 Julho · 🟡 Dezembro</p>
</div>

<div class="card">
    <h2>📋 Tabela Completa (<?= count($dados) ?> registros)</h2>
    <div style="max-height:500px;overflow:auto;">
    <table>
        <thead>
            <tr>
                <th>Mês</th><th>Hora</th><th>Carga Total (W)</th>
                <th>Solar</th><th>Interna</th><th>Condução</th>
                <th>N</th><th>S</th><th>L</th><th>O</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dados as $d): ?>
            <tr>
                <td><?= $d['mes'] ?></td>
                <td><?= $d['hora'] ?>h</td>
                <td><strong><?= number_format($d['carga_total_w'], 0, ',', '.') ?></strong></td>
                <td><?= number_format($d['carga_solar_w'], 0, ',', '.') ?></td>
                <td><?= number_format($d['carga_interna_w'], 0, ',', '.') ?></td>
                <td><?= number_format($d['carga_conducao_w'], 0, ',', '.') ?></td>
                <td><?= number_format($d['irr_norte'], 0) ?></td>
                <td><?= number_format($d['irr_sul'], 0) ?></td>
                <td><?= number_format($d['irr_leste'], 0) ?></td>
                <td><?= number_format($d['irr_oeste'], 0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p>Nenhum dado calculado ainda. Clique no botão acima para gerar o perfil horário.</p>
</div>
<?php endif; ?>

</body>
</html>
