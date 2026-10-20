<?php
/**
 * Item 1-9 — Aplicações Especiais
 * Base: NBR 16401 / ANVISA / ISO 14644-1
 * Data centers, salas limpas, laboratórios, arquivos, saúde
 */
include __DIR__ . "/../config/config.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $d = $_POST;
    
    $area = $d["comp"] * $d["larg"];
    $volume = $area * $d["alt"];
    
    # Ganho por equipamentos — componente dominante em aplicações especiais
    $densidade_carga = $d["densidade_carga_Wm2"];
    $ganho_equip = $area * $densidade_carga;
    
    # Transmissão térmica com controle rigoroso de delta T
    $delta_T_ext = 30 - $d["t_interna"];
    $U = 0.20; # isolamento de alto padrão
    $ganho_transmissao = (2*($d["comp"]*$d["alt"] + $d["larg"]*$d["alt"]) + 2*$area) * $U * $delta_T_ext;
    
    # Renovação de ar — definida pela classe de aplicação
    $taxas = [
        "data_center" => 0,      # recirculação com resfriamento
        "sala_limpa" => 30,      # trocas/hora + fluxo laminar
        "laboratorio" => 12,
        "arquivo_museu" => 6,
        "saude_cirurgica" => 25,
        "saude_enfermaria" => 12
    ];
    $renov = $taxas[$d["tipo_aplicacao"]];
    $ganho_renovacao = $volume * $renov * 0.34; # W/m³·°C ar tratado
    
    # Umidade — carga latente separada
    $ganho_latente = $area * $d["fator_umidade"];
    
    # Ocupação — mínima em data centers, maior em saúde
    $por_pessoa = in_array($d["tipo_aplicacao"], ["saude_cirurgica","saude_enfermaria"]) ? 450 : 250;
    $ganho_pessoas = $d["pessoas"] * $por_pessoa;
    
    $f_simul = $d["fator_simultaneidade"] / 100;
    
    $total_sem = $ganho_equip + $ganho_transmissao + $ganho_renovacao + $ganho_latente + $ganho_pessoas;
    $total_w = $total_sem * $f_simul;
    
    $kcal_h = $total_w * 0.86;
    $btu_h = $total_w * 3.412;
    $tr = $btu_h / 12000;
    
    $nomes = [
        "data_center" => "Data Center / Sala de Servidores",
        "sala_limpa" => "Sala Limpa / Laboratório",
        "laboratorio" => "Laboratório de Análise",
        "arquivo_museu" => "Arquivo / Museu / Conservação",
        "saude_cirurgica" => "Sala Cirúrgica",
        "saude_enfermaria" => "Enfermaria / UTI"
    ];
    
    $precisao = [
        "data_center" => "±2°C / ±5% UR",
        "sala_limpa" => "±0,5°C / ±2% UR",
        "laboratorio" => "±1°C / ±3% UR",
        "arquivo_museu" => "±1°C / ±5% UR",
        "saude_cirurgica" => "±1°C / ±5% UR",
        "saude_enfermaria" => "±2°C / ±10% UR"
    ];
    
    $msg = "<div class=resultado><h3>✅ Resultado — {$nomes[$d["tipo_aplicacao"]]}</h3><table>
<tr><td>Área:</td><td>{$area} m²</td></tr>
<tr><td>Volume:</td><td>{$volume} m³</td></tr>
<tr><td>Temperatura alvo:</td><td>{$d["t_interna"]}°C — Precisão: {$precisao[$d["tipo_aplicacao"]]}</td></tr>
<tr><td>Densidade de carga:</td><td>{$densidade_carga} W/m² → ".number_format($ganho_equip,0,",",".")." W</td></tr>
<tr><td>Transmissão térmica:</td><td>".number_format($ganho_transmissao,0,",",".")." W</td></tr>
<tr><td>Renovação de ar:</td><td>{$renov} trocas/hora → ".number_format($ganho_renovacao,0,",",".")." W</td></tr>
<tr><td>Carga latente (umidade):</td><td>".number_format($ganho_latente,0,",",".")." W</td></tr>
<tr><td>Ocupação ({$d["pessoas"]} pessoas):</td><td>".number_format($ganho_pessoas,0,",",".")." W</td></tr>
<tr class=linha-total><td>Subtotal:</td><td>".number_format($total_sem,0,",",".")." W</td></tr>
<tr><td>Fator de simultaneidade ({$d["fator_simultaneidade"]}%):</td><td>× {$f_simul}</td></tr>
<tr class=linha-total><td><strong>CARGA TOTAL DE PROJETO:</strong></td><td><strong>".number_format($total_w,0,",",".")." W</strong></td></tr>
<tr><td>Equivalente:</td><td>".number_format($kcal_h,0,",",".")." kcal/h</td></tr>
<tr><td>Capacidade do sistema:</td><td>".number_format($btu_h,0,",",".")." BTU/h</td></tr>
<tr><td></td><td><strong>".number_format($tr,2,",",".")." Toneladas de Refrigeração</strong></td></tr>
</table>
<p style=\"margin-top:15px;color:#7f8c8d;font-size:14px;\">
💡 <strong>Especificações críticas:</strong>
<ul style=\"margin:8px 0;padding-left:20px;\">
<li>Controle separado: <strong>carga sensível + carga latente</strong> = equipamento com desumidificação dedicada</li>
<li>Redundância: N+1 ou 2N — falha de uma unidade não pode interromper operação</li>
<li>Filtragem: HEPA H13/H14 em salas limpas e saúde</li>
<li>Estabilidade térmica: variação > 0,5°C pode comprometer processo ou conservação</li>
<li>Resfriamento de backup: gerador de energia obrigatório</li>
</ul>
</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>1-9 — Aplicações Especiais</title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1>🏛️ Item 1-9 — Aplicações Especiais</h1>
<p style="color:#7f8c8d;margin-bottom:20px;">NBR 16401 / ANVISA / ISO 14644-1 — Data centers, laboratórios, saúde, conservação</p>
<form method="POST">
<h2>🏗️ Tipo de Aplicação</h2>
<div class="grupo"><label>Ambiente:</label><select name=tipo_aplicacao id=tipo_aplicacao>
<option value=data_center selected>Data Center / Sala de Servidores</option>
<option value=sala_limpa>Sala Limpa / Processo Eletrônico</option>
<option value=laboratorio>Laboratório de Pesquisa</option>
<option value=arquivo_museu>Arquivo / Museu / Conservação</option>
<option value=saude_cirurgica>Sala Cirúrgica / Centro Cirúrgico</option>
<option value=saude_enfermaria>Enfermaria / UTI</option>
</select></div>
<h2>📐 Dimensões e Controle</h2>
<div class="grupo"><label>Comprimento (m):</label><input type=number step=0.01 name=comp value=8.00 required></div>
<div class="grupo"><label>Largura (m):</label><input type=number step=0.01 name=larg value=6.00 required></div>
<div class="grupo"><label>Altura útil (m):</label><input type=number step=0.01 name=alt value=3.20 required></div>
<div class="grupo"><label>Temperatura alvo (°C):</label><input type=number step=0.5 name=t_interna value=22.0 required></div>
<div class="grupo"><label>Densidade de carga (W/m²):</label><input type=number name=densidade_carga_Wm2 value=800 required><small>servidores, equipamentos, iluminação</small></div>
<h2>🎛️ Fatores Adicionais</h2>
<div class="grupo"><label>Carga de umidade (W/m²):</label><input type=number step=1 name=fator_umidade value=40 required></div>
<div class="grupo"><label>Pessoas presentes:</label><input type=number name=pessoas value=2 required></div>
<div class="grupo"><label>Fator de simultaneidade (%):</label><input type=number name=fator_simultaneidade value=90 required></div>
<button type="submit">📊 Calcular Aplicação Especial</button>
</form>
<?=$msg?>
<br><a href="aplicacao.php?id=9" class=voltar>← Voltar para ficha</a>
</div></body></html>
