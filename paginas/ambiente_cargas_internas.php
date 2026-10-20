<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

// ============================================================
// CARREGAR PROJETO E AMBIENTE
// ============================================================
$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);
if (!$projeto_id || !$ambiente_id) { header("Location: residencial_ambiente.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
$amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();
if (!$proj || !$amb) { header("Location: residencial_ambiente.php?projeto_id=$projeto_id"); exit; }

function val($arr, $chave, $padrao = '') {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

$mensagem_sucesso = '';

// ============================================================
// TABELAS DE REFERÊNCIA
// ============================================================

// Atividades — ASHRAE 55 / NBR 16401
// Fracção sensível/latente por atividade
$ATIVIDADES = [
    'sentado'    => ['nome' => 'Sentado (repouso)',      'q_total' => 115, 'frac_sens' => 0.60],
    'escritorio' => ['nome' => 'Escritório (leve)',      'q_total' => 130, 'frac_sens' => 0.58],
    'moderado'   => ['nome' => 'Moderado (loja, aula)',  'q_total' => 145, 'frac_sens' => 0.55],
    'pesado'     => ['nome' => 'Pesado (indústria)',     'q_total' => 185, 'frac_sens' => 0.48],
    'custom'     => ['nome' => 'Valor personalizado',    'q_total' => 115, 'frac_sens' => 0.60],
];

// Iluminação — potência por m² por tipo
$ILUMINACAO = [
    'led'          => ['nome' => 'LED (moderno)',         'w_m2' => 8.0],
    'fluorescente' => ['nome' => 'Fluorescente',          'w_m2' => 15.0],
    'incandescente'=> ['nome' => 'Incandescente (antigo)','w_m2' => 25.0],
    'custom'       => ['nome' => 'Manual',                'w_m2' => 0],
];

// Equipamentos — sugestões
$EQUIPAMENTOS = [
    ['nome' => 'Computador desktop',     'watts' => 150],
    ['nome' => 'Notebook',                'watts' => 65],
    ['nome' => 'Monitor',                 'watts' => 30],
    ['nome' => 'TV LED 32-50"',           'watts' => 100],
    ['nome' => 'Impressora laser',        'watts' => 500],
    ['nome' => 'Geladeira pequena',       'watts' => 200],
    ['nome' => 'Micro-ondas',             'watts' => 1200],
    ['nome' => 'Cafeteira',               'watts' => 800],
];

// ============================================================
// ÁREA E VOLUME DO AMBIENTE (herança da Etapa 1)
// ============================================================
$comp_amb = (float)val($amb, 'comprimento_m', 0);
$larg_amb = (float)val($amb, 'largura_m', 0);
$pe_direito = (float)val($amb, 'peDireita_m', 2.8);
$area_ambiente = $comp_amb * $larg_amb;
$volume_ambiente = $area_ambiente * $pe_direito;

// ============================================================
// CARREGAR CARGAS EXISTENTES
// ============================================================
$ci = $conn->query("SELECT * FROM cargas_internas WHERE ambiente_id = $ambiente_id LIMIT 1")->fetch_assoc();

// Valores padrão sugeridos (se não houver registro)
if (!$ci) {
    $ci = [
        'pessoas' => 2,
        'calor_por_pessoa_w' => 115.0,
        'fator_simultaneidade_pessoas' => 0.90,
        'potencia_iluminacao_w' => round($area_ambiente * 8, 2),  // LED por padrão
        'fator_simultaneidade_iluminacao' => 1.00,
        'potencia_equipamentos_w' => 300.0,
        'fator_simultaneidade_equipamentos' => 0.70,
    ];
}


// ============================================================
// ILUMINAÇÃO — carrega tipos + itens do banco
// ============================================================
$tipos_iluminacao = [];
$r = $conn->query("SELECT * FROM tipos_iluminacao WHERE ativo = 1 ORDER BY potencia_w, nome");
if ($r) { while ($row = $r->fetch_assoc()) $tipos_iluminacao[] = $row; }

$itens_iluminacao = [];
$r = $conn->query("SELECT * FROM iluminacao_ambiente WHERE ambiente_id = " . intval($ambiente_id) . " ORDER BY id");
if ($r) { while ($row = $r->fetch_assoc()) $itens_iluminacao[] = $row; }

// Calcula total automático
$total_iluminacao = 0;
foreach ($itens_iluminacao as $it) {
    $total_iluminacao += floatval($it['potencia_total_w'] ?? 0);
}

// Sincroniza com cargas_internas
if (!empty($itens_iluminacao) && $total_iluminacao > 0) {
    $conn->query("UPDATE cargas_internas SET potencia_iluminacao_w = " . floatval($total_iluminacao) . " WHERE ambiente_id = " . intval($ambiente_id));
    if (isset($ci)) $ci['potencia_iluminacao_w'] = $total_iluminacao;
}


// ============================================================
// EQUIPAMENTOS — carrega tipos + itens do banco
// ============================================================
$tipos_equipamentos = [];
$r = $conn->query("SELECT * FROM tipos_equipamentos WHERE ativo = 1 ORDER BY categoria, nome");
if ($r) { while ($row = $r->fetch_assoc()) $tipos_equipamentos[] = $row; }

$itens_equipamentos = [];
$r = $conn->query("SELECT * FROM equipamentos_ambiente WHERE ambiente_id = " . intval($ambiente_id) . " ORDER BY id");
if ($r) { while ($row = $r->fetch_assoc()) $itens_equipamentos[] = $row; }

$total_equipamentos = 0;
foreach ($itens_equipamentos as $it) {
    $total_equipamentos += floatval($it['potencia_efetiva_w'] ?? $it['potencia_total_w'] ?? 0);
}

// Sincroniza com cargas_internas (se a tabela relacional tem dados)
if (!empty($itens_equipamentos) && $total_equipamentos > 0) {
    $conn->query("UPDATE cargas_internas SET potencia_equipamentos_w = " . floatval($total_equipamentos) . " WHERE ambiente_id = " . intval($ambiente_id));
    if (isset($ci)) $ci['potencia_equipamentos_w'] = $total_equipamentos;
}

// ============================================================
// SALVAR POST
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['salvar_cargas'])) {
    $pessoas = (int)($_POST['pessoas'] ?? 0);
    $calor_pessoa = (float)($_POST['calor_por_pessoa_w'] ?? 115.0);
    $fator_pessoas = (float)($_POST['fator_simultaneidade_pessoas'] ?? 0.90);

    // ============================================================
    // SALVAR ILUMINAÇÃO (múltiplos itens)
    // ============================================================
    if (isset($_POST['ilum_tipo']) && is_array($_POST['ilum_tipo'])) {
        // Limpa os antigos
        $conn->query("DELETE FROM iluminacao_ambiente WHERE ambiente_id = " . intval($ambiente_id));
        
        $total_ilum = 0;
        for ($i = 0; $i < count($_POST['ilum_tipo']); $i++) {
            $tipo_id = intval($_POST['ilum_tipo'][$i] ?? 0);
            $qtd = max(1, intval($_POST['ilum_qtd'][$i] ?? 1));
            $pot_u = floatval($_POST['ilum_pot'][$i] ?? 0);
            $nome_item = trim($_POST['ilum_nome'][$i] ?? '');
            
            if ($tipo_id <= 0 || $pot_u <= 0) continue;
            
            $stmt = $conn->prepare("INSERT INTO iluminacao_ambiente 
                (ambiente_id, tipo_iluminacao_id, nome_item, quantidade, potencia_unitaria_w) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisid", $ambiente_id, $tipo_id, $nome_item, $qtd, $pot_u);
            $stmt->execute();
            $stmt->close();
            
            $total_ilum += $qtd * $pot_u;
        }
        
        // Sincroniza com cargas_internas
        $conn->query("UPDATE cargas_internas SET potencia_iluminacao_w = " . floatval($total_ilum) . " WHERE ambiente_id = " . intval($ambiente_id));
        
        $pot_ilum = $total_ilum;
    }

    $pot_ilum = (float)($_POST['potencia_iluminacao_w'] ?? 0);
    $fator_ilum = (float)($_POST['fator_simultaneidade_iluminacao'] ?? 1.00);

    // ============================================================
    // SALVAR EQUIPAMENTOS (múltiplos itens)
    // ============================================================
    if (isset($_POST['equip_tipo']) && is_array($_POST['equip_tipo'])) {
        $conn->query("DELETE FROM equipamentos_ambiente WHERE ambiente_id = " . intval($ambiente_id));
        
        $total_equip = 0;
        for ($i = 0; $i < count($_POST['equip_tipo']); $i++) {
            $tipo_id = intval($_POST['equip_tipo'][$i] ?? 0);
            $qtd = max(1, intval($_POST['equip_qtd'][$i] ?? 1));
            $pot_u = floatval($_POST['equip_pot'][$i] ?? 0);
            $fator = floatval($_POST['equip_fator'][$i] ?? 1.0);
            $nome_item = trim($_POST['equip_nome'][$i] ?? '');
            
            if ($tipo_id <= 0 || $pot_u <= 0) continue;
            
            $stmt = $conn->prepare("INSERT INTO equipamentos_ambiente 
                (ambiente_id, tipo_equipamento_id, nome_item, quantidade, potencia_unitaria_w, fator_demanda) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisidd", $ambiente_id, $tipo_id, $nome_item, $qtd, $pot_u, $fator);
            $stmt->execute();
            $stmt->close();
            
            $total_equip += $qtd * $pot_u * $fator;
        }
        
        // Sincroniza com cargas_internas
        $conn->query("UPDATE cargas_internas SET potencia_equipamentos_w = " . floatval($total_equip) . " WHERE ambiente_id = " . intval($ambiente_id));
        
        $pot_equip = $total_equip;
    }

    $pot_equip = (float)($_POST['potencia_equipamentos_w'] ?? 0);
    $fator_equip = (float)($_POST['fator_simultaneidade_equipamentos'] ?? 0.70);
    
    // Limpa
    $conn->query("DELETE FROM cargas_internas WHERE ambiente_id = $ambiente_id");
    
    $stmt = $conn->prepare("INSERT INTO cargas_internas 
        (ambiente_id, pessoas, calor_por_pessoa_w, fator_simultaneidade_pessoas,
         potencia_iluminacao_w, fator_simultaneidade_iluminacao,
         potencia_equipamentos_w, fator_simultaneidade_equipamentos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidddddd",
        $ambiente_id, $pessoas, $calor_pessoa, $fator_pessoas,
        $pot_ilum, $fator_ilum, $pot_equip, $fator_equip);
    
    if (!$stmt->execute()) {
        error_log("Erro INSERT cargas_internas: " . $stmt->error);
    }
    
    if (empty($_POST['salvar_apenas'])) {
        header("Location: ambiente_dimensionamento.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
        exit;
    } else {
        $mensagem_sucesso = "Cargas internas salvas com sucesso!";
        // Recarrega para exibir atualizado
        $ci = $conn->query("SELECT * FROM cargas_internas WHERE ambiente_id = $ambiente_id LIMIT 1")->fetch_assoc();
        
        // Re-sincroniza (garante que os totais estão atualizados)
        $soma_ilum = $conn->query("SELECT COALESCE(SUM(potencia_total_w), 0) AS t FROM iluminacao_ambiente WHERE ambiente_id = " . intval($ambiente_id))->fetch_assoc();
        $soma_equip = $conn->query("SELECT COALESCE(SUM(potencia_efetiva_w), 0) AS t FROM equipamentos_ambiente WHERE ambiente_id = " . intval($ambiente_id))->fetch_assoc();
        
        if ($soma_ilum['t'] > 0 || $soma_equip['t'] > 0) {
            $conn->query("UPDATE cargas_internas SET 
                potencia_iluminacao_w = " . floatval($soma_ilum['t']) . ",
                potencia_equipamentos_w = " . floatval($soma_equip['t']) . "
                WHERE ambiente_id = " . intval($ambiente_id));
            $ci = $conn->query("SELECT * FROM cargas_internas WHERE ambiente_id = $ambiente_id LIMIT 1")->fetch_assoc();
        }
    }
}

// ============================================================
// CÁLCULOS
// ============================================================

// 👥 OCUPANTES
$q_pessoas_total = ($ci['pessoas'] ?? 0) * ($ci['calor_por_pessoa_w'] ?? 115.0)
                   * ($ci['fator_simultaneidade_pessoas'] ?? 0.90);
// Fracção média sensível: 0.58 (aproximação ASHRAE)
$q_pessoas_sens = $q_pessoas_total * 0.58;
$q_pessoas_lat  = $q_pessoas_total * 0.42;

// 💡 ILUMINAÇÃO
$q_ilum_total = ($ci['potencia_iluminacao_w'] ?? 0) * ($ci['fator_simultaneidade_iluminacao'] ?? 1.0);
// Iluminação: 100% sensível (toda energia vira calor)
$q_ilum_sens = $q_ilum_total;
$q_ilum_lat  = 0;

// ⚡ EQUIPAMENTOS
$q_equip_total = ($ci['potencia_equipamentos_w'] ?? 0) * ($ci['fator_simultaneidade_equipamentos'] ?? 0.70);
// Equipamentos: 70% sensível / 30% latente
$q_equip_sens = $q_equip_total * 0.70;
$q_equip_lat  = $q_equip_total * 0.30;

// 📊 TOTAIS
$Q_sensivel = $q_pessoas_sens + $q_ilum_sens + $q_equip_sens;
$Q_latente  = $q_pessoas_lat  + $q_ilum_lat  + $q_equip_lat;
$Q_internas = $Q_sensivel + $Q_latente;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 4 — Cargas Internas</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1200px; margin:0 auto; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { flex:1; text-align:center; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#8b5cf6; border-bottom:3px solid #8b5cf6; font-weight:700; }
.passo-link.concluido { color:#2563eb; border-bottom:2px solid #bfdbfe; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.3rem; color:#1e293b; margin-bottom:5px; }
h2 { font-size:1.1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.subtitulo { color:#64748b; margin-bottom:12px; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
.grupo { margin-bottom:16px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:6px; font-size:0.85rem; }
.grupo small { color:#94a3b8; font-size:0.75rem; font-weight:400; margin-left:6px; }
input, select { padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.9rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,0.12); background:#fff; }
.grid-2col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.grid-3col { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:12px; }
.btn { padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; gap:6px; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-proximo { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:#fff; }
.btn-salvar { background:linear-gradient(135deg, #10b981, #059669); color:#fff; }
.botoes { display:flex; gap:12px; margin-top:24px; justify-content:space-between; padding-top:20px; border-top:2px solid #e2e8f0; flex-wrap:wrap; }
.dica { background:#f5f3ff; border-left:3px solid #8b5cf6; padding:12px 16px; margin:16px 0; font-size:0.85rem; color:#5b21b6; border-radius:0 6px 6px 0; }
.aviso-sucesso { background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#065f46; font-weight:600; }
.resumo-box { background:linear-gradient(135deg, #ede9fe, #ddd6fe); border-radius:12px; padding:24px; margin:24px 0; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-top:16px; }
.card { background:#fff; padding:16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.card-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.card-valor { font-size:1.5rem; font-weight:800; }
.card-sub { font-size:0.75rem; color:#64748b; margin-top:4px; }
table { width:100%; border-collapse:collapse; margin-top:8px; }
th { background:#f1f5f9; padding:10px 12px; text-align:left; font-weight:700; color:#475569; font-size:0.8rem; text-transform:uppercase; }
td { padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:0.9rem; color:#334155; }
tfoot td { background:#f1f5f9; font-weight:700; color:#0f172a; }
.calculado { background:#ede9fe; border-color:#c4b5fd; color:#5b21b6; font-weight:600; cursor:not-allowed; }
</style>
</head>
<body>

<div class="barra-progresso">
    <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 1. Dados</a>
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 2. Paredes</a>
    <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 3. Solar</a>
    <a class="passo-link ativo">4. Internas</a>
    <a class="passo-link">5. Ar</a>
    <a class="passo-link">6. Resultado</a>
</div>

<div class="cabecalho">
    <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" style="color:#8b5cf6; text-decoration:none; font-weight:600; font-size:0.9rem;">← Voltar ao Passo 3</a>
    
    <?php if (!empty($mensagem_sucesso)): ?>
    <div class="aviso-sucesso" style="margin-top:16px;">
        ✅ <?= htmlspecialchars($mensagem_sucesso) ?>
    </div>
    <?php endif; ?>
    
    <h1 style="margin-top:16px;">💡 Cargas Internas do Ambiente</h1>
    <p class="subtitulo">
        Ambiente: <strong><?= htmlspecialchars(val($amb, 'nome')) ?></strong>
        · Área: <?= number_format($area_ambiente, 2, ',', '.') ?> m²
        · Volume: <?= number_format($volume_ambiente, 2, ',', '.') ?> m³
    </p>
</div>

<div class="dica">
    💡 <strong>Referências:</strong> ASHRAE Fundamentals / NBR 16401 ·
    Pessoas: 115 W (sentado) a 185 W (pesado) ·
    Iluminação: LED 8 W/m² · Fluorescente 15 W/m² · Incandescente 25 W/m² ·
    Equipamentos: 70% sensível / 30% latente
</div>

<form method="POST">
    <input type="hidden" name="salvar_cargas" value="1">
    
    <!-- ============================================================ -->
    <!-- OCUPANTES -->
    <!-- ============================================================ -->
    <div class="form-card">
        <h2>👥 Ocupantes</h2>
        
        <div class="grid-3col">
            <div class="grupo">
                <label>Número de pessoas</label>
                <input type="number" name="pessoas" min="0" step="1" 
                       value="<?= (int)($ci['pessoas'] ?? 0) ?>">
            </div>
            
            <div class="grupo">
                <label>Atividade <small>(preenche o calor)</small></label>
                <select id="atividade_sel" onchange="aplicarAtividade(this)">
                    <?php foreach ($ATIVIDADES as $key => $atv): ?>
                    <option value="<?= $atv['q_total'] ?>" 
                            data-frac-sens="<?= $atv['frac_sens'] ?>"
                            <?= (float)($ci['calor_por_pessoa_w'] ?? 115) == $atv['q_total'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($atv['nome']) ?> (<?= $atv['q_total'] ?> W)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grupo">
                <label>Calor por pessoa (W)</label>
                <input type="number" step="0.1" name="calor_por_pessoa_w" id="calor_pessoa"
                       value="<?= (float)($ci['calor_por_pessoa_w'] ?? 115.0) ?>">
            </div>
            
            <div class="grupo">
                <label>Fator de simultaneidade</label>
                <input type="number" step="0.01" min="0" max="1" 
                       name="fator_simultaneidade_pessoas"
                       value="<?= (float)($ci['fator_simultaneidade_pessoas'] ?? 0.90) ?>">
                <small>0.90 = 10% estão ausentes</small>
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
        <!-- ILUMINAÇÃO -->
    <div class="form-card">
        <h2>💡 Iluminação</h2>
        <p style="color:#666; font-size:.9em; margin-bottom:12px;">
            Adicione os tipos de lâmpada/luminária do ambiente. Total calculado automaticamente.
        </p>
        
        <input type="hidden" name="potencia_iluminacao_w" id="pot_ilum"
               value="<?= (float)($ci['potencia_iluminacao_w'] ?? 0) ?>">
        
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <thead>
                <tr style="background:#1a3a5c; color:#fff;">
                    <th style="padding:10px; text-align:left;">Tipo de iluminação</th>
                    <th style="padding:10px; width:80px;">Qtd</th>
                    <th style="padding:10px; width:110px;">Pot. Unit. (W)</th>
                    <th style="padding:10px; width:100px;">Subtotal</th>
                    <th style="padding:10px; width:60px;"></th>
                </tr>
            </thead>
            <tbody id="corpo_iluminacao">
                <?php if (empty($itens_iluminacao)): ?>
                    <tr id="linha_vazia_ilum">
                        <td colspan="5" style="padding:16px; text-align:center; color:#999;">
                            Nenhuma lâmpada cadastrada. Clique em "➕ Adicionar" abaixo.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($itens_iluminacao as $it): ?>
                    <tr class="linha-ilum">
                        <td style="padding:6px;">
                            <select name="ilum_tipo[]" onchange="atualizarPotIlum(this)"
                                    style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                                <option value="">— Selecione —</option>
                                <?php foreach ($tipos_iluminacao as $tp): ?>
                                    <option value="<?= $tp['id'] ?>" 
                                            data-pot="<?= $tp['potencia_w'] ?>"
                                            <?= ($tp['id'] == $it['tipo_iluminacao_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tp['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="ilum_nome[]" value="<?= htmlspecialchars($it['nome_item'] ?? '') ?>">
                        </td>
                        <td style="padding:6px;">
                            <input type="number" name="ilum_qtd[]" min="1" value="<?= intval($it['quantidade']) ?>"
                                   onchange="recalcIlum()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                        <td style="padding:6px;">
                            <input type="number" name="ilum_pot[]" step="0.01" min="0" value="<?= floatval($it['potencia_unitaria_w']) ?>"
                                   onchange="recalcIlum()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                        <td style="padding:6px; text-align:right; font-weight:bold;" class="subtotal-ilum">
                            <?= number_format(floatval($it['potencia_total_w'] ?? 0), 0) ?> W
                        </td>
                        <td style="padding:6px; text-align:center;">
                            <button type="button" onclick="removerLinhaIlum(this)" 
                                    style="padding:4px 8px; border:none; background:#e74c3c; color:#fff; cursor:pointer; border-radius:4px;">❌</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#e8f4fd; font-weight:bold;">
                    <td colspan="3" style="padding:10px; text-align:right;">TOTAL ILUMINAÇÃO:</td>
                    <td id="total_iluminacao" style="padding:10px; text-align:right; color:#27ae60; font-size:1.1em;">
                        <?= number_format($total_iluminacao, 0, ',', '.') ?> W
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <div style="margin-top:12px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <button type="button" onclick="adicionarLinhaIlum()" 
                    style="padding:8px 16px; background:#27ae60; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">
                ➕ Adicionar lâmpada
            </button>
            <small style="color:#666;">
                O total é somado automaticamente e usado no cálculo.
            </small>
        </div>
        
        <div class="grid-3col" style="margin-top:16px;">
            <div class="grupo">
                <label>Fator de simultaneidade</label>
                <input type="number" step="0.01" min="0" max="1"
                       name="fator_simultaneidade_iluminacao"
                       value="<?= (float)($ci['fator_simultaneidade_iluminacao'] ?? 1.00) ?>">
                <small>1.00 = todas as lâmpadas ligadas</small>
            </div>
        </div>
    </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- EQUIPAMENTOS -->
    <!-- ============================================================ -->
    <div class="form-card">
        <h2>⚡ Equipamentos</h2>
        <p style="color:#666; font-size:.9em; margin-bottom:12px;">
            Adicione os equipamentos elétricos do ambiente. Total calculado automaticamente.
        </p>
        
        <input type="hidden" name="potencia_equipamentos_w" id="potencia_equipamentos_w"
               value="<?= (float)($ci['potencia_equipamentos_w'] ?? 0) ?>">
        
        <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
            <select id="select_equipamento" style="flex:1; min-width:250px; padding:8px; border:1px solid #ccc; border-radius:4px;">
                <option value="">— Selecione um equipamento —</option>
                <?php foreach ($tipos_equipamentos as $tp): ?>
                    <option value="<?= $tp['id'] ?>" 
                            data-pot="<?= $tp['potencia_w'] ?>"
                            data-fator="<?= $tp['fator_demanda'] ?>"
                            data-nome="<?= htmlspecialchars($tp['nome'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($tp['nome']) ?> — <?= $tp['potencia_w'] ?> W
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="adicionarEquipamentoSelect()" 
                    style="padding:8px 16px; background:#27ae60; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">
                ➕ Adicionar
            </button>
        </div>
        
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <thead>
                <tr style="background:#1a3a5c; color:#fff;">
                    <th style="padding:10px; text-align:left;">Equipamento</th>
                    <th style="padding:10px; width:80px;">Qtd</th>
                    <th style="padding:10px; width:110px;">Pot. Unit. (W)</th>
                    <th style="padding:10px; width:110px;">Fator Dem.</th>
                    <th style="padding:10px; width:100px;">Subtotal</th>
                    <th style="padding:10px; width:60px;"></th>
                </tr>
            </thead>
            <tbody id="corpo_equipamentos">
                <?php if (empty($itens_equipamentos)): ?>
                    <tr id="linha_vazia_equip">
                        <td colspan="6" style="padding:16px; text-align:center; color:#999;">
                            Nenhum equipamento cadastrado. Selecione acima e clique em "Adicionar".
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($itens_equipamentos as $it): ?>
                    <tr class="linha-equip">
                        <td style="padding:6px;">
                            <select name="equip_tipo[]" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                                <option value="">— Selecione —</option>
                                <?php foreach ($tipos_equipamentos as $tp): ?>
                                    <option value="<?= $tp['id'] ?>" 
                                            data-pot="<?= $tp['potencia_w'] ?>"
                                            <?= ($tp['id'] == $it['tipo_equipamento_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tp['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="equip_nome[]" value="<?= htmlspecialchars($it['nome_item'] ?? '') ?>">
                        </td>
                        <td style="padding:6px;">
                            <input type="number" name="equip_qtd[]" min="1" value="<?= intval($it['quantidade']) ?>"
                                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                        <td style="padding:6px;">
                            <input type="number" name="equip_pot[]" step="0.01" min="0" value="<?= floatval($it['potencia_unitaria_w']) ?>"
                                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                        <td style="padding:6px;">
                            <input type="number" name="equip_fator[]" step="0.01" min="0" max="1" value="<?= floatval($it['fator_demanda'] ?? 1.0) ?>"
                                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                        <td style="padding:6px; text-align:right; font-weight:bold;" class="subtotal-equip">
                            <?= number_format(floatval($it['potencia_efetiva_w'] ?? 0), 0) ?> W
                        </td>
                        <td style="padding:6px; text-align:center;">
                            <button type="button" onclick="removerLinhaEquip(this)" 
                                    style="padding:4px 8px; border:none; background:#e74c3c; color:#fff; cursor:pointer; border-radius:4px;">❌</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#e8f4fd; font-weight:bold;">
                    <td colspan="4" style="padding:10px; text-align:right;">TOTAL EQUIPAMENTOS:</td>
                    <td id="total_equipamentos" style="padding:10px; text-align:right; color:#27ae60; font-size:1.1em;">
                        <?= number_format($total_equipamentos, 0, ',', '.') ?> W
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- LISTA DE EQUIPAMENTOS ADICIONADOS -->
        <div id="lista_equipamentos" style="margin-top:20px; display:none;">
            <label style="font-weight:bold; color:#1a3a5c; display:block; margin-bottom:8px;">
                📦 Equipamentos adicionados
            </label>
            <table id="tabela_equipamentos" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="text-align:left; padding:8px;">Equipamento</th>
                        <th style="text-align:center; width:140px;">Quantidade</th>
                        <th style="text-align:right; width:100px;">Watts</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="corpo_equipamentos">
                    <!-- Preenchido via JS -->
                </tbody>
                <tfoot>
                    <tr style="background:#e8f4fd; font-weight:bold;">
                        <td colspan="2" style="padding:8px;">TOTAL</td>
                        <td id="total_equipamentos" style="text-align:right; padding:8px;">0 W</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="grid-3col" style="margin-top:16px;">
            <div class="grupo">
                <label>Fator de simultaneidade</label>
                <input type="number" step="0.01" min="0" max="1" 
                       name="fator_simultaneidade_equipamentos"
                       value="<?= (float)($ci['fator_simultaneidade_equipamentos'] ?? 0.70) ?>">
                <small>0.70 = 30% desligados ou em standby</small>
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- RESUMO PARCIAL -->
    <!-- ============================================================ -->
    <div class="form-card">
        <h2>📊 Resumo Parcial por Tipo</h2>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th style="text-align:right;">Q_sensível (W)</th>
                    <th style="text-align:right;">Q_latente (W)</th>
                    <th style="text-align:right;">Q_total (W)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>👥 Ocupantes</td>
                    <td style="text-align:right;"><?= number_format($q_pessoas_sens, 1, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($q_pessoas_lat, 1, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:600;"><?= number_format($q_pessoas_total, 1, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>💡 Iluminação</td>
                    <td style="text-align:right;"><?= number_format($q_ilum_sens, 1, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($q_ilum_lat, 1, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:600;"><?= number_format($q_ilum_total, 1, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>⚡ Equipamentos</td>
                    <td style="text-align:right;"><?= number_format($q_equip_sens, 1, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($q_equip_lat, 1, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:600;"><?= number_format($q_equip_total, 1, ',', '.') ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td>TOTAL CARGAS INTERNAS</td>
                    <td style="text-align:right; color:#5b21b6;"><?= number_format($Q_sensivel, 1, ',', '.') ?> W</td>
                    <td style="text-align:right; color:#5b21b6;"><?= number_format($Q_latente, 1, ',', '.') ?> W</td>
                    <td style="text-align:right; color:#5b21b6; font-size:1.05rem;"><?= number_format($Q_internas, 1, ',', '.') ?> W</td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <!-- ============================================================ -->
    <!-- RESUMO GERAL -->
    <!-- ============================================================ -->
    <div class="resumo-box">
        <h2 style="border:none; margin-bottom:16px; color:#5b21b6;">📊 Resumo das Cargas Internas</h2>
        
        <div class="card-grid">
            <div class="card" style="border-left:4px solid #3b82f6;">
                <div class="card-label" style="color:#1e40af;">Q SENSÍVEL</div>
                <div class="card-valor" style="color:#1e40af;">
                    <?= number_format($Q_sensivel, 0, ',', '.') ?> W
                </div>
                <div class="card-sub">afeta temperatura</div>
            </div>
            
            <div class="card" style="border-left:4px solid #06b6d4;">
                <div class="card-label" style="color:#0e7490;">Q LATENTE</div>
                <div class="card-valor" style="color:#0e7490;">
                    <?= number_format($Q_latente, 0, ',', '.') ?> W
                </div>
                <div class="card-sub">afeta umidade</div>
            </div>
            
            <div class="card" style="border-left:4px solid #8b5cf6;">
                <div class="card-label" style="color:#5b21b6;">Q TOTAL INTERNAS</div>
                <div class="card-valor" style="color:#5b21b6;">
                    <?= number_format($Q_internas, 0, ',', '.') ?> W
                </div>
                <div class="card-sub">
                    <?= number_format($Q_internas / 3517, 2, ',', '.') ?> TR ·
                    <?= number_format($Q_internas * 3.412, 0, ',', '.') ?> BTU/h
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- BOTÕES -->
    <!-- ============================================================ -->
    <div class="botoes">
        <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">
            ← Voltar ao Passo 3
        </a>
        <div style="display:flex; gap:12px; margin-left:auto; flex-wrap:wrap;">
            <button type="submit" name="salvar_apenas" value="1" class="btn btn-salvar">
                💾 Salvar
            </button>
            <button type="submit" name="prosseguir" value="1" class="btn btn-proximo">
                Salvar e Prosseguir → Dimensionamento
            </button>
        </div>
    </div>
</form>

<script>
// ============================================================
// FUNÇÕES EXISTENTES
// ============================================================
function aplicarAtividade(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('calor_pessoa').value = opt.value;
}

// ============================================================
// ILUMINAÇÃO — clona template
// ============================================================
function adicionarLinhaIlum() {
    const corpo = document.getElementById('corpo_iluminacao');
    const vazia = document.getElementById('linha_vazia_ilum');
    if (vazia) vazia.remove();
    
    const tpl = document.getElementById('tpl_ilum');
    const clone = tpl.content.cloneNode(true);
    corpo.appendChild(clone);
}

function atualizarPotIlum(sel) {
    const opt = sel.options[sel.selectedIndex];
    const pot = parseFloat(opt.dataset.pot) || 0;
    const tr = sel.closest('tr');
    tr.querySelector('input[name="ilum_pot[]"]').value = pot.toFixed(2);
    tr.querySelector('input[name="ilum_nome[]"]').value = opt.textContent.trim();
    recalcIlum();
}

function removerLinhaIlum(btn) {
    btn.closest('tr').remove();
    recalcIlum();
}

function recalcIlum() {
    let total = 0;
    document.querySelectorAll('.linha-ilum').forEach(tr => {
        const inputQtd = tr.querySelector('input[name="ilum_qtd[]"]');
        const inputPot = tr.querySelector('input[name="ilum_pot[]"]');
        if (!inputQtd || !inputPot) return;
        const qtd = parseFloat(inputQtd.value) || 0;
        const pot = parseFloat(inputPot.value) || 0;
        const sub = qtd * pot;
        total += sub;
        const subEl = tr.querySelector('.subtotal-ilum');
        if (subEl) subEl.textContent = sub.toFixed(0) + ' W';
    });
    const totalEl = document.getElementById('total_iluminacao');
    if (totalEl) totalEl.textContent = total.toFixed(0) + ' W';
    const hidden = document.getElementById('pot_ilum');
    if (hidden) hidden.value = total.toFixed(2);
}

// ============================================================
// EQUIPAMENTOS — clona template
// ============================================================
function adicionarEquipamentoSelect() {
    const sel = document.getElementById('select_equipamento');
    const opt = sel.options[sel.selectedIndex];
    
    if (!opt.value) {
        alert('Selecione um equipamento primeiro');
        return;
    }
    
    const corpo = document.getElementById('corpo_equipamentos');
    const vazia = document.getElementById('linha_vazia_equip');
    if (vazia) vazia.remove();
    
    const tpl = document.getElementById('tpl_equip');
    const clone = tpl.content.cloneNode(true);
    const tr = clone.querySelector('tr');
    
    // Preenche valores com base no equipamento selecionado
    const selNovo = tr.querySelector('select[name="equip_tipo[]"]');
    selNovo.value = opt.value;
    
    tr.querySelector('input[name="equip_nome[]"]').value = opt.dataset.nome || opt.textContent.trim();
    tr.querySelector('input[name="equip_pot[]"]').value = opt.dataset.pot || 0;
    tr.querySelector('input[name="equip_fator[]"]').value = opt.dataset.fator || 1;
    
    corpo.appendChild(clone);
    recalcEquip();
}

function removerLinhaEquip(btn) {
    btn.closest('tr').remove();
    recalcEquip();
}

function recalcEquip() {
    let total = 0;
    document.querySelectorAll('.linha-equip').forEach(tr => {
        const inputQtd = tr.querySelector('input[name="equip_qtd[]"]');
        const inputPot = tr.querySelector('input[name="equip_pot[]"]');
        const inputFator = tr.querySelector('input[name="equip_fator[]"]');
        if (!inputQtd || !inputPot) return;
        const qtd = parseFloat(inputQtd.value) || 0;
        const pot = parseFloat(inputPot.value) || 0;
        const fator = parseFloat(inputFator ? inputFator.value : 1) || 1;
        const sub = qtd * pot * fator;
        total += sub;
        const subEl = tr.querySelector('.subtotal-equip');
        if (subEl) subEl.textContent = sub.toFixed(0) + ' W';
    });
    const totalEl = document.getElementById('total_equipamentos');
    if (totalEl) totalEl.textContent = total.toFixed(0) + ' W';
    const hidden = document.getElementById('potencia_equipamentos_w');
    if (hidden) hidden.value = total.toFixed(2);
}
</script>


<!-- Templates para clonagem (renderizados pelo PHP) -->
<template id="tpl_ilum">
    <tr class="linha-ilum">
        <td style="padding:6px;">
            <select name="ilum_tipo[]" onchange="atualizarPotIlum(this)"
                    style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                <option value="">— Selecione —</option>
                <?php foreach ($tipos_iluminacao as $tp): ?>
                    <option value="<?= $tp['id'] ?>" data-pot="<?= $tp['potencia_w'] ?>">
                        <?= htmlspecialchars($tp['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="ilum_nome[]" value="">
        </td>
        <td style="padding:6px;">
            <input type="number" name="ilum_qtd[]" min="1" value="1"
                   onchange="recalcIlum()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
        </td>
        <td style="padding:6px;">
            <input type="number" name="ilum_pot[]" step="0.01" min="0" value="0"
                   onchange="recalcIlum()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
        </td>
        <td style="padding:6px; text-align:right; font-weight:bold;" class="subtotal-ilum">0 W</td>
        <td style="padding:6px; text-align:center;">
            <button type="button" onclick="removerLinhaIlum(this)" 
                    style="padding:4px 8px; border:none; background:#e74c3c; color:#fff; cursor:pointer; border-radius:4px;">❌</button>
        </td>
    </tr>
</template>

<template id="tpl_equip">
    <tr class="linha-equip">
        <td style="padding:6px;">
            <select name="equip_tipo[]" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
                <option value="">— Selecione —</option>
                <?php foreach ($tipos_equipamentos as $tp): ?>
                    <option value="<?= $tp['id'] ?>" 
                            data-pot="<?= $tp['potencia_w'] ?>"
                            data-fator="<?= $tp['fator_demanda'] ?>">
                        <?= htmlspecialchars($tp['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="equip_nome[]" value="">
        </td>
        <td style="padding:6px;">
            <input type="number" name="equip_qtd[]" min="1" value="1"
                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
        </td>
        <td style="padding:6px;">
            <input type="number" name="equip_pot[]" step="0.01" min="0" value="0"
                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
        </td>
        <td style="padding:6px;">
            <input type="number" name="equip_fator[]" step="0.01" min="0" max="1" value="1"
                   onchange="recalcEquip()" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px;">
        </td>
        <td style="padding:6px; text-align:right; font-weight:bold;" class="subtotal-equip">0 W</td>
        <td style="padding:6px; text-align:center;">
            <button type="button" onclick="removerLinhaEquip(this)" 
                    style="padding:4px 8px; border:none; background:#e74c3c; color:#fff; cursor:pointer; border-radius:4px;">❌</button>
        </td>
    </tr>
</template>

</body>
</html>
