<?php
/**
 * Item 1-6 — Armazenamento e Distribuição de Alimentos
 * Base: Wilbert & Stoecker | NBR 15097 / ANVISA RDC 216
 * Carga térmica: transmissão, produtos, renovação, respiração, perdas
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    # Dimensões e área das paredes
    $L = $d["comp"]; $l = $d["larg"]; $h = $d["alt"];
    $area_paredes = 2 * ($L*$h + $l*$h);
    $area_teto = $L * $l;
    $area_piso = $L * $l;
    
    # Gradiente térmico
    $t_ext = 30; # temperatura ambiente externa padrão
    $delta_T = $t_ext - $d["t_interna"];
    
    # Transmissão pelo isolamento (coeficiente U simplificado por material)
    $U = [
        "poliuretano_100mm" => 0.22,
        "poliuretano_150mm" => 0.16,
        "poliuretano_200mm" => 0.12,
        "lana_vidro_150mm"  => 0.26
    ];
    $coef_U = $U[$d["isolamento"]];
    
    $ganho_transmissao = ($area_paredes + $area_teto + $area_piso) * $coef_U * $delta_T;
    
    # Resfriamento do produto
    $calor_especifico = $d["t_interna"] <= -18 ? 1.8 : 3.5; # kJ/kg·°C (congelado / resfriado)
    $ganho_produto = $d["carga_kg"] * $calor_especifico * ($t_ext - $d["t_entrada"]) / 3.6 / 24; # kJ → W/dia
    
    # Calor de respiração (produtos vivos — só acima de 0°C)
    $respiracao = 0;
    if ($d["t_interna"] > 0) {
        $resp_valores = [
            0 => 10,   # 0°C
            5 => 35,   # 5°C
            10 => 75,  # 10°C
            15 => 150, # 15°C
            20 => 250  # 20°C
        ];
        $t = $d["t_interna"];
        $mais_prox = 0;
        foreach ([0,5,10,15,20] as $ref) {
            if (abs($t - $ref) < abs($t - $mais_prox)) $mais_prox = $ref;
        }
        $respiracao = $d["carga_kg"] * $resp_valores[$mais_prox] / 3600; # mW/kg → W
    }
    
    # Renovação de ar / infiltração
    $volume_camara = $L * $l * $h;
    $ganho_renovacao = $volume_camara * 5; # W/m³ taxa padrão
    
    # Perdas por abertura de portas
    $perda_portas = $d["portas_abertura"] * 150; # W por abertura/hora
    
    $total_w = $ganho_transmissao + $ganho_produto + $respiracao + $ganho_renovacao + $perda_portas;
    $kcal_h = $total_w * 0.86;
    $btu_h = $total_w * 3.412;
    $tr = $btu_h / 12000;
    
    $tipo_armaz = $d["t_interna"] <= -18 ? "Congelamento" : ($d["t_interna"] <= 4 ? "Resfriamento" : "Refrigeração geral");
    
    $msg = "<div class=resultado><h3>✅ Resultado — Câmara Fria</h3><table>
<tr><td>Dimensões:</td><td>{$L} m × {$l} m × {$h} h → {$volume_camara} m³</td></tr>
<tr><td>Temperatura interna:</td><td>{$d["t_interna"]}°C — <strong>{$tipo_armaz}</strong></td></tr>
<tr><td>Isolamento térmico:</td><td>{$d["isolamento"]} (U = {$coef_U} W/m²·°C)</td></tr>
<tr><td>Ganho por transmissão:</td><td>".number_format($ganho_transmissao,0,",",".")." W</td></tr>
<tr><td>Resfriamento do produto:</td><td>".number_format($ganho_produto,0,",",".")." W</td></tr>
<tr><td>Calor de respiração:</td><td>".number_format($respiracao,0,",",".")." W</td></tr>
<tr><td>Renovação/infiltração:</td><td>".number_format($ganho_renovacao,0,",",".")." W</td></tr>
<tr><td>Perdas por portas:</td><td>".number_format($perda_portas,0,",",".")." W</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL:</strong></td><td><strong>".number_format($total_w,0,",",".")." W</strong></td></tr>
<tr><td>Equivalente:</td><td>".number_format($kcal_h,0,",",".")." kcal/h</td></tr>
<tr><td>Capacidade do sistema:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
💡 Recomendação: adicione <strong>15–20% de margem de segurança</strong> para picos de carga e envelhecimento do equipamento.
Para temperaturas abaixo de -18°C, considere degelo periódico e dissipação de calor adicional.
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-6 — Armazenamento de Alimentos</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🧊 Item 1-6 — Armazenamento e Distribuição de Alimentos</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">NBR 15097 / ANVISA RDC 216 — Câmaras frias, resfriamento e congelamento</p>
<form method="POST">
<h2>📐 Dimensões e Temperatura</h2>
<div class="grupo"><label>Comprimento interno (m):</label><input type=number step=0.01 name=comp value=4.00 required></div>
<div class="grupo"><label>Largura interna (m):</label><input type=number step=0.01 name=larg value=3.00 required></div>
<div class="grupo"><label>Altura interna (m):</label><input type=number step=0.01 name=alt value=2.50 required></div>
<div class="grupo"><label>Temperatura de conservação (°C):</label><input type=number step=1 name=t_interna value=-18 required></div>
<div class="grupo"><label>Temperatura de entrada do produto (°C):</label><input type=number step=1 name=t_entrada value=5 required></div>
<h2>🧱 Isolamento Térmico</h2>
<div class="grupo"><label>Material/Espessura:</label><select name=isolamento>
<option value=poliuretano_100mm>Poliuretano expandido — 100 mm (U=0,22)</option>
<option value=poliuretano_150mm selected>Poliuretano expandido — 150 mm (U=0,16)</option>
<option value=poliuretano_200mm>Poliuretano expandido — 200 mm (U=0,12)</option>
<option value=lana_vidro_150mm>Lã de vidro — 150 mm (U=0,26)</option>
</select></div>
<h2>📦 Produto e Operação</h2>
<div class="grupo"><label>Carga média estocada (kg):</label><input type=number name=carga_kg value=2000 required></div>
<div class="grupo"><label>Abertura de portas (vezes/hora):</label><input type=number step=0.5 name=portas_abertura value=2 required></div>
<button type="submit">📊 Calcular Câmara Fria</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=6" class=voltar>← Voltar para ficha</a>
</div></body></html>
