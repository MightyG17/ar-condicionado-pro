<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$cidades = $conn->query("
    SELECT * FROM irradiacao_cresesb 
    WHERE ativo = 1 
    ORDER BY uf, cidade
")->fetch_all(MYSQLI_ASSOC);

$uf_sel = $_GET['uf'] ?? '';
if ($uf_sel) {
    $cidades = array_filter($cidades, fn($c) => $c['uf'] === $uf_sel);
    $cidades = array_values($cidades);
}

$ufs = [];
foreach ($conn->query("SELECT DISTINCT uf FROM irradiacao_cresesb ORDER BY uf") as $row) {
    $ufs[] = $row['uf'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cidades CRESESB</title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 24px; margin: 0 auto 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 1400px; }
h1, h2 { color: #1a3a5c; margin-top: 0; }
.filtros { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0; }
.filtros a { padding: 6px 12px; border-radius: 6px; background: #eee; color: #333; text-decoration: none; font-size: .9em; }
.filtros a:hover, .filtros a.ativo { background: #1a3a5c; color: #fff; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: .9em; }
th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #1a3a5c; color: #fff; font-weight: 500; }
tr:hover { background: #f8fafc; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.info-box { background: #e8f4fd; border-left: 4px solid #3498db; padding: 14px 18px; border-radius: 8px; margin: 16px 0; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<div class="card">
    <h1>🌞 Cidades CRESESB</h1>
    <p>Base de irradiação solar com <strong><?= count($cidades) ?></strong> cidades (Atlas CRESESB/INPE).</p>
    
    <div class="info-box">
        <strong>ℹ️ Fonte:</strong> Centro de Referência para Energia Solar e Eólica Sérgio de Salvo Brito (CRESESB/INPE)<br>
        <strong>📊 Dados:</strong> Médias diárias anuais em kWh/m²/dia por orientação
    </div>
    
    <div class="filtros">
        <a href="cidades_cresesb.php" class="<?= !$uf_sel ? 'ativo' : '' ?>">Todas</a>
        <?php foreach ($ufs as $u): ?>
            <a href="cidades_cresesb.php?uf=<?= $u ?>" class="<?= $uf_sel === $u ? 'ativo' : '' ?>"><?= $u ?></a>
        <?php endforeach; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Cidade</th>
                <th>UF</th>
                <th class="num">Lat</th>
                <th class="num">Lon</th>
                <th class="num">Plano</th>
                <th class="num">Norte</th>
                <th class="num">Sul</th>
                <th class="num">Leste</th>
                <th class="num">Oeste</th>
                <th>Fonte</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cidades as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['cidade']) ?></strong></td>
                <td><?= $c['uf'] ?></td>
                <td class="num"><?= number_format((float)$c['latitude'], 4, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['longitude'], 4, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['plano_horizontal'], 2, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['norte'], 2, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['sul'], 2, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['leste'], 2, ',', '.') ?></td>
                <td class="num"><?= number_format((float)$c['oeste'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($c['fonte']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
