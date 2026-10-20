<?php include __DIR__ . "/../config/config.php";
$sql = "SELECT * FROM aplicacoes ORDER BY ordem_exibicao ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ar Condicionado Pro — Wilbert & Stoecker</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>📘 Refrigeração e Ar Condicionado</h1>
<h2>Baseado em Wilbert & Stoecker — Atualizado com NBR</h2>
<?php while ($row = $result->fetch_assoc()): ?>
<a href="aplicacao.php?id=<?=$row["id"]?>">
<div class="aplicacao">
<span class="codigo"><?=$row["codigo"]?></span>
<span class="nome"><?=$row["nome"]?></span>
<div class="normas">📌 Normas: <?=$row["normas_referencia"]?></div>
<div class="faixas">🌡️ Temp: <?=$row["temp_interna_min"]?> a <?=$row["temp_interna_max"]?>°C | 💧 Umidade: <?=$row["umid_interna_min"]?> a <?=$row["umid_interna_max"]?>%</div>
</div></a>
<?php endwhile; $conn->close(); ?>
</div></body></html>
