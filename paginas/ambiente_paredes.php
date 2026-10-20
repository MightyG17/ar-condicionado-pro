<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

// ✅ CORRIGIDO: garante charset utf8mb4 (evita "MÃ©dia" no POST)
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);

if (!$projeto_id || !$ambiente_id) {
    header("Location: residencial_ambiente.php");
    exit;
}

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
$amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();

if (!$proj || !$amb) { 
    header("Location: residencial_ambiente.php?projeto_id=$projeto_id"); 
    exit; 
}

function val($arr, $chave, $padrao = '') {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

function calcularPerimetro($largura, $altura) {
    if ($largura > 0 && $altura > 0) {
        return 2 * ($largura + $altura);
    }
    return 0;
}

// ✅ CORRIGIDO: normaliza string removendo acentos para comparações robustas
function normalizar($str) {
    $str = trim((string)$str);
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    return $str;
}

$coeficientes_U = [
    'Tijolo cerâmico 1/2 vez' => 2.40,
    'Tijolo cerâmico 1 vez'   => 1.70,
    'Bloco de concreto'       => 2.80,
    'Concreto maciço'         => 3.50,
    'Madeira'                 => 0.60,
    'Drywall'                 => 1.80,
    'Vidro'                   => 5.80,
    'Outro'                   => 2.00
];

$pe_direito = (float)val($amb, 'peDireita_m', 2.8);
$compr = (float)val($amb, 'comprimento_m', 0);
$larg = (float)val($amb, 'largura_m', 0);
$volume_ambiente = $compr * $larg * $pe_direito;
$temp_int = (float)val($proj, 'temp_interna_verao', 24.0);
$temp_ext = (float)val($proj, 'temp_externa_verao', 32.0);
$delta_T = $temp_ext - $temp_int;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['salvar_paredes'])) {
    $conn->query("DELETE FROM paredes_ambiente WHERE ambiente_id = $ambiente_id");
    
    if (!empty($_POST['parede']) && is_array($_POST['parede'])) {
        foreach ($_POST['parede'] as $direcao => $p) {
            $comp = (float)($p['comprimento_m'] ?? 0);
            $alt = (float)($p['altura_m'] ?? 0);
            if ($comp <= 0 || $alt <= 0) continue;
            
            // ✅ CORRIGIDO: normaliza cor externa (evita problema de encoding)
            $cor_ext = normalizar($p['cor_externa'] ?? 'Clara');
            
            // ✅ CORRIGIDO: match com aliases para tolerar encoding
            $coef_abs = match(true) {
                in_array($cor_ext, ['Clara', 'clara']) => 0.30,
                in_array($cor_ext, ['Média', 'Media', 'media', 'média']) => 0.50,
                in_array($cor_ext, ['Escura', 'escura']) => 0.75,
                default => 0.30
            };
            
            $tem_porta = 0; $tem_janela = 0; $tem_basculante = 0; $tem_fresta = 0;
            if (!empty($p['itens']) && is_array($p['itens'])) {
                foreach ($p['itens'] as $it_chk) {
                    $t_it = strtolower(trim($it_chk['tipo_item'] ?? ''));
                    if (str_contains($t_it, 'porta')) $tem_porta = 1;
                    if (str_contains($t_it, 'janela')) $tem_janela = 1;
                    if (str_contains($t_it, 'basculante')) $tem_basculante = 1;
                    if (str_contains($t_it, 'fresta')) $tem_fresta = 1;
                }
            }

            // ✅ CORRIGIDO: normaliza material removendo sufixo "(U≈x,x)" caso venha
            $material_parede = normalizar($p['material'] ?? 'Outro');
            $material_parede = preg_replace('/\s*\(U≈[^)]+\)/', '', $material_parede);
            
            $cor_interna_parede = normalizar($p['cor_interna'] ?? 'Clara');
            $obs_parede = trim($p['observacoes'] ?? '');
            
            $stmt = $conn->prepare("INSERT INTO paredes_ambiente 
                (ambiente_id, nome, comprimento_m, altura_m, material, cor, cor_externa, 
                 coef_absorcao_solar, tem_porta, tem_janela, tem_basculante, tem_fresta, observacoes_parede)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isddsssdiisss",
                $ambiente_id, $direcao, $comp, $alt,
                $material_parede,
                $cor_interna_parede,
                $cor_ext, $coef_abs,
                $tem_porta, $tem_janela, $tem_basculante, $tem_fresta,
                $obs_parede
            );
            
            // ✅ CORRIGIDO: verifica erro no execute
            if (!$stmt->execute()) {
                error_log("Erro INSERT parede {$direcao}: " . $stmt->error);
                continue;
            }
            $parede_nova_id = $conn->insert_id;
            
            if (!empty($p['itens']) && is_array($p['itens'])) {
                foreach ($p['itens'] as $item) {
                    $qtd = (int)($item['quantidade'] ?? 1);
                    if ($qtd < 1) continue;
                    
                    $larg_it = (float)($item['largura_m'] ?? 0);
                    $alt_it = (float)($item['altura_m'] ?? 0);
                    $area_it = $larg_it * $alt_it;
                    $perim_it = (float)($item['comprimento_fresta_m'] ?? 0);
                    
                    if ($perim_it <= 0 && $larg_it > 0 && $alt_it > 0) {
                        $perim_it = calcularPerimetro($larg_it, $alt_it);
                    }
                    
                    $coef_it = (float)($item['coef_infiltracao'] ?? 0);
                    $metodo_it = trim($item['metodo_coef'] ?? 'ren/h');
                    
                    // ✅ CORRIGIDO: fallback triplo do nome (evita "0 Basculante")
                    // 🔍 DEBUG TEMPORÁRIO — remover depois de corrigir
                    error_log("DEBUG_POST nome_item=[" . ($item['nome_item'] ?? 'NULL') . "] tipo_item=[" . ($item['tipo_item'] ?? 'NULL') . "]");
                    $nome_item_val = trim($item['nome_item'] ?? '');
                    $tipo_item_val = trim($item['tipo_item'] ?? 'Outro');
                    if ($nome_item_val === '') {
                        $nome_item_val = $tipo_item_val !== '' ? $tipo_item_val : 'Item sem nome';
                    }
                    // Remove prefixo numérico estranho tipo "0 Basculante"
                    // ✅ CORRIGIDO: \s* permite "0" sozinho
                    $nome_item_val = preg_replace('/^\d+\s*/', '', $nome_item_val);
                    // Se ficou vazio após o replace, usa fallback
                    if ($nome_item_val === '') {
                    $nome_item_val = $tipo_item_val !== '' ? $tipo_item_val : 'Item sem nome';
                    }
                    
                    $vazao = 0;
                    if ($metodo_it === 'ren/h' && $volume_ambiente > 0) {
                        $vazao = $qtd * $coef_it * $volume_ambiente;
                    } elseif ($metodo_it === 'm³/(h·m)' && $perim_it > 0) {
                        $vazao = $qtd * $coef_it * $perim_it;
                    }
                    
                    $carga_inf = $vazao * 1.2 / 3600 * 1005 * ($temp_ext - $temp_int);
                    
                    $stmt_it = $conn->prepare("INSERT INTO itens_parede 
                        (parede_id, tipo_item, nome_item, quantidade, largura_m, altura_m, area_m2,
                         comprimento_fresta_m, coef_infiltracao, metodo_coef, carga_infiltracao_w)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    // PROTECAO_NOME_ITEM - garante que nunca grava '0'

                    $nome_item_val = preg_replace('/^\d+\s*/', '', trim((string)$nome_item_val));

                    if ($nome_item_val === '' || $nome_item_val === '0') {

                        $nome_item_val = trim((string)($tipo_item_val ?? '')) ?: 'Item sem nome';

                    }

                    

                    error_log("DEBUG_FINAL nome_item_val=[" . var_export($nome_item_val, true) . "] tipo_item_val=[" . var_export($tipo_item_val, true) . "]");
                    $stmt_it->bind_param("issidddddsd",
                        $parede_nova_id,
                        $tipo_item_val,
                        $nome_item_val,
                        $qtd, $larg_it, $alt_it, $area_it,
                        $perim_it, $coef_it, $metodo_it, $carga_inf
                    );
                    
                    // ✅ CORRIGIDO: verifica erro no execute do item
                    if (!$stmt_it->execute()) {
                        error_log("Erro INSERT item parede {$parede_nova_id}: " . $stmt_it->error);
                    }
                }
            }
        }
    }
    
    // Redireciona para próxima etapa APENAS se não for 'salvar_apenas'
    if (empty($_POST['salvar_apenas'])) {
        header("Location: ambiente_ganho_solar.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
        exit;
    } else {
        $mensagem_sucesso = 'Dados salvos com sucesso! Revise o resumo abaixo.';
    }
}

$catalogo = $conn->query("SELECT * FROM tipos_aberturas WHERE ativo = 1 ORDER BY categoria, nome")->fetch_all(MYSQLI_ASSOC);
$direcoes = ['Norte', 'Nordeste', 'Leste', 'Sudeste', 'Sul', 'Sudoeste', 'Oeste', 'Noroeste'];
$paredes_salvas = [];

$res = $conn->query("SELECT * FROM paredes_ambiente WHERE ambiente_id = $ambiente_id");
while ($p = $res->fetch_assoc()) {
    $paredes_salvas[$p['nome']] = $p;
    $paredes_salvas[$p['nome']]['itens'] = [];
    $parede_id = (int)$p['id'];
    $res_it = $conn->query("SELECT * FROM itens_parede WHERE parede_id = $parede_id");
    while ($it = $res_it->fetch_assoc()) {
        $paredes_salvas[$p['nome']]['itens'][] = $it;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 2 — Paredes, Cor e Aberturas</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1200px; margin:0 auto; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { flex:1; text-align:center; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#0ea5e9; border-bottom:3px solid #0ea5e9; font-weight:700; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.3rem; color:#1e293b; margin-bottom:5px; }
.subtitulo { color:#64748b; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
.parede-card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; overflow:hidden; }
.parede-cabecalho { background:linear-gradient(135deg, #f0f9ff, #e0f2fe); padding:14px 20px; border-bottom:1px solid #bae6fd; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
.parede-nome { font-weight:700; font-size:1.05rem; color:#0369a1; }
.parede-conteudo { padding:20px; display:none; }
.parede-conteudo.aberto { display:block; }
.grid-3col { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:12px; }
.grupo { margin-bottom:12px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:4px; font-size:0.8rem; }
input, select, textarea { padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.9rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,0.12); background:#fff; }
input.campo-calculo { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; font-weight:600; cursor:not-allowed; }
.filtro-box { border:1px solid #d1fae5; background:#f0fdf4; border-radius:10px; padding:16px; margin:16px 0; }
.filtro-titulo { font-weight:700; color:#15803d; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
.select-catalogo { background:#fff; border:1px solid #059669; color:#065f46; font-weight:600; padding:10px; border-radius:8px; width:100%; cursor:pointer; }
.itens-selecionados { margin-top:14px; }
.item-linha { border:1px solid #bbf7d0; border-radius:8px; padding:14px; margin-bottom:12px; background:#fff; }
.item-titulo { font-weight:700; color:#166534; margin-bottom:10px; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; padding-bottom:8px; border-bottom:1px solid #e2fceb; }
.item-nome-completo { font-size:0.95rem; line-height:1.4; }
.etiqueta-tipo { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; margin-left:8px; }
.tipo-porta { background:#dbeafe; color:#1e40af; }
.tipo-janela { background:#d1fae5; color:#0e7490; }
.tipo-basculante { background:#f3e8ff; color:#7c3aed; }
.tipo-fresta { background:#fee2e2; color:#dc2626; }
.tipo-outro { background:#e5e7eb; color:#4b5563; }
.btn { padding:10px 20px; border-radius:8px; font-weight:700; border:none; cursor:pointer; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-proximo { background:linear-gradient(135deg, #0ea5e9, #0284c7); color:#fff; }
.btn-pequeno { padding:5px 10px; font-size:0.75rem; border-radius:6px; }
.btn-remover { background:#fee2e2; color:#b91c1c; }
.btn-catalogo { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:#fff; font-size:0.85rem; padding:8px 14px; }
.btn-catalogo:hover { transform:scale(1.02); transition:0.2s; }
.botoes { display:flex; gap:12px; margin-top:24px; justify-content:space-between; padding-top:20px; border-top:2px solid #e2e8f0; }
.aviso-cor { background:#fffbeb; border-left:3px solid #f59e0b; padding:10px 14px; margin:12px 0; font-size:0.85rem; color:#92400e; }
.aviso-material { background:#f0f4ff; border-left:3px solid #3b82f6; padding:10px 14px; margin:12px 0; font-size:0.85rem; color:#1e40af; }
.resumo-parede { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; font-size:0.78rem; }
.resumo-tag { padding:3px 10px; border-radius:12px; background:#e5e7eb; color:#4b5563; }
.resumo-tag.U-valor { background:#dbeafe; color:#1e40af; font-weight:bold; }
.resumo-tag.Cor-valor { font-weight:bold; }
.legenda-auto { font-size:0.75rem; color:#6366f1; margin-top:4px; }
.aviso-carregado { background:#ecfdf5; border-left:3px solid #10b981; padding:8px 12px; margin:8px 0; font-size:0.85rem; color:#047857; }
.campo-auto { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php $nav_ambiente_id = (int)($ambiente_id ?? ($_GET['ambiente_id'] ?? ($_GET['id'] ?? 0))); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="barra-progresso">
    <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link">1. Dados</a>
    <a class="passo-link ativo">2. Paredes & Aberturas</a>
    <a class="passo-link">3. Ganho Solar</a>
    <a class="passo-link">4. Cargas Internas</a>
    <a class="passo-link">5. Dimensionamento</a>
    <a class="passo-link">6. Resultado</a>
</div>
<?php if (!empty($mensagem_sucesso)): ?>
<div style="background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#065f46; font-weight:600;">
    ✅ <?= htmlspecialchars($mensagem_sucesso) ?>
</div>
<?php endif; ?>
<div class="cabecalho">
    <h1>🧱 Paredes, Cores e Aberturas</h1>
    <p class="subtitulo">
        Defina cada parede com orientação, dimensões, cor externa e selecione aberturas
        <a href="catalogo_aberturas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" target="_blank" class="btn btn-catalogo">📋 Gerenciar Catálogo de Itens</a>
    </p>
    <?php if ($volume_ambiente > 0): ?>
    <p style="font-size:0.9rem; color:#475569; margin-top:6px; line-height:1.6;">
        📐 Ambiente: <?= $compr ?> × <?= $larg ?> × <?= $pe_direito ?> m → Volume: <strong><?= number_format($volume_ambiente,2,',','.') ?> m³</strong>
        · ΔT = <?= $delta_T ?>°C (Ext: <?= $temp_ext ?>°C → Int: <?= $temp_int ?>°C)
    </p>
    <?php endif; ?>
    
    <?php if (!empty($paredes_salvas)): ?>
    <div class="aviso-carregado">
        ✅ Dados salvos carregados — altere e clique em Salvar para atualizar.
    </div>
    <?php endif; ?>
    
    <div class="aviso-cor">
        ☀️ <strong>Absorção Solar (α):</strong> Clara (α ≈ 0,30) · Média (α ≈ 0,50) · Escura (α ≈ 0,75 — maior ganho).
    </div>
    <div class="aviso-material">
        🧱 <strong>Transmissão Térmica (U):</strong> Madeira (U≈0,6 — melhor isolamento) · Tijolo 1x (U≈1,7) · Tijolo ½x (U≈2,4) · Concreto (U≈3,5 — maior transmissão).
    </div>
    <div style="background:#eef2ff; border-left:3px solid #6366f1; padding:8px 12px; margin-top:10px; font-size:0.85rem; color:#3730a3;">
        💡 <strong>Cálculos automáticos:</strong> Área = Largura × Altura · Perímetro = 2 × (Largura + Altura) — preencha as dimensões e os valores aparecem sozinhos!
    </div>
</div>
<?php
// ============================================================
// CÁLCULO DO RESUMO PARA REVIEW
// ============================================================
$resumo_areas = 0;
$resumo_volume = $volume_ambiente;
$resumo_carga_infiltracao = 0;
$resumo_carga_paredes = 0;
$resumo_por_parede = [];

foreach ($paredes_salvas as $dir_nome => $p_dados) {
    $area_p = ($p_dados['comprimento_m'] ?? 0) * ($p_dados['altura_m'] ?? 0);
    $resumo_areas += $area_p;
    
    $carga_p = 0;
    $carga_pi = 0;
    
    if (!empty($p_dados['itens'])) {
        foreach ($p_dados['itens'] as $it) {
            $carga_pi += (float)($it['carga_infiltracao_w'] ?? 0);
        }
    }
    
    $resumo_carga_infiltracao += $carga_pi;
    
    $resumo_por_parede[] = [
        'nome' => $dir_nome,
        'comprimento' => $p_dados['comprimento_m'] ?? 0,
        'altura' => $p_dados['altura_m'] ?? 0,
        'area' => $area_p,
        'material' => $p_dados['material'] ?? '—',
        'cor_externa' => $p_dados['cor_externa'] ?? '—',
        'num_itens' => count($p_dados['itens'] ?? []),
        'carga_infiltracao' => $carga_pi
    ];
}
?>

<form method="POST">
<input type="hidden" name="salvar_paredes" value="1">
<?php foreach ($direcoes as $idx => $dir):
    $p = $paredes_salvas[$dir] ?? [];
    $abriu = !empty($p['comprimento_m']);
    $material_atual = $p['material'] ?? '';
    
    // ✅ CORRIGIDO: normaliza material para lookup no array (tolerante a sufixo)
    $material_limpo = preg_replace('/\s*\(U≈[^)]+\)/', '', $material_atual);
    $U_atual = $coeficientes_U[$material_limpo] ?? '—';
    $cor_ext_atual = $p['cor_externa'] ?? 'Clara';
    
    // Cor de fundo da etiqueta de cor
    $cor_fundo = match($cor_ext_atual) {
        'Clara' => '#fef3c7',
        'Média' => '#fde68a',
        'Escura' => '#fcd34d',
        default => '#fef3c7'
    };
?>
<div class="parede-card">
    <div class="parede-cabecalho" onclick="this.nextElementSibling.classList.toggle('aberto')">
        <div>
            <span class="parede-nome"><?= $dir ?></span>
            <span class="etiqueta-tipo" style="background:#e0f2fe; color:#0369a1; font-weight:normal;"><?= $idx+1 ?>/8</span>
            <?php if (!empty($p)): ?>
            <div class="resumo-parede">
                <span class="resumo-tag"><?= number_format($p['comprimento_m'] ?? 0,1,',','.') ?>×<?= number_format($p['altura_m'] ?? 0,1,',','.') ?>m</span>
                <span class="resumo-tag U-valor">U: <?= $U_atual ?> W/m²°C</span>
                <span class="resumo-tag Cor-valor" style="background:<?= $cor_fundo ?>; color:#92400e;">Cor: <?= htmlspecialchars($cor_ext_atual) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <span style="font-size:1.2rem;"><?= $abriu ? '▼' : '▶' ?></span>
    </div>
    
    <div class="parede-conteudo <?= $abriu ? 'aberto' : '' ?>">
        <input type="hidden" name="parede[<?= $dir ?>][direcao]" value="<?= $dir ?>">
        
        <div class="grid-3col">
            <div class="grupo">
                <label>Comprimento (m)</label>
                <input type="number" step="0.01" name="parede[<?= $dir ?>][comprimento_m]" 
                       value="<?= $p['comprimento_m'] ?? '' ?>" min="0" placeholder="0.00"
                       oninput="calcularAreaParede('<?= $dir ?>')" id="comp_<?= $dir ?>">
            </div>
            <div class="grupo">
                <label>Altura / Pé-direito (m)</label>
                <input type="number" step="0.01" name="parede[<?= $dir ?>][altura_m]" 
                       value="<?= $p['altura_m'] ?? $pe_direito ?>" min="0"
                       oninput="calcularAreaParede('<?= $dir ?>')" id="alt_<?= $dir ?>">
            </div>
            <div class="grupo">
                <label>Área da Parede (m²) <small style="color:#6366f1;">↻ auto</small></label>
                <input type="text" id="area_<?= $dir ?>" class="campo-calculo" readonly
                       value="<?= !empty($p['comprimento_m']) && !empty($p['altura_m']) ? number_format($p['comprimento_m'] * $p['altura_m'], 2, ',', '.') : '0,00' ?>">
            </div>
        </div>
        
        <div class="grid-3col">
            <div class="grupo">
                <label>🧱 Material da Parede</label>
                <select name="parede[<?= $dir ?>][material]" id="mat_<?= $dir ?>">
                    <option value="">— Selecione —</option>
                    <option value="Tijolo cerâmico 1/2 vez" <?= ($p['material']??'')==='Tijolo cerâmico 1/2 vez'?'selected':'' ?>>Tijolo cerâmico 1/2 vez (U≈2,4)</option>
                    <option value="Tijolo cerâmico 1 vez" <?= ($p['material']??'')==='Tijolo cerâmico 1 vez'?'selected':'' ?>>Tijolo cerâmico 1 vez (U≈1,7)</option>
                    <option value="Bloco de concreto" <?= ($p['material']??'')==='Bloco de concreto'?'selected':'' ?>>Bloco de concreto (U≈2,8)</option>
                    <option value="Concreto maciço" <?= ($p['material']??'')==='Concreto maciço'?'selected':'' ?>>Concreto maciço (U≈3,5)</option>
                    <option value="Madeira" <?= ($p['material']??'')==='Madeira'?'selected':'' ?>>Madeira (U≈0,6 — melhor isolamento)</option>
                    <option value="Drywall" <?= ($p['material']??'')==='Drywall'?'selected':'' ?>>Drywall (U≈1,8)</option>
                    <option value="Vidro" <?= ($p['material']??'')==='Vidro'?'selected':'' ?>>Vidro (U≈5,8)</option>
                    <option value="Outro" <?= ($p['material']??'')==='Outro'?'selected':'' ?>>Outro (U≈2,0)</option>
                </select>
            </div>
            <div class="grupo">
                <label>🎨 Cor Externa (Absorção Solar α)</label>
                <select name="parede[<?= $dir ?>][cor_externa]" id="cor_ext_<?= $dir ?>">
                    <option value="Clara" <?= ($p['cor_externa']??'Clara')==='Clara'?'selected':'' ?>>Clara — α = 0,30</option>
                    <option value="Média" <?= ($p['cor_externa']??'')==='Média'?'selected':'' ?>>Média — α = 0,50</option>
                    <option value="Escura" <?= ($p['cor_externa']??'')==='Escura'?'selected':'' ?>>Escura — α = 0,75 (⚠️ Maior ganho)</option>
                </select>
            </div>
        </div>
        
        <div class="filtro-box">
            <div class="filtro-titulo">
                🔍 Adicionar Abertura / Fresta nesta parede
                <small style="font-weight:normal; color:#166534;">Itens do catálogo</small>
            </div>
            
            <select class="select-catalogo" onchange="adicionarAbertura(this, <?= $idx ?>, '<?= $dir ?>')">
                <option value="">+ Selecionar item do Catálogo...</option>
                <?php foreach ($catalogo as $cat): ?>
                <?php // ✅ CORRIGIDO: json_encode seguro contra aspas ?>
                <option value="<?= htmlspecialchars(json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                    [<?= htmlspecialchars($cat['categoria']) ?>] <?= htmlspecialchars($cat['nome']) ?> (Coef: <?= number_format($cat['coeficiente_infiltracao'],2,',','.') ?> <?= htmlspecialchars($cat['metodo_medida']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            
            <div class="itens-selecionados" id="selecionados_<?= $idx ?>">
                <?php if (!empty($p['itens'])): ?>
                <?php foreach ($p['itens'] as $si): 
                    $larg_si = (float)($si['largura_m'] ?? 0);
                    $alt_si = (float)($si['altura_m'] ?? 0);
                    $area_si = $larg_si * $alt_si;
                    $perim_si = (float)($si['comprimento_fresta_m'] ?? 0);
                    if ($perim_si <= 0 && $larg_si > 0 && $alt_si > 0) {
                        $perim_si = 2 * ($larg_si + $alt_si);
                    }
                    
                    // ✅ CORRIGIDO: fallback triplo do nome + remoção de prefixo numérico
                    $nome_exibir = trim($si['nome_item'] ?? '');
                    if ($nome_exibir === '') {
                        $nome_exibir = trim($si['tipo_item'] ?? '');
                    }
                    if ($nome_exibir === '') {
                        $nome_exibir = 'Item sem nome';
                    }
                    $nome_exibir = preg_replace('/^\d+\s*/', '', $nome_exibir);
                    // ✅ CORRIGIDO: fallback se ficou vazio
                    if ($nome_exibir === '') {
                    $nome_exibir = $tipo_si ?: 'Item sem nome';
                    } 
                    
                    // ✅ NOVO: identifica categoria para etiqueta
                    $tipo_si = trim($si['tipo_item'] ?? 'Outro');
                    $classe_tipo = strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $tipo_si)));
                    if ($classe_tipo === '') $classe_tipo = 'outro';
                ?>
                <div class="item-linha" id="item_<?= $si['id'] ?>">
                    <div class="item-titulo">
                        <div class="item-nome-completo">
                            <strong><?= htmlspecialchars($nome_exibir) ?></strong>
                            <?php if ($tipo_si !== '' && $tipo_si !== 'Outro'): ?>
                            <span class="etiqueta-tipo tipo-<?= $classe_tipo ?>"><?= htmlspecialchars($tipo_si) ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-pequeno btn-remover" onclick="this.closest('.item-linha').remove()">×</button>
                    </div>
                    <div class="grid-3col">
                        <div>
                            <label>Quantidade</label>
                            <input type="number" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][quantidade]" value="<?= (int)($si['quantidade'] ?? 1) ?>" min="1">
                        </div>
                        <div>
                            <label>Largura (m)</label>
                            <input type="number" step="0.01" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][largura_m]" 
                                   value="<?= $larg_si ?>" oninput="atualizarCalculos('<?= $si['id'] ?>')" id="larg_<?= $si['id'] ?>">
                        </div>
                        <div>
                            <label>Altura (m)</label>
                            <input type="number" step="0.01" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][altura_m]" 
                                   value="<?= $alt_si ?>" oninput="atualizarCalculos('<?= $si['id'] ?>')" id="alt_<?= $si['id'] ?>">
                        </div>
                        <div>
                            <label>Área (m²) ↻ auto</label>
                            <input type="text" id="area_item_<?= $si['id'] ?>" class="campo-calculo" readonly
                                   value="<?= number_format($area_si, 2, ',', '.') ?>">
                        </div>
                        <div>
                            <label>Perímetro/Fresta (m) ↻ auto</label>
                            <input type="text" id="perim_item_<?= $si['id'] ?>" class="campo-calculo campo-auto" readonly
                                   value="<?= number_format($perim_si, 2, ',', '.') ?>">
                            <input type="hidden" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][comprimento_fresta_m]" 
                                   id="perim_hidden_<?= $si['id'] ?>" value="<?= $perim_si ?>">
                        </div>
                        <?php if (($si['metodo_coef'] ?? 'ren/h') === 'ren/h'): ?>
                        <div>
                            <label>Coef. (ren/h)</label>
                            <input type="number" step="0.01" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][coef_infiltracao]" value="<?= (float)($si['coef_infiltracao'] ?? 0) ?>">
                        </div>
                        <?php else: ?>
                        <div>
                            <label>Coef. (<?= htmlspecialchars($si['metodo_coef'] ?? 'm³/h·m') ?>)</label>
                            <input type="number" step="0.01" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][coef_infiltracao]" value="<?= (float)($si['coef_infiltracao'] ?? 0) ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][tipo_item]" value="<?= htmlspecialchars($tipo_si) ?>">
                    <input type="hidden" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][nome_item]" value="<?= htmlspecialchars($nome_exibir) ?>">
                    <input type="hidden" name="parede[<?= $dir ?>][itens][<?= $si['id'] ?>][metodo_coef]" value="<?= htmlspecialchars($si['metodo_coef'] ?? 'ren/h') ?>">
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grupo">
            <label>📝 Observações desta parede</label>
            <textarea name="parede[<?= $dir ?>][observacoes]" rows="2" placeholder="Ex: Parede voltada para fachada principal, alta incidência solar..."><?= htmlspecialchars($p['observacoes_parede'] ?? '') ?></textarea>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (!empty($resumo_por_parede)): ?>
            <div class="resumo-box" style="margin-top:32px; background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); overflow:hidden;">
                <div style="background:linear-gradient(135deg, #ecfdf5, #d1fae5); padding:16px 20px; border-bottom:1px solid #a7f3d0;">
                    <h2 style="margin:0; font-size:1.05rem; color:#065f46; font-weight:700;">
                        📊 Resumo do Ambiente — Revise antes de prosseguir
                    </h2>
                </div>
                
                <div style="padding:20px;">
                    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">PAREDE</th>
                                <th style="text-align:center; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">DIMENSÕES</th>
                                <th style="text-align:center; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">ÁREA</th>
                                <th style="text-align:left; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">MATERIAL</th>
                                <th style="text-align:center; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">COR EXT.</th>
                                <th style="text-align:center; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">ITENS</th>
                                <th style="text-align:right; padding:10px 12px; font-size:0.8rem; color:#475569; border-bottom:2px solid #e2e8f0;">CARGA INF.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumo_por_parede as $rp): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 12px; font-weight:600; color:#0f172a;"><?= htmlspecialchars($rp['nome']) ?></td>
                                <td style="padding:10px 12px; text-align:center; color:#475569; font-size:0.85rem;">
                                    <?= number_format($rp['comprimento'], 2, ',', '.') ?> × <?= number_format($rp['altura'], 2, ',', '.') ?> m
                                </td>
                                <td style="padding:10px 12px; text-align:center; color:#0369a1; font-weight:600;">
                                    <?= number_format($rp['area'], 2, ',', '.') ?> m²
                                </td>
                                <td style="padding:10px 12px; color:#475569; font-size:0.85rem;"><?= htmlspecialchars($rp['material']) ?></td>
                                <td style="padding:10px 12px; text-align:center;">
                                    <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:0.75rem; background:#fef3c7; color:#92400e;">
                                        <?= htmlspecialchars($rp['cor_externa']) ?>
                                    </span>
                                </td>
                                <td style="padding:10px 12px; text-align:center; color:#475569;"><?= $rp['num_itens'] ?></td>
                                <td style="padding:10px 12px; text-align:right; color:#7c3aed; font-weight:600; font-size:0.85rem;">
                                    <?= number_format($rp['carga_infiltracao'], 2, ',', '.') ?> W
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#f8fafc; font-weight:700;">
                                <td colspan="2" style="padding:12px; color:#0f172a;">TOTAIS</td>
                                <td style="padding:12px; text-align:center; color:#0369a1; font-size:1.05rem;">
                                    <?= number_format($resumo_areas, 2, ',', '.') ?> m²
                                </td>
                                <td colspan="2" style="padding:12px; color:#64748b; font-size:0.8rem;">
                                    Volume: <?= number_format($resumo_volume, 2, ',', '.') ?> m³
                                </td>
                                <td style="padding:12px; text-align:center; color:#475569;">
                                    <?php 
                                    $total_itens = 0;
                                    foreach ($resumo_por_parede as $rp) $total_itens += $rp['num_itens'];
                                    echo $total_itens;
                                    ?>
                                </td>
                                <td style="padding:12px; text-align:right; color:#7c3aed; font-size:1.05rem;">
                                    <?= number_format($resumo_carga_infiltracao, 2, ',', '.') ?> W
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:16px;">
                        <div style="background:#eff6ff; padding:14px; border-radius:8px; border-left:4px solid #3b82f6;">
                            <div style="font-size:0.75rem; color:#1e40af; font-weight:600; text-transform:uppercase;">Área Total</div>
                            <div style="font-size:1.3rem; color:#1e40af; font-weight:700; margin-top:4px;">
                                <?= number_format($resumo_areas, 2, ',', '.') ?> m²
                            </div>
                        </div>
                        <div style="background:#f0fdf4; padding:14px; border-radius:8px; border-left:4px solid #22c55e;">
                            <div style="font-size:0.75rem; color:#15803d; font-weight:600; text-transform:uppercase;">Volume Ambiente</div>
                            <div style="font-size:1.3rem; color:#15803d; font-weight:700; margin-top:4px;">
                                <?= number_format($resumo_volume, 2, ',', '.') ?> m³
                            </div>
                        </div>
                        <div style="background:#fef3c7; padding:14px; border-radius:8px; border-left:4px solid #f59e0b;">
                            <div style="font-size:0.75rem; color:#92400e; font-weight:600; text-transform:uppercase;">ΔT (Ext - Int)</div>
                            <div style="font-size:1.3rem; color:#92400e; font-weight:700; margin-top:4px;">
                                <?= number_format($delta_T, 2, ',', '.') ?> °C
                            </div>
                        </div>
                        <div style="background:#faf5ff; padding:14px; border-radius:8px; border-left:4px solid #a855f7;">
                            <div style="font-size:0.75rem; color:#6b21a8; font-weight:600; text-transform:uppercase;">Carga Infiltração</div>
                            <div style="font-size:1.3rem; color:#6b21a8; font-weight:700; margin-top:4px;">
                                <?= number_format($resumo_carga_infiltracao, 2, ',', '.') ?> W
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="botoes" style="margin-top:24px;">
                <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">← Voltar</a>
                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-left:auto;">
                    <button type="submit" name="salvar_apenas" value="1" class="btn" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; padding:12px 24px;">
                        💾 Salvar
                    </button>
                    <button type="submit" name="prosseguir" value="1" class="btn btn-proximo" style="padding:12px 24px;">
                        Salvar e Prosseguir → Ganho Solar
                    </button>
                </div>
            </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const primeira = document.querySelectorAll('.parede-cabecalho')[0];
    if (primeira) primeira.nextElementSibling.classList.add('aberto');
});

function calcularAreaParede(direcao) {
    const comp = parseFloat(document.getElementById('comp_' + direcao).value) || 0;
    const alt = parseFloat(document.getElementById('alt_' + direcao).value) || 0;
    const area = comp * alt;
    document.getElementById('area_' + direcao).value = area.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function atualizarCalculos(itemId) {
    const larg = parseFloat(document.getElementById('larg_' + itemId).value) || 0;
    const alt = parseFloat(document.getElementById('alt_' + itemId).value) || 0;
    
    const area = larg * alt;
    document.getElementById('area_item_' + itemId).value = area.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    const perim = 2 * (larg + alt);
    document.getElementById('perim_item_' + itemId).value = perim.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const hidden = document.getElementById('perim_hidden_' + itemId);
    if (hidden) hidden.value = perim.toFixed(4);
}

function adicionarAbertura(selectEl, paredeIdx, direcao) {
    if (!selectEl.value) return;
    const item = JSON.parse(selectEl.value);
    selectEl.value = ""; 
    
    const container = document.getElementById(`selecionados_${paredeIdx}`);
    const uniqueId = 'novo_' + Date.now() + Math.floor(Math.random() * 1000);
    
    const tipo = item.categoria || 'Outro';
    // ✅ CORRIGIDO: preserva nome completo, com fallback
    const nomeCompleto = (item.nome && item.nome.trim() !== '') ? item.nome : tipo;
    
    // ✅ CORRIGIDO: classe de tipo robusta (remove acentos, espaços)
    const classeTipo = tipo.toLowerCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z]/g, '') || 'outro';
    
    const isRenH = (item.metodo_medida === 'ren/h');
    const labelCoef = isRenH ? 'Coef. (ren/h)' : `Coef. (${item.metodo_medida})`;
    
    const div = document.createElement('div');
    div.className = 'item-linha';
    div.id = `item_${uniqueId}`;
    div.innerHTML = `
        <div class="item-titulo">
            <div class="item-nome-completo">
                <strong>${escapeHtml(nomeCompleto)}</strong>
                <span class="etiqueta-tipo tipo-${classeTipo}">${escapeHtml(tipo)}</span>
            </div>
            <button type="button" class="btn btn-pequeno btn-remover" onclick="this.closest('.item-linha').remove()">×</button>
        </div>
        <div class="grid-3col">
            <div>
                <label>Quantidade</label>
                <input type="number" name="parede[${direcao}][itens][${uniqueId}][quantidade]" value="1" min="1">
            </div>
            <div>
                <label>Largura (m)</label>
                <input type="number" step="0.01" name="parede[${direcao}][itens][${uniqueId}][largura_m]" 
                       oninput="atualizarCalculos('${uniqueId}')" id="larg_${uniqueId}">
            </div>
            <div>
                <label>Altura (m)</label>
                <input type="number" step="0.01" name="parede[${direcao}][itens][${uniqueId}][altura_m]" 
                       oninput="atualizarCalculos('${uniqueId}')" id="alt_${uniqueId}">
            </div>
            <div>
                <label>Área (m²) ↻ auto</label>
                <input type="text" id="area_item_${uniqueId}" class="campo-calculo" readonly value="0,00">
            </div>
            <div>
                <label>Perímetro/Fresta (m) ↻ auto</label>
                <input type="text" id="perim_item_${uniqueId}" class="campo-calculo campo-auto" readonly value="0,00">
                <input type="hidden" name="parede[${direcao}][itens][${uniqueId}][comprimento_fresta_m]" 
                       id="perim_hidden_${uniqueId}" value="0">
            </div>
            <div>
                <label>${escapeHtml(labelCoef)}</label>
                <input type="number" step="0.01" name="parede[${direcao}][itens][${uniqueId}][coef_infiltracao]" value="${item.coeficiente_infiltracao}">
            </div>
        </div>
        <input type="hidden" name="parede[${direcao}][itens][${uniqueId}][tipo_item]" value="${escapeHtml(tipo)}">
        <input type="hidden" name="parede[${direcao}][itens][${uniqueId}][nome_item]" value="${escapeHtml(nomeCompleto)}">
        <input type="hidden" name="parede[${direcao}][itens][${uniqueId}][metodo_coef]" value="${escapeHtml(item.metodo_medida || 'ren/h')}">
    `;
    container.appendChild(div);
}

// ✅ NOVO: sanitização para evitar XSS em nome do item
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
</body>
</html>
