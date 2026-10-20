<?php
/**
 * Item 1-7 — Processamento de Alimentos
 * Base: Wilbert & Stoecker | ANVISA RDC 216 / NBR 15097
 * Carga térmica: equipamentos, processo, ocupação, renovação higiênica, perdas
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    # Área e volume
    $area = $d["comp"] * $d["larg"];
    $volume = $area * $d["alt"];
    
    # Ganho por equipamentos em operação
    $ganho_equip = $d["pot_equipamentos"] * $d["fator_demanda"] / 100;
    
    # Calor liberado pelo processo (cozimento, vapor, resfriamento)
    $ganho_processo = $d["carga_processo_kcalh"] * 1.163; # kcal/h → W
    
    # Ocupação — pessoas em atividade geram mais calor que ambientes normais
    $ganho_pessoas = $d["pessoas"] * 300; # W/pessoa em trabalho físico leve-moderado
    
    # Renovação de ar por norma sanitária (RDC 216)
    $renovacao_por_pessoa = 30; # m³/h por pessoa
    $ganho_renovacao = ($d["pessoas"] * $renovacao_por_pessoa) * 1.2; # 1,2 W/m³·°C
    
    # Iluminação e cargas diversas
    $ganho_iluminacao = $area * 15; # W/m² padrão industrial/comercial
    
    # Fator de simultaneidade
    $f_simultaneidade = $d["fator_simultaneidade"] / 100;
    
    $total_sem_fator = $ganho_equip + $ganho_processo + $ganho_pessoas + $ganho_renovacao + $ganho_iluminacao;
    $total_w = $total_sem_fator * $f_simultaneidade;
    
    $kcal_h = $total_w * 0.86;
    $btu_h = $total_w * 3.412;
    $tr = $btu_h / 12000;
    
    $regime = $d["turno_unico"] ? "Turno único (8h/dia)" : "Produção contínua";
    
    $msg = "<div class=resultado><h3>✅ Resultado — Processamento de Alimentos</h3><table>
<tr><td>Área do ambiente:</td><td>{$area} m²</td></tr>
<tr><td>Volume:</td><td>{$volume} m³</td></tr>
<tr><td>Regime de operação:</td><td>{$regime}</td></tr>
<tr><td>Equipamentos em operação:</td><td>".number_format($ganho_equip,0,",",".")." W (fator demanda {$d["fator_demanda"]}%)</td></tr>
<tr><td>Calor do processo (vapor/cozimento):</td><td>".number_format($ganho_processo,0,",",".")." W</td></tr>
<tr><td>Ocupação ({$d["pessoas"]} pessoas):</td><td>".number_format($ganho_pessoas,0,",",".")." W</td></tr>
<tr><td>Renovação higiênica RDC 216:</td><td>".number_format($ganho_renovacao,0,",",".")." W</td></tr>
<tr><td>Iluminação e cargas gerais:</td><td>".number_format($ganho_iluminacao,0,",",".")." W</td></tr>
<tr class=linha-total><td>Subtotal:</td><td>".number_format($total_sem_fator,0,",",".")." W</td></tr>
<tr><td>Fator de simultaneidade ({$d["fator_simultaneidade"]}%):</td><td>× {$f_simultaneidade}</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL DE PROJETO:</strong></td><td><strong>".number_format($total_w,0,",",".")." W</strong></td></tr>
<tr><td>Equivalente:</td><td>".number_format($kcal_h,0,",",".")." kcal/h</td></tr>
<tr><td>Capacidade do sistema:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
💡 Recomendações:
<ul style=\"margin:8px 0;padding-left:20px;\">
<li>Exaustão local para equipamentos com vapor reduz a carga em até 40%</li>
<li>Renovação mínima: 30 m³/h por pessoa (ANVISA RDC 216)</li>
<li>Adicione 15% de margem para picos de produção e verão</li>
<li>Ambientes com lavagem frequente: considere desumidificação adicional</li>
</ul>
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-7 — Processamento de Alimentos</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🍳 Item 1-7 — Processamento de Alimentos</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">ANVISA RDC 216 — Cozinha industrial, laticínio, panificação, processamento e embalagem</p>
<form method="POST">
<h2>📐 Dimensões do Ambiente</h2>
<div class="grupo"><label>Comprimento (m):</label><input type=number step=0.01 name=comp value=10.00 required></div>
<div class="grupo"><label>Largura (m):</label><input type=number step=0.01 name=larg value=6.00 required></div>
<div class="grupo"><label>Altura útil (m):</label><input type=number step=0.01 name=alt value=3.50 required></div>
<h2>⚙️ Equipamentos e Processo</h2>
<div class="grupo"><label>Potência total instalada (W):</label><input type=number name=pot_equipamentos value=15000 required></div>
<div class="grupo"><label>Fator de demanda (%):</label><input type=number name=fator_demanda value=70 required><small>equipamentos nem todos ligados ao mesmo tempo</small></div>
<div class="grupo"><label>Calor do processo — vapor/cozimento (kcal/h):</label><input type=number name=carga_processo_kcalh value=8000 required></div>
<h2>👤 Ocupação e Higiene</h2>
<div class="grupo"><label>Número de funcionários:</label><input type=number name=pessoas value=8 required></div>
<div class="grupo"><label>Produção:</label><select name=turno_unico>
<option value=1 selected>Turno único (8h/dia)</option>
<option value=0>Contínua / 24h</option>
</select></div>
<div class="grupo"><label>Fator de simultaneidade (%):</label><input type=number name=fator_simultaneidade value=85 required><small>pico de carga não atinge 100% ao mesmo tempo</small></div>
<button type="submit">📊 Calcular Processamento</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=7" class=voltar>← Voltar para ficha</a>
</div></body></html>
