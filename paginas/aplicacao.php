<?php
include __DIR__ . "/../config/config.php";
$id = (int)($_GET["id"] ?? 0);

if ($id == 4) {
    $row = $conn->query("SELECT * FROM aplicacoes WHERE id = 4")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$row["codigo"]?> — <?=$row["nome"]?></title>
<link rel="stylesheet" href="../assets/css/estilos.css">
<style>
.duas-opcoes {display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin:2rem 0;}
.opcao {padding:2rem; border-radius:10px; text-align:center; text-decoration:none; transition:transform 0.2s;}
.opcao:hover {transform:translateY(-5px);}
.simples {background:#e8f8f5; border:2px solid:#27ae60; color:#2c3e50;}
.refinado {background:#eef2ff; border:2px solid:#3498db; color:#2c3e50;}
.opcao h3 {margin-top:0; font-size:1.4rem;}
.opcao p {color:#666;}
@media(max-width:768px){.duas-opcoes{grid-template-columns:1fr;}}
</style>
</head>
<body><div class="container">
<h1><?=$row["codigo"]?> — <?=$row["nome"]?></h1>
<p style="color:#7f8c8d; margin-bottom:2rem;">NBR 15575 / NBR 16401 — Escolha o método de dimensionamento:</p>

<div class="duas-opcoes">
<a href="calculo_residencial.php" class="opcao simples">
<h3>🟢 Método Simplificado</h3>
<p>Dimensionamento rápido por ambiente<br>
Área × fatores de orientação<br>
Resultado imediato em BTU/h e TR<br>
Ideal: residências pequenas e médias</p>
<div style="margin-top:1rem; font-weight:bold; color:#27ae60;">Acessar →</div>
</a>

<a href="residencial_projetos.php" class="opcao refinado">
<h3>🔵 Método Refinado — Projeto Completo</h3>
<p>Cadastro de projeto + planta<br>
Cálculo ambiente por ambiente<br>
Banco de equipamentos reais<br>
Recomendação por eficiência energética<br>
Relatório técnico detalhado</p>
<div style="margin-top:1rem; font-weight:bold; color:#3498db;">Acessar →</div>
</a>
</div>

<div class="bloco">
<h4>📋 Comparação rápida</h4>
<table>
<tr><th>Aspecto</th><th>Método Simplificado</th><th>Método Refinado</th></tr>
<tr><td>Dados de entrada</td><td>Dimensões + orientação</td><td>Planta + descrição construtiva</td></tr>
<tr><td>Cálculo</td><td>Fórmula prática</td><td>Carga sensível + latente + insolação</td></tr>
<tr><td>Equipamentos</td><td>Valor aproximado</td><td>Catálogo real com SEER e preço</td></tr>
<tr><td>Ambientes</td><td>Um por vez</td><td>Toda a residência integrada</td></tr>
<tr><td>Relatório</td><td>Resultado direto</td><td>Memorial completo</td></tr>
</table>
</div>

<br>
<a href="index.php" class="voltar">← Voltar para Página Principal</a>
</div></body></html>
<?php
exit;
}

// DEMAIS ITENS — mantém funcionando
$row = $conn->query("SELECT * FROM aplicacoes WHERE id = $id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$row["codigo"]?> — <?=$row["nome"]?></title>
<link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body><div class="container">
<h1><?=$row["codigo"]?> — <?=$row["nome"]?></h1>
<div class="bloco"><span class="etiqueta">Descrição:</span><br><?=$row["descricao"]?></div>
<div class="bloco"><span class="etiqueta">Normas de Referência:</span><br><?=$row["normas_referencia"]?></div>
<div class="bloco"><span class="etiqueta">Condições de Projeto:</span><br>
🌡️ Temperatura interna: <?=$row["temp_interna_min"]?> a <?=$row["temp_interna_max"]?>°C<br>
💧 Umidade relativa: <?=$row["umid_interna_min"]?> a <?=$row["umid_interna_max"]?>%<br>
👥 Renovação de ar: <?=$row["renovacao_ar_m3h_por_pessoa"]?> m³/h por pessoa
</div>
<?php
switch($row["codigo"]) {
    case "1-2":
        echo "<a href=calculo_edificio.php class=botao>📐 Iniciar Dimensionamento — Edifícios</a>";
        break;
    case "1-3":
        echo "<a href=calculo_industrial.php class=botao>🏭 Iniciar Dimensionamento — Industrial</a>";
        break;
    case "1-5":
        echo "<a href=calculo_veiculo.php class=botao>🚗 Iniciar Dimensionamento — Veículos</a>";
        break;
    case "1-6":
        echo "<a href=calculo_camara_fria.php class=botao>🧊 Iniciar Dimensionamento — Câmara Fria</a>";
        break;
    case "1-7":
        echo "<a href=calculo_processamento_alimentos.php class=botao>🍳 Iniciar Dimensionamento — Processamento</a>";
        break;
    case "1-8":
        echo "<a href=calculo_industria_quimica.php class=botao>⚗️ Iniciar Dimensionamento — Indústria Química</a>";
        break;
    case "1-9":
        echo "<a href=calculo_aplicacoes_especiais.php class=botao>🏛️ Iniciar Dimensionamento — Aplicações Especiais</a>";
        break;
    case "1-10":
        echo "<a href=conclusao_resumo.php class=botao>🏁 Ver Resumo e Conclusão</a>";
        break;
    default:
        echo "<div class=aviso>🔜 Página de cálculo em desenvolvimento</div>";
}
?>
<br><a href="index.php" class="voltar">← Voltar para Página Principal</a>
</div></body></html>
