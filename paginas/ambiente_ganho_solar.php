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

// ============================================================
// CONSTANTES TÉCNICAS
// ============================================================

// Irradiação solar de pico (Brasil, latitude ~23°S, verão)
$IRRADIACAO_SOLAR = [
    'Norte'    => 550,
    'Nordeste' => 520,
    'Leste'    => 600,
    'Sudeste'  => 400,
    'Sul'      => 200,
    'Sudoeste' => 500,
    'Oeste'    => 700,
    'Noroeste' => 620,
];

// Propriedades térmicas por tipo de vidro
$TIPO_VIDRO = [
    'comum'     => ['nome' => 'Comum 3-6mm', 'U' => 5.8, 'tau' => 0.85, 'shgc' => 0.86],
    'duplo'     => ['nome' => 'Duplo',        'U' => 2.8, 'tau' => 0.70, 'shgc' => 0.70],
    'low_e'     => ['nome' => 'Low-E',        'U' => 1.5, 'tau' => 0.40, 'shgc' => 0.40],
    'temperado' => ['nome' => 'Temperado',    'U' => 5.5, 'tau' => 0.78, 'shgc' => 0.80],
    'laminado'  => ['nome' => 'Laminado',     'U' => 5.5, 'tau' => 0.75, 'shgc' => 0.75],
    'refletivo' => ['nome' => 'Refletivo',    'U' => 5.5, 'tau' => 0.35, 'shgc' => 0.40],
];

// Coeficientes U de materiais (fallback, se não vier do banco)
$COEF_U_MATERIAIS = [
    'Tijolo cerâmico 1/2 vez' => 2.40,
    'Tijolo cerâmico 1 vez'   => 1.70,
    'Bloco de concreto'       => 2.80,
    'Concreto maciço'         => 3.50,
    'Madeira'                 => 0.60,
    'Drywall'                 => 1.80,
    'Vidro'                   => 5.80,
    'Outro'                   => 2.00,
];

$delta_T = (float)val($proj, 'temp_externa_verao', 32.0) - (float)val($proj, 'temp_interna_verao', 24.0);
$mensagem_sucesso = '';

// ============================================================
// SALVAR POST (fator sombra + tipo vidro por janela)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['salvar_solar'])) {
    // Limpa configuração anterior
    $conn->query("DELETE FROM ganho_solar WHERE ambiente_id = $ambiente_id");
    
    if (!empty($_POST['item_id']) && is_array($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $idx => $item_id) {
            $item_id = (int)$item_id;
            $tipo_vidro = trim($_POST['tipo_vidro'][$idx] ?? 'comum');
            $fator_sombra = (float)($_POST['fator_sombra'][$idx] ?? 1.0);
            
            // Verifica se é um tipo de vidro válido
            if (!isset($TIPO_VIDRO[$tipo_vidro])) $tipo_vidro = 'comum';
            $transmissividade = $TIPO_VIDRO[$tipo_vidro]['tau'];
            
            $stmt = $conn->prepare("INSERT INTO ganho_solar 
                (ambiente_id, item_id, orientacao, area_vidro_m2, fator_sombra, transmissividade, tipo_vidro) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            // Pega orientação e área do item
            $item_row = $conn->query("
                SELECT ip.*, pa.nome AS orientacao 
                FROM itens_parede ip 
                JOIN paredes_ambiente pa ON pa.id = ip.parede_id 
                WHERE ip.id = $item_id
            ")->fetch_assoc();
            
            $orientacao = $item_row['orientacao'] ?? 'Norte';
            $area = ($item_row['largura_m'] ?? 0) * ($item_row['altura_m'] ?? 0);
            
            $stmt->bind_param("iisddds", 
                $ambiente_id, 
                $item_id, 
                $orientacao, 
                $area, 
                $fator_sombra, 
                $transmissividade, 
                $tipo_vidro
            );
            
            if (!$stmt->execute()) {
                error_log("Erro INSERT ganho_solar: " . $stmt->error);
            }
        }
    }
    
    if (empty($_POST['salvar_apenas'])) {
        header("Location: ambiente_cargas_internas.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
        exit;
    } else {
        $mensagem_sucesso = "Ganho solar calculado e salvo com sucesso!";
    }
}

// ============================================================
// CARREGAR CONFIGURAÇÕES JÁ SALVAS (se existirem)
// ============================================================
$config_por_item = [];
$res_gs = $conn->query("SELECT * FROM ganho_solar WHERE ambiente_id = $ambiente_id");
while ($gs = $res_gs->fetch_assoc()) {
    $config_por_item[(int)$gs['item_id']] = $gs;
}

// ============================================================
// HERDAR DADOS DA ETAPA 2 — PAREDES EXTERNAS
// ============================================================
$paredes = [];
$res_p = $conn->query("SELECT * FROM paredes_ambiente WHERE ambiente_id = $ambiente_id ORDER BY id");
while ($p = $res_p->fetch_assoc()) {
    $comp = (float)($p['comprimento_m'] ?? 0);
    $alt = (float)($p['altura_m'] ?? 0);
    if ($comp <= 0 || $alt <= 0) continue;
    
    $area = $comp * $alt;
    $U = $COEF_U_MATERIAIS[$p['material']] ?? 2.0;
    $alpha = (float)($p['coef_absorcao_solar'] ?? 0.30);
    $I = $IRRADIACAO_SOLAR[$p['nome']] ?? 400;
    $F_sombra = 0.85;
    
    $q_cond_parede = $U * $area * $delta_T;
    $q_rad_parede = $alpha * $I * $area * $F_sombra;
    
    $paredes[] = [
        'id'         => $p['id'],
        'nome'       => $p['nome'],
        'area'       => $area,
        'comprimento'=> $comp,
        'altura'     => $alt,
        'material'   => $p['material'],
        'cor_externa'=> $p['cor_externa'] ?? 'Clara',
        'U'          => $U,
        'alpha'      => $alpha,
        'I'          => $I,
        'F_sombra'   => $F_sombra,
        'q_conducao' => $q_cond_parede,
        'q_radiacao' => $q_rad_parede,
        'q_total'    => $q_cond_parede + $q_rad_parede,
    ];
}

// ============================================================
// HERDAR DADOS DA ETAPA 2 — JANELAS E ABERTURAS
// ============================================================
$janelas = [];
$res_i = $conn->query("
    SELECT ip.*, pa.nome AS orientacao_parede, pa.id AS parede_id
    FROM itens_parede ip
    JOIN paredes_ambiente pa ON pa.id = ip.parede_id
    WHERE pa.ambiente_id = $ambiente_id
    ORDER BY pa.nome, ip.id
");
while ($it = $res_i->fetch_assoc()) {
    $larg = (float)($it['largura_m'] ?? 0);
    $alt = (float)($it['altura_m'] ?? 0);
    $area = $larg * $alt;
    if ($area <= 0) continue;
    
    $orientacao = $it['orientacao_parede'] ?? 'Norte';
    $I = $IRRADIACAO_SOLAR[$orientacao] ?? 400;
    
    // Verifica se há configuração salva
    $cfg = $config_por_item[(int)$it['id']] ?? null;
    $tipo_vidro = $cfg['tipo_vidro'] ?? 'comum';
    $fator_sombra = (float)($cfg['fator_sombra'] ?? 1.0);
    
    if (!isset($TIPO_VIDRO[$tipo_vidro])) $tipo_vidro = 'comum';
    $tau = $TIPO_VIDRO[$tipo_vidro]['tau'];
    $U_vidro = $TIPO_VIDRO[$tipo_vidro]['U'];
    
    $q_cond = $U_vidro * $area * $delta_T;
    $q_rad = $tau * $I * $area * $fator_sombra;
    $q_inf = (float)($it['carga_infiltracao_w'] ?? 0);
    
    $janelas[] = [
        'id'             => $it['id'],
        'nome'           => $it['nome_item'],
        'tipo'           => $it['tipo_item'],
        'parede_id'      => $it['parede_id'],
        'orientacao'     => $orientacao,
        'area'           => $area,
        'largura'        => $larg,
        'altura'         => $alt,
        'tipo_vidro'     => $tipo_vidro,
        'fator_sombra'   => $fator_sombra,
        'tau'            => $tau,
        'U'              => $U_vidro,
        'I'              => $I,
        'q_conducao'     => $q_cond,
        'q_radiacao'     => $q_rad,
        'q_infiltracao'  => $q_inf,
        'q_total'        => $q_cond + $q_rad + $q_inf,
    ];
}

// ============================================================
// TOTAIS
// ============================================================
$Q_paredes_solar = 0;
foreach ($paredes as $p) $Q_paredes_solar += $p['q_total'];

$Q_janelas_cond = 0;
$Q_janelas_rad = 0;
$Q_janelas_inf = 0;
foreach ($janelas as $j) {
    $Q_janelas_cond += $j['q_conducao'];
    $Q_janelas_rad  += $j['q_radiacao'];
    $Q_janelas_inf  += $j['q_infiltracao'];
}
$Q_janelas_solar = $Q_janelas_cond + $Q_janelas_rad + $Q_janelas_inf;
$Q_total_solar = $Q_paredes_solar + $Q_janelas_solar;

// Agrupamento por orientação
$por_orientacao = [];
foreach (['Norte','Nordeste','Leste','Sudeste','Sul','Sudoeste','Oeste','Noroeste'] as $o) {
    $por_orientacao[$o] = ['paredes' => 0, 'janelas' => 0, 'total' => 0];
}
foreach ($paredes as $p) {
    $por_orientacao[$p['nome']]['paredes'] += $p['q_total'];
    $por_orientacao[$p['nome']]['total'] += $p['q_total'];
}
foreach ($janelas as $j) {
    $por_orientacao[$j['orientacao']]['janelas'] += $j['q_total'];
    $por_orientacao[$j['orientacao']]['total'] += $j['q_total'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 3 — Ganho Solar</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1200px; margin:0 auto; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { flex:1; text-align:center; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#f59e0b; border-bottom:3px solid #f59e0b; font-weight:700; }
.passo-link.concluido { color:#2563eb; border-bottom:2px solid #bfdbfe; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.3rem; color:#1e293b; margin-bottom:5px; }
h2 { font-size:1.1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.subtitulo { color:#64748b; margin-bottom:12px; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
table { width:100%; border-collapse:collapse; margin-top:8px; }
th { background:#f1f5f9; padding:10px 12px; text-align:left; font-weight:700; color:#475569; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.3px; }
td { padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:0.9rem; color:#334155; }
tr:hover { background:#f8fafc; }
tfoot td { background:#f1f5f9; font-weight:700; color:#0f172a; }
input, select { padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 2px rgba(245,158,11,0.15); background:#fff; }
.btn { padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; gap:6px; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-proximo { background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; }
.btn-salvar { background:linear-gradient(135deg, #10b981, #059669); color:#fff; }
.botoes { display:flex; gap:12px; margin-top:24px; justify-content:space-between; padding-top:20px; border-top:2px solid #e2e8f0; flex-wrap:wrap; }
.dica { background:#fffbeb; border-left:3px solid #f59e0b; padding:12px 16px; margin:16px 0; font-size:0.85rem; color:#78350f; border-radius:0 6px 6px 0; }
.aviso-sucesso { background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#065f46; font-weight:600; }
.resumo-box { background:linear-gradient(135deg, #fef3c7, #fde68a); border-radius:12px; padding:24px; margin:24px 0; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-top:16px; }
.card { background:#fff; padding:16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.card-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.card-valor { font-size:1.5rem; font-weight:800; }
.card-sub { font-size:0.75rem; color:#64748b; margin-top:4px; }
.badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:700; }
.badge-n { background:#dbeafe; color:#1e40af; }
.badge-s { background:#fee2e2; color:#991b1b; }
.badge-l { background:#fef3c7; color:#92400e; }
.badge-o { background:#fce7f3; color:#9d174d; }
</style>
</head>
<body>

<div class="barra-progresso">
    <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 1. Dados</a>
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 2. Paredes</a>
    <a class="passo-link ativo">3. Solar</a>
    <a class="passo-link">4. Internas</a>
    <a class="passo-link">5. Ar</a>
    <a class="passo-link">6. Resultado</a>
</div>

<div class="cabecalho">
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" style="color:#f59e0b; text-decoration:none; font-weight:600; font-size:0.9rem;">← Voltar ao Passo 2</a>
    
    <?php if (!empty($mensagem_sucesso)): ?>
    <div class="aviso-sucesso" style="margin-top:16px;">
        ✅ <?= htmlspecialchars($mensagem_sucesso) ?>
    </div>
    <?php endif; ?>
    
    <h1 style="margin-top:16px;">☀️ Ganho de Calor por Radiação Solar</h1>
    <p class="subtitulo">
        Ambiente: <strong><?= htmlspecialchars(val($amb, 'nome')) ?></strong>
        · ΔT = <?= number_format($delta_T, 1, ',', '.') ?>°C
        (<?= number_format((float)val($proj, 'temp_externa_verao', 32.0), 1, ',', '.') ?>°C → <?= number_format((float)val($proj, 'temp_interna_verao', 24.0), 1, ',', '.') ?>°C)
    </p>
</div>

<div class="dica">
    💡 <strong>Radiação de pico (Brasil — latitude ~23°S, verão):</strong>
    Norte 550 · Nordeste 520 · Leste 600 · Sudeste 400 · Sul 200 · Sudoeste 500 · Oeste 700 · Noroeste 620 W/m²
    <br>
    <strong>Fator de sombra:</strong> sem sombra = 1.0 · parcial = 0.6 · total = 0.2
</div>

<!-- ============================================================ -->
<!-- PAREDES EXTERNAS (HERDADAS) -->
<!-- ============================================================ -->
<?php if (!empty($paredes)): ?>
<div class="form-card">
    <h2>🧱 Paredes Externas <span style="font-weight:400; color:#64748b; font-size:0.85rem;">— herdadas da Etapa 2</span></h2>
    
    <table>
        <thead>
            <tr>
                <th>Orientação</th>
                <th>Área</th>
                <th>Material</th>
                <th>U</th>
                <th>α</th>
                <th>I (W/m²)</th>
                <th>Q_cond (W)</th>
                <th>Q_rad (W)</th>
                <th style="text-align:right;">Q_total (W)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paredes as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                <td><?= number_format($p['area'], 2, ',', '.') ?> m²</td>
                <td style="font-size:0.85rem;"><?= htmlspecialchars($p['material']) ?></td>
                <td><?= number_format($p['U'], 2, ',', '.') ?></td>
                <td><?= number_format($p['alpha'], 2, ',', '.') ?></td>
                <td><?= $p['I'] ?></td>
                <td><?= number_format($p['q_conducao'], 1, ',', '.') ?></td>
                <td><?= number_format($p['q_radiacao'], 1, ',', '.') ?></td>
                <td style="text-align:right; color:#0369a1; font-weight:700;"><?= number_format($p['q_total'], 1, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align:right;">TOTAL PAREDES:</td>
                <td style="text-align:right; color:#0369a1; font-size:1.05rem;"><?= number_format($Q_paredes_solar, 1, ',', '.') ?> W</td>
            </tr>
        </tfoot>
    </table>
    
    <p style="color:#94a3b8; font-size:0.8rem; margin-top:12px;">
        ℹ️ Fator de sombra assumido: 0.85 (15% de sombreamento por beirais/paredes adjacentes)
    </p>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- JANELAS E ABERTURAS (HERDADAS + CONFIGURÁVEIS) -->
<!-- ============================================================ -->
<?php if (!empty($janelas)): ?>
<div class="form-card">
    <h2>🪟 Janelas e Aberturas <span style="font-weight:400; color:#64748b; font-size:0.85rem;">— herdadas da Etapa 2</span></h2>
    
    <form method="POST" id="formJanela">
        <input type="hidden" name="salvar_solar" value="1">
        
        <table>
            <thead>
                <tr>
                    <th>Abertura</th>
                    <th>Orientação</th>
                    <th>Área</th>
                    <th>Tipo de Vidro</th>
                    <th>Fator Sombra</th>
                    <th>Q_cond</th>
                    <th>Q_rad</th>
                    <th>Q_inf</th>
                    <th style="text-align:right;">Q_total (W)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($janelas as $idx => $j): ?>
                <tr>
                    <td>
                        <input type="hidden" name="item_id[<?= $idx ?>]" value="<?= $j['id'] ?>">
                        <strong style="font-size:0.85rem;"><?= htmlspecialchars($j['nome']) ?></strong><br>
                        <small style="color:#94a3b8;"><?= htmlspecialchars($j['tipo']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower(substr($j['orientacao'], 0, 1)) ?>">
                            <?= htmlspecialchars($j['orientacao']) ?>
                        </span>
                    </td>
                    <td><?= number_format($j['area'], 2, ',', '.') ?> m²</td>
                    <td>
                        <select name="tipo_vidro[<?= $idx ?>]">
                            <?php foreach ($TIPO_VIDRO as $key => $v): ?>
                            <option value="<?= $key ?>" <?= $j['tipo_vidro']===$key?'selected':'' ?>>
                                <?= $v['nome'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="fator_sombra[<?= $idx ?>]">
                            <option value="1.00" <?= $j['fator_sombra']==1.00?'selected':'' ?>>Sem (1.00)</option>
                            <option value="0.85" <?= $j['fator_sombra']==0.85?'selected':'' ?>>Pouca (0.85)</option>
                            <option value="0.60" <?= $j['fator_sombra']==0.60?'selected':'' ?>>Parcial (0.60)</option>
                            <option value="0.35" <?= $j['fator_sombra']==0.35?'selected':'' ?>>Beiral Gde (0.35)</option>
                            <option value="0.20" <?= $j['fator_sombra']==0.20?'selected':'' ?>>Total (0.20)</option>
                        </select>
                    </td>
                    <td><?= number_format($j['q_conducao'], 1, ',', '.') ?></td>
                    <td><?= number_format($j['q_radiacao'], 1, ',', '.') ?></td>
                    <td><?= number_format($j['q_infiltracao'], 1, ',', '.') ?></td>
                    <td style="text-align:right; color:#dc2626; font-weight:700;"><?= number_format($j['q_total'], 1, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right;">SUBTOTAIS:</td>
                    <td><?= number_format($Q_janelas_cond, 1, ',', '.') ?></td>
                    <td><?= number_format($Q_janelas_rad, 1, ',', '.') ?></td>
                    <td><?= number_format($Q_janelas_inf, 1, ',', '.') ?></td>
                    <td style="text-align:right; color:#dc2626; font-size:1.05rem;"><?= number_format($Q_janelas_solar, 1, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        
        <p style="color:#94a3b8; font-size:0.8rem; margin-top:12px;">
            ℹ️ <strong>Q_cond</strong> = condução térmica (U·A·ΔT) ·
            <strong>Q_rad</strong> = radiação solar (τ·I·A·F) ·
            <strong>Q_inf</strong> = infiltração (herdada da Etapa 2)
        </p>
    </form>
</div>
<?php else: ?>
<div class="form-card" style="text-align:center; color:#64748b; padding:40px;">
    <p>🪟 Nenhuma janela ou abertura cadastrada.</p>
    <p style="font-size:0.85rem; margin-top:8px;">
        <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" style="color:#f59e0b;">← Voltar ao Passo 2 para adicionar</a>
    </p>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- RESUMO POR ORIENTAÇÃO -->
<!-- ============================================================ -->
<?php if ($Q_total_solar > 0): ?>
<div class="form-card">
    <h2>📍 Distribuição por Orientação</h2>
    <table>
        <thead>
            <tr>
                <th>Orientação</th>
                <th>Paredes (W)</th>
                <th>Janelas (W)</th>
                <th style="text-align:right;">Total (W)</th>
                <th style="text-align:right;">% do Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($por_orientacao as $o => $v): ?>
                <?php if ($v['total'] > 0): ?>
                <tr>
                    <td><strong><?= $o ?></strong></td>
                    <td><?= number_format($v['paredes'], 1, ',', '.') ?></td>
                    <td><?= number_format($v['janelas'], 1, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:700; color:#0369a1;"><?= number_format($v['total'], 1, ',', '.') ?></td>
                    <td style="text-align:right; color:#64748b;"><?= number_format($v['total'] / $Q_total_solar * 100, 1, ',', '.') ?>%</td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- RESUMO GERAL -->
<!-- ============================================================ -->
<div class="resumo-box">
    <h2 style="border:none; margin-bottom:16px; color:#78350f;">📊 Resumo do Ganho Solar</h2>
    
    <div class="card-grid">
        <div class="card" style="border-left:4px solid #3b82f6;">
            <div class="card-label" style="color:#1e40af;">Q PAREDES</div>
            <div class="card-valor" style="color:#1e40af;">
                <?= number_format($Q_paredes_solar, 0, ',', '.') ?> W
            </div>
            <div class="card-sub">condução + radiação</div>
        </div>
        
        <div class="card" style="border-left:4px solid #dc2626;">
            <div class="card-label" style="color:#991b1b;">Q JANELAS</div>
            <div class="card-valor" style="color:#991b1b;">
                <?= number_format($Q_janelas_solar, 0, ',', '.') ?> W
            </div>
            <div class="card-sub">
                cond: <?= number_format($Q_janelas_cond, 0, ',', '.') ?> ·
                rad: <?= number_format($Q_janelas_rad, 0, ',', '.') ?> ·
                inf: <?= number_format($Q_janelas_inf, 0, ',', '.') ?>
            </div>
        </div>
        
        <div class="card" style="border-left:4px solid #10b981;">
            <div class="card-label" style="color:#065f46;">Q TOTAL SOLAR</div>
            <div class="card-valor" style="color:#065f46;">
                <?= number_format($Q_total_solar, 0, ',', '.') ?> W
            </div>
            <div class="card-sub">
                <?= number_format($Q_total_solar / 3517, 2, ',', '.') ?> TR ·
                <?= number_format($Q_total_solar * 3.412, 0, ',', '.') ?> BTU/h
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- BOTÕES -->
<!-- ============================================================ -->
<div class="botoes">
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">
        ← Voltar ao Passo 2
    </a>
    <div style="display:flex; gap:12px; margin-left:auto; flex-wrap:wrap;">
        <button type="submit" form="formJanela" name="salvar_apenas" value="1" class="btn btn-salvar">
            💾 Salvar
        </button>
        <button type="submit" form="formJanela" name="prosseguir" value="1" class="btn btn-proximo">
            Salvar e Prosseguir → Cargas Internas
        </button>
    </div>
</div>

</body>
</html>
