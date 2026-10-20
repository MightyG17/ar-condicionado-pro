<?php
/**
 * Item 1-5 — Ar Condicionado em Veículos
 * Base: Wilbert & Stoecker | NBR 15100
 * Carga solar intensa, espaço confinado, superfícies envidraçadas
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    # Dimensões internas
    $volume = $d["comp"] * $d["larg"] * $d["alt"];
    
    # Área envidraçada e fator de radiação
    $fator_sol = [
        "parado_sol" => 1.80,
        "parado_sombra" => 1.10,
        "em_movimento" => 1.00
    ];
    $f_sol = $fator_sol[$d["regime"]];
    
    # Ganho por superfície vidro (radiação direta)
    $ganho_vidro = $d["area_vidro"] * 650; # W/m² pico insolação
    $ganho_carcaca = $d["area_carcaca"] * 80; # condução térmica
    $ganho_ar = $volume * 40; # renovação/infiltração
    $ganho_ocupantes = $d["pessoas"] * 145; # W por pessoa em veículo
    $ganho_motor = $d["motor_ligado"] ? 300 : 0; # calor do motor/painel
    
    $total_w = ($ganho_vidro + $ganho_carcaca + $ganho_ar + $ganho_ocupantes + $ganho_motor) * $f_sol;
    $btu_h = $total_w * 3.412;
    $kcal_h = $total_w * 0.86;
    $tr = $btu_h / 12000;
    
    $regime_texto = [
        "parado_sol" => "Parado sob sol direto",
        "parado_sombra" => "Parado na sombra",
        "em_movimento" => "Em movimento (ventilação natural)"
    ];
    
    $msg = "<div class=resultado><h3>✅ Resultado — Cálculo Veicular</h3><table>
<tr><td>Volume do habitáculo:</td><td><strong>".number_format($volume,2,",",".")." m³</strong></td></tr>
<tr><td>Regime de operação:</td><td>".$regime_texto[$d["regime"]]." (fator ×$f_sol)</td></tr>
<tr><td>Superfície envidraçada:</td><td>".number_format($d["area_vidro"],1,",",".")." m² → ".number_format($ganho_vidro,0,",",".")." W (radiação solar)</td></tr>
<tr><td>Superfície da carcaça:</td><td>".number_format($d["area_carcaca"],1,",",".")." m² → ".number_format($ganho_carcaca,0,",",".")." W (condução)</td></tr>
<tr><td>Ar/infiltração:</td><td>".number_format($ganho_ar,0,",",".")." W</td></tr>
<tr><td>Ocupação ({$d["pessoas"]} pessoas):</td><td>".number_format($ganho_ocupantes,0,",",".")." W</td></tr>
<tr><td>Calor do compartimento motor:</td><td>".number_format($ganho_motor,0,",",".")." W</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL:</strong></td><td><strong>".number_format($total_w,0,",",".")." W</strong></td></tr>
<tr><td>Equivalente:</td><td>".number_format($kcal_h,0,",",".")." kcal/h</td></tr>
<tr><td>Capacidade do sistema:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
💡 Nota: Veículos parados sob sol direto podem atingir 60–70°C internos. 
O valor calculado representa a carga necessária para reduzir a temperatura até o confortável (22–24°C).
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-5 — Ar Condicionado em Veículos</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🚗 Item 1-5 — Ar Condicionado em Veículos</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">NBR 15100 — Carga solar, espaço confinado, superfícies envidraçadas e ganho térmico dinâmico</p>
<form method="POST">
<h2>📐 Dimensões do Habitáculo</h2>
<div class="grupo"><label>Comprimento interno (m):</label><input type=number step=0.01 name=comp value=2.50 required></div>
<div class="grupo"><label>Largura interna (m):</label><input type=number step=0.01 name=larg value=1.50 required></div>
<div class="grupo"><label>Altura interna (m):</label><input type=number step=0.01 name=alt value=1.20 required></div>
<h2>☀️ Exposição e Superfícies</h2>
<div class="grupo"><label>Regime predominante:</label><select name=regime>
<option value=parado_sol>Parado sob sol direto (fator ×1,80 — condição crítica)</option>
<option value=parado_sombra>Parado na sombra (×1,10)</option>
<option value=em_movimento>Em movimento (×1,00 — ventilação natural)</option>
</select></div>
<div class="grupo"><label>Área total de vidro (m²):</label><input type=number step=0.01 name=area_vidro value=2.80 required></div>
<div class="grupo"><label>Área externa da carcaça exposta (m²):</label><input type=number step=0.01 name=area_carcaca value=6.50 required></div>
<h2>👤 Ocupação e Fatores Adicionais</h2>
<div class="grupo"><label>Número de ocupantes:</label><input type=number name=pessoas value=1 required></div>
<div class="grupo"><label>Calor do compartimento motor:</label><select name=motor_ligado>
<option value=1>Motor ligado / trânsito parado (+300 W)</option>
<option value=0>Motor desligado / ventilação reduzida</option>
</select></div>
<button type="submit">📊 Calcular Carga Veicular</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=5" class=voltar>← Voltar para ficha</a>
</div></body></html>
