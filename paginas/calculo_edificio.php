<?php include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    $area_parede = ($d["comp"] * $d["alt"]) * 2 + ($d["larg"] * $d["alt"]) * 2;
    $area_piso = $d["comp"] * $d["larg"];
    $dT = $d["temp_ext"] - $d["temp_int"];
    $gp = $area_parede * 2.0 * $dT;
    $gt = $area_piso * 1.5 * $dT;
    $fs = ["Norte"=>80,"Sul"=>50,"Leste"=>180,"Oeste"=>220][$d["orientacao"]];
    $gj = $d["area_janela"] * $fs;
    $ps = $d["pessoas"] * 70; $pl = $d["pessoas"] * 45;
    $va = $d["pessoas"] * 25;
    $ga = ($va/3600)*1.2*1005*$dT/1000;
    $ts = $gp + $gt + $gj + $ps + $d["pot_ilum"] + $d["pot_eqp"] + $ga;
    $tl = $pl; $tw = $ts + $tl;
    $msg = "<div class=resultado><h3>✅ Resultados</h3><table>
<tr><td>Área:</td><td><strong>".number_format($area_piso,1,",",".")." m²</strong></td></tr>
<tr><td>Paredes:</td><td>".number_format($gp,0,",",".")." W</td></tr>
<tr><td>Teto:</td><td>".number_format($gt,0,",",".")." W</td></tr>
<tr><td>Janela ({$d["orientacao"]}):</td><td>".number_format($gj,0,",",".")." W</td></tr>
<tr><td>Pessoas sensível:</td><td>".number_format($ps,0,",",".")." W</td></tr>
<tr><td>Pessoas latente:</td><td>".number_format($pl,0,",",".")." W</td></tr>
<tr class=linha-total><td><strong>TOTAL:</strong></td><td><strong>".number_format($tw,0,",",".")." W</strong></td></tr>
<tr><td></td><td>= ".number_format($tw*3.412,0,",",".")." BTU/h</td></tr>
<tr><td></td><td>= ".number_format($tw/3517,2,",",".")." TR</td></tr>
</table></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-2 — Edifícios</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🏢 Item 1-2 — Edifícios de Porte Médio e Grande</h1>
<form method="POST">
<h2>📐 Dimensões</h2>
<div class="grupo"><label>Comprimento (m):</label><input type=number step=0.01 name=comp required></div>
<div class="grupo"><label>Largura (m):</label><input type=number step=0.01 name=larg required></div>
<div class="grupo"><label>Altura (m):</label><input type=number step=0.01 name=alt value=2.80 required></div>
<h2>🌡️ Temperaturas</h2>
<div class="grupo"><label>Interna (°C):</label><input type=number step=0.1 name=temp_int value=24 required></div>
<div class="grupo"><label>Externa (°C):</label><input type=number step=0.1 name=temp_ext value=32 required></div>
<h2>☀️ Janelas</h2>
<div class="grupo"><label>Orientação:</label><select name=orientacao>
<option value=Norte>Norte</option><option value=Sul>Sul</option>
<option value=Leste>Leste</option><option value=Oeste>Oeste</option>
</select></div>
<div class="grupo"><label>Área janelas (m²):</label><input type=number step=0.01 name=area_janela value=0 required></div>
<h2>👥 Ocupação</h2>
<div class="grupo"><label>Pessoas:</label><input type=number name=pessoas value=1 required></div>
<div class="grupo"><label>Iluminação (W):</label><input type=number name=pot_ilum value=0></div>
<div class="grupo"><label>Equipamentos (W):</label><input type=number name=pot_eqp value=0></div>
<button>📊 Calcular Carga Térmica</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=2" class=voltar>← Voltar</a>
</div></body></html>
