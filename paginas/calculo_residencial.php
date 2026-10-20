<?php
/**
 * Item 1-4 — Ar Condicionado Residencial
 * Base: Wilbert & Stoecker | NBR 15575
 * Método prático: área × altura × fatores
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    # Dimensões
    $area = $d["larg"] * $d["comp"];
    $volume = $area * $d["alt"];
    
    # Fator de orientação solar
    $fatores_sol = [
        "norte" => 1.25,
        "leste" => 1.15,
        "sul"   => 1.00,
        "oeste" => 1.35
    ];
    $f_sol = $fatores_sol[$d["orientacao"]];
    
    # Ganho base: 100 kcal/h por m² + ocupação + renovação
    $ganho_base = $area * 100;
    $ganho_pessoas = $d["pessoas"] * 120; # kcal/h por pessoa
    $ganho_ventil = $volume * 30;        # renovação natural
    $ganho_aparelhos = $d["pot_aparelhos"] * 0.86; # W → kcal/h
    
    $total_kcal = ($ganho_base + $ganho_pessoas + $ganho_ventil + $ganho_aparelhos) * $f_sol;
    $total_w = $total_kcal * 1.163;      # kcal/h → W
    $btu_h = $total_kcal * 3.968;         # kcal/h → BTU/h
    $tr = $btu_h / 12000;                 # BTU → TR
    
    $msg = "<div class=resultado><h3>✅ Resultado — Cálculo Residencial</h3><table>
<tr><td>Área do ambiente:</td><td><strong>".number_format($area,1,",",".")." m²</strong></td></tr>
<tr><td>Volume:</td><td>".number_format($volume,1,",",".")." m³</td></tr>
<tr><td>Orientação predominante:</td><td>".ucfirst($d["orientacao"])." (fator ×$f_sol)</td></tr>
<tr><td>Ganho ambiental:</td><td>".number_format($ganho_base,0,",",".")." kcal/h</td></tr>
<tr><td>Ocupação ({$d["pessoas"]} pessoas):</td><td>".number_format($ganho_pessoas,0,",",".")." kcal/h</td></tr>
<tr><td>Renovação de ar:</td><td>".number_format($ganho_ventil,0,",",".")." kcal/h</td></tr>
<tr><td>Aparelhos/iluminação:</td><td>".number_format($ganho_aparelhos,0,",",".")." kcal/h</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL:</strong></td><td><strong>".number_format($total_kcal,0,",",".")." kcal/h</strong></td></tr>
<tr><td>Potência térmica:</td><td>".number_format($total_w,0,",",".")." W</td></tr>
<tr><td>Capacidade recomendada:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
💡 Recomendação: arredonde para o modelo comercial imediatamente superior. 
Para orientação Oeste e ambientes com vidro grande, considere +10% adicional.
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-4 — Ar Condicionado Residencial</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🏠 Item 1-4 — Ar Condicionado Residencial</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">NBR 15575 — Método prático para casas, apartamentos e ambientes domésticos</p>
<form method="POST">
<h2>📐 Dimensões do Ambiente</h2>
<div class="grupo"><label>Comprimento (m):</label><input type=number step=0.01 name=comp required></div>
<div class="grupo"><label>Largura (m):</label><input type=number step=0.01 name=larg required></div>
<div class="grupo"><label>Altura pé-direito (m):</label><input type=number step=0.01 name=alt value=2.80 required></div>
<h2>☀️ Orientação Predominante</h2>
<div class="grupo"><label>Face principal para:</label><select name=orientacao>
<option value=norte>Norte (fator ×1,25)</option>
<option value=leste>Leste / Nascente (×1,15)</option>
<option value=sul>Sul (×1,00 — mais favorável)</option>
<option value=oeste>Oeste / Poente (×1,35 — maior incidência)</option>
</select></div>
<h2>👤 Ocupação e Cargas</h2>
<div class="grupo"><label>Número de pessoas:</label><input type=number name=pessoas value=1 required></div>
<div class="grupo"><label>Aparelhos + iluminação (W):</label><input type=number name=pot_aparelhos value=0><small class="dica">TV, geladeira, luz, etc.</small></div>
<button type="submit">📊 Calcular Carga Residencial</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=4" class=voltar>← Voltar para ficha</a>
</div></body></html>
