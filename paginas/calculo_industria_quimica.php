<?php
/**
 * Item 1-8 — Indústrias Químicas e Processos
 * Base: Wilbert & Stoecker | NBR 14100 / NR-13 / NR-19
 * Carga térmica: reações, mistura, atmosfera controlada, exaustão de segurança
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    # Área e volume
    $area = $d["comp"] * $d["larg"];
    $volume = $area * $d["alt"];
    
    # Ganho por transmissão
    $delta_T = 32 - $d["t_interna"]; # temp externa padrão 32°C
    $U = 0.35; # coeficiente médio paredes industriais isoladas
    $ganho_transmissao = (2*($d["comp"]*$d["alt"] + $d["larg"]*$d["alt"]) + 2*$area) * $U * $delta_T;
    
    # Calor de processo — reação/mistura
    $ganho_reacao = $d["pot_reacao_kW"] * 1000;
    
    # Equipamentos em operação
    $ganho_equip = $d["pot_equipamentos"] * $d["fator_demanda"] / 100;
    
    # Renovação de ar por norma de segurança
    $taxa_renovacao = [
        "comum" => 6,      # trocas/hora — ambiente geral
        "risco_moderado" => 12,
        "inflamavel" => 20,
        "toxico" => 30,
        "explosivo" => 40
    ];
    $renovacoes = $taxa_renovacao[$d["classe_risco"]];
    $m3_por_seg = ($volume * $renovacoes) / 3600;
    $ganho_renovacao = $volume * $renovacoes * 0.335; # W/m³·°C ar úmido
    
    # Ocupação — menor que processamento, mas com EPI
    $ganho_pessoas = $d["pessoas"] * 400; # W/pessoa com vestimenta protetora
    
    # Fator de simultaneidade
    $f_simult = $d["fator_simultaneidade"] / 100;
    
    $total_w = ($ganho_transmissao + $ganho_reacao + $ganho_equip + $ganho_renovacao + $ganho_pessoas) * $f_simult;
    
    $kcal_h = $total_w * 0.86;
    $btu_h = $total_w * 3.412;
    $tr = $btu_h / 12000;
    
    $classe_texto = [
        "comum" => "Ambiente comum",
        "risco_moderado" => "Risco moderado",
        "inflamavel" => "Atmosfera inflamável",
        "toxico" => "Produtos tóxicos",
        "explosivo" => "Zona explosiva"
    ];
    
    $msg = "<div class=resultado><h3>✅ Resultado — Indústria Química</h3><table>
<tr><td>Área do ambiente:</td><td>{$area} m²</td></tr>
<tr><td>Volume:</td><td>{$volume} m³</td></tr>
<tr><td>Temperatura de processo:</td><td>{$d["t_interna"]}°C</td></tr>
<tr><td>Classe de risco:</td><td>{$classe_texto[$d["classe_risco"]]} — {$renovacoes} trocas/hora</td></tr>
<tr><td>Ganho por transmissão térmica:</td><td>".number_format($ganho_transmissao,0,",",".")." W</td></tr>
<tr><td>Calor de reação/mistura:</td><td>".number_format($ganho_reacao,0,",",".")." W</td></tr>
<tr><td>Equipamentos (fator {$d["fator_demanda"]}%):</td><td>".number_format($ganho_equip,0,",",".")." W</td></tr>
<tr><td>Renovação de ar de segurança:</td><td>".number_format($ganho_renovacao,0,",",".")." W ({$m3_por_seg} m³/s)</td></tr>
<tr><td>Ocupação ({$d["pessoas"]} pessoas):</td><td>".number_format($ganho_pessoas,0,",",".")." W</td></tr>
<tr class=linha-total><td>Subtotal:</td><td>".number_format(($ganho_transmissao + $ganho_reacao + $ganho_equip + $ganho_renovacao + $ganho_pessoas),0,",",".")." W</td></tr>
<tr><td>Fator de simultaneidade ({$d["fator_simultaneidade"]}%):</td><td>× {$f_simult}</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL DE PROJETO:</strong></td><td><strong>".number_format($total_w,0,",",".")." W</strong></td></tr>
<tr><td>Equivalente:</td><td>".number_format($kcal_h,0,",",".")." kcal/h</td></tr>
<tr><td>Capacidade do sistema:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
⚠️ <strong>Recomendações de segurança:</strong>
<ul style=\"margin:8px 0;padding-left:20px;\">
<li>Recirculação de ar: <strong>PROIBIDA</strong> em ambientes com risco de vapores</li>
<li>Equipamentos: <strong>antideflagrantes</strong> em Zona Explosiva (NR-19)</li>
<li>Pressão positiva: manter ambiente acima da externo para evitar entrada de contaminantes</li>
<li>Adicionar 20–25% de margem — paradas de emergência podem elevar carga rapidamente</li>
</ul>
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-8 — Indústrias Químicas</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>⚗️ Item 1-8 — Indústrias Químicas e Processos</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">NBR 14100 / NR-19 — Controle de temperatura, atmosfera segura, exaustão e renovação</p>
<form method="POST">
<h2>📐 Ambiente e Processo</h2>
<div class="grupo"><label>Comprimento (m):</label><input type=number step=0.01 name=comp value=12.00 required></div>
<div class="grupo"><label>Largura (m):</label><input type=number step=0.01 name=larg value=8.00 required></div>
<div class="grupo"><label>Altura útil (m):</label><input type=number step=0.01 name=alt value=5.00 required></div>
<div class="grupo"><label>Temperatura de operação (°C):</label><input type=number step=1 name=t_interna value=22 required></div>
<h2>🔥 Fontes de Calor</h2>
<div class="grupo"><label>Calor de reação/mistura (kW):</label><input type=number step=0.1 name=pot_reacao_kW value=12.0 required></div>
<div class="grupo"><label>Potência total instalada (W):</label><input type=number name=pot_equipamentos value=30000 required></div>
<div class="grupo"><label>Fator de demanda (%):</label><input type=number name=fator_demanda value=65 required></div>
<h2>🛡️ Classe de Risco e Higiene</h2>
<div class="grupo"><label>Atmosfera do ambiente:</label><select name=classe_risco>
<option value=comum selected>Ambiente comum (6 trocas/h)</option>
<option value=risco_moderado>Risco moderado (12 trocas/h)</option>
<option value=inflamavel>Vapores inflamáveis (20 trocas/h)</option>
<option value=toxico>Produtos tóxicos (30 trocas/h)</option>
<option value=explosivo>Zona explosiva (40 trocas/h)</option>
</select></div>
<div class="grupo"><label>Número de operadores:</label><input type=number name=pessoas value=4 required></div>
<div class="grupo"><label>Fator de simultaneidade (%):</label><input type=number name=fator_simultaneidade value=80 required></div>
<button type="submit">📊 Calcular Processo Químico</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=8" class=voltar>← Voltar para ficha</a>
</div></body></html>
