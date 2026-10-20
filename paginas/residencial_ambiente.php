<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
if (!$projeto_id) { header("Location: residencial_projetos.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
if (!$proj) { header("Location: residencial_projetos.php"); exit; }

$mensagem_sucesso = '';

// ============================================================
// CRIAR NOVO AMBIENTE
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['criar_ambiente'])) {
    $nome = trim($_POST['nome'] ?? '');
    $compr = (float)($_POST['comprimento_m'] ?? 0);
    $larg = (float)($_POST['largura_m'] ?? 0);
    $pe = (float)($_POST['peDireita_m'] ?? 2.80);
    $pessoas = (int)($_POST['pessoas'] ?? 1);
    
    $erros = [];
    if ($nome === '') $erros[] = 'Nome é obrigatório';
    if ($compr <= 0) $erros[] = 'Comprimento > 0';
    if ($larg <= 0) $erros[] = 'Largura > 0';
    if ($pe <= 0) $erros[] = 'Pé-direito > 0';
    
    if (empty($erros)) {
        $stmt = $conn->prepare("INSERT INTO ambientes 
            (projeto_id, nome, comprimento_m, largura_m, peDireita_m, pessoas) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddi", $projeto_id, $nome, $compr, $larg, $pe, $pessoas);
        if ($stmt->execute()) {
            $novo_id = $conn->insert_id;
            header("Location: ambiente_dados.php?projeto_id=$projeto_id&ambiente_id=$novo_id");
            exit;
        } else {
            $mensagem_erro = "Erro ao criar ambiente: " . $stmt->error;
        }
    } else {
        $mensagem_erro = implode(' · ', $erros);
    }
}

// ============================================================
// EXCLUIR AMBIENTE
// ============================================================
if (!empty($_GET['excluir'])) {
    $id_exc = (int)$_GET['excluir'];
    $conn->query("DELETE FROM ambientes WHERE id = $id_exc AND projeto_id = $projeto_id");
    header("Location: residencial_ambiente.php?projeto_id=$projeto_id&msg=excluido");
    exit;
}

if (!empty($_GET['msg']) && $_GET['msg'] === 'excluido') {
    $mensagem_sucesso = 'Ambiente excluído com sucesso!';
}

// ============================================================
// CARREGAR AMBIENTES COM STATUS DE CADA ETAPA
// ============================================================
$ambientes = [];

$res_amb = $conn->query("SELECT * FROM ambientes WHERE projeto_id = $projeto_id ORDER BY id");
while ($a = $res_amb->fetch_assoc()) {
    $aid = (int)$a['id'];
    
    // Etapa 1: Dados preenchidos
    $etapa1_ok = !empty($a['nome']) && (float)$a['comprimento_m'] > 0 && (float)$a['largura_m'] > 0;
    
    // Etapa 2: Existe pelo menos 1 parede
    $etapa2_ok = $conn->query("SELECT COUNT(*) AS n FROM paredes_ambiente WHERE ambiente_id = $aid")->fetch_assoc()['n'] > 0;
    
    // Etapa 3: Existe pelo menos 1 item em ganho_solar
    $etapa3_ok = $conn->query("SELECT COUNT(*) AS n FROM ganho_solar WHERE ambiente_id = $aid")->fetch_assoc()['n'] > 0;
    
    // Etapa 4: Existe registro em cargas_internas
    $etapa4_ok = $conn->query("SELECT COUNT(*) AS n FROM cargas_internas WHERE ambiente_id = $aid")->fetch_assoc()['n'] > 0;
    
    // Etapa 5: Existe resultado em resultados_calculo
    $res_calc = $conn->query("SELECT * FROM resultados_calculo WHERE ambiente_id = $aid ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $etapa5_ok = !empty($res_calc);
    
    // Etapa 6: mesmas condições da 5
    $etapa6_ok = $etapa5_ok;
    
    // Valores do resultado
    $carga_w = $res_calc ? (float)$res_calc['carga_total_w'] : 0;
    $carga_btu = $res_calc ? (float)$res_calc['carga_total_btu'] : 0;
    $equip = $res_calc ? ($res_calc['aparelho_recomendado'] ?? '') : '';
    
    $ambientes[] = [
        'dados' => $a,
        'etapa1' => $etapa1_ok,
        'etapa2' => $etapa2_ok,
        'etapa3' => $etapa3_ok,
        'etapa4' => $etapa4_ok,
        'etapa5' => $etapa5_ok,
        'etapa6' => $etapa6_ok,
        'carga_w' => $carga_w,
        'carga_btu' => $carga_btu,
        'equip' => $equip,
    ];
}

// Totais do projeto
$total_w = 0;
$total_btu = 0;
foreach ($ambientes as $a) {
    $total_w += $a['carga_w'];
    $total_btu += $a['carga_btu'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ambientes do Projeto</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1100px; margin:0 auto; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.4rem; color:#1e293b; margin-bottom:8px; }
h2 { font-size:1.1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.subtitulo { color:#64748b; margin-bottom:16px; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
.grid-3col { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; }
.grupo { margin-bottom:12px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:4px; font-size:0.8rem; }
input, select { padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.9rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,0.12); background:#fff; }
.btn { padding:10px 20px; border-radius:8px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-criar { background:linear-gradient(135deg, #10b981, #059669); color:#fff; }
.btn-abrir { background:linear-gradient(135deg, #0ea5e9, #0284c7); color:#fff; font-size:0.85rem; padding:8px 14px; }
.btn-excluir { background:#fee2e2; color:#991b1b; padding:6px 10px; font-size:0.8rem; }
.aviso-sucesso { background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#065f46; font-weight:600; }
.aviso-erro { background:#fee2e2; border-left:4px solid #ef4444; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#991b1b; font-weight:600; }
.ambiente-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; border-left:4px solid #0ea5e9; }
.ambiente-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px; }
.ambiente-nome { font-size:1.2rem; font-weight:800; color:#0f172a; }
.ambiente-meta { color:#64748b; font-size:0.85rem; margin-top:4px; }
.etapas-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin:16px 0; }
.etapa-item { padding:10px 12px; border-radius:8px; font-size:0.8rem; font-weight:600; display:flex; align-items:center; gap:8px; text-decoration:none; transition:transform 0.15s; }
.etapa-item:hover { transform:translateY(-2px); }
.etapa-ok { background:#d1fae5; color:#065f46; border-left:3px solid #10b981; }
.etapa-pendente { background:#fef3c7; color:#92400e; border-left:3px solid #f59e0b; }
.etapa-ok::before { content:"✅ "; }
.etapa-pendente::before { content:"⬜ "; }
.resultado-box { background:linear-gradient(135deg, #ecfdf5, #d1fae5); padding:16px; border-radius:10px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:12px; }
.resultado-valores { display:flex; gap:24px; flex-wrap:wrap; }
.resultado-item { text-align:center; }
.resultado-label { font-size:0.7rem; color:#047857; font-weight:700; text-transform:uppercase; }
.resultado-valor { font-size:1.2rem; color:#065f46; font-weight:800; }
.total-box { background:linear-gradient(135deg, #1e40af, #1e3a8a); color:#fff; padding:24px; border-radius:12px; display:flex; justify-content:space-around; text-align:center; margin-top:20px; flex-wrap:wrap; gap:16px; }
.total-item .label { font-size:0.75rem; opacity:0.9; text-transform:uppercase; letter-spacing:1px; }
.total-item .valor { font-size:1.8rem; font-weight:900; margin-top:6px; }
.acoes { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<div class="cabecalho">
    <a href="residencial_projetos.php" style="color:#0ea5e9; text-decoration:none; font-size:0.9rem; font-weight:600;">← Voltar para Projetos</a>
    <h1 style="margin-top:12px;">🏢 Projeto: <?= htmlspecialchars($proj['nome']) ?></h1>
    <p class="subtitulo">
        Cliente: <strong><?= htmlspecialchars($proj['nome_cliente'] ?? '—') ?></strong>
        · Data: <?= date('d/m/Y', strtotime($proj['data_criacao'] ?? 'now')) ?>
    </p>
</div>

<?php if (!empty($mensagem_sucesso)): ?>
<div class="aviso-sucesso">✅ <?= htmlspecialchars($mensagem_sucesso) ?></div>
<?php endif; ?>

<?php if (!empty($mensagem_erro)): ?>
<div class="aviso-erro">⚠️ <?= htmlspecialchars($mensagem_erro) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2>➕ Adicionar Novo Ambiente</h2>
    <form method="POST">
        <input type="hidden" name="criar_ambiente" value="1">
        <div class="grupo">
            <label>Nome do Ambiente</label>
            <input type="text" name="nome" required placeholder="Ex: Quarto, Sala, Cozinha">
        </div>
        <div class="grid-3col">
            <div class="grupo">
                <label>Comprimento (m)</label>
                <input type="number" step="0.01" min="0.1" name="comprimento_m" value="3.00">
            </div>
            <div class="grupo">
                <label>Largura (m)</label>
                <input type="number" step="0.01" min="0.1" name="largura_m" value="3.00">
            </div>
            <div class="grupo">
                <label>Pé Direito (m)</label>
                <input type="number" step="0.01" min="0.1" name="peDireita_m" value="2.80">
            </div>
            <div class="grupo">
                <label>Ocupantes</label>
                <input type="number" min="0" name="pessoas" value="1">
            </div>
        </div>
        <button type="submit" class="btn btn-criar" style="margin-top:12px;">🏠 Criar Ambiente</button>
    </form>
</div>

<div class="form-card">
    <h2>📋 Ambientes do Projeto (<?= count($ambientes) ?>)</h2>
    
    <?php if (empty($ambientes)): ?>
        <p style="text-align:center; color:#94a3b8; padding:40px;">
            Nenhum ambiente cadastrado ainda. Crie o primeiro acima!
        </p>
    <?php else: ?>
        <?php foreach ($ambientes as $item): 
            $a = $item['dados'];
            $aid = (int)$a['id'];
        ?>
        <div class="ambiente-card">
            <div class="ambiente-header">
                <div>
                    <div class="ambiente-nome"><?= htmlspecialchars($a['nome']) ?></div>
                    <div class="ambiente-meta">
                        <?= number_format((float)$a['comprimento_m'], 2, ',', '.') ?> ×
                        <?= number_format((float)$a['largura_m'], 2, ',', '.') ?> ×
                        <?= number_format((float)$a['peDireita_m'], 2, ',', '.') ?> m
                        · <?= (int)$a['pessoas'] ?> pessoa(s)
                        · Uso: <?= htmlspecialchars($a['uso_principal'] ?? 'Dormitório') ?>
                    </div>
                </div>
                <a href="?projeto_id=<?= $projeto_id ?>&excluir=<?= $aid ?>" 
                   class="btn btn-excluir"
                   onclick="return confirm('Excluir ambiente <?= htmlspecialchars(addslashes($a['nome'])) ?>? Todos os dados serão perdidos.')">
                    🗑️ Excluir
                </a>
            </div>
            
            <div class="etapas-grid">
                <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa1'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    1. Dados
                </a>
                <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa2'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    2. Paredes
                </a>
                <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa3'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    3. Solar
                </a>
                <a href="ambiente_cargas_internas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa4'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    4. Cargas Internas
                </a>
                <a href="ambiente_dimensionamento.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa5'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    5. Dimensionamento
                </a>
                <a href="ambiente_resultado.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                   class="etapa-item <?= $item['etapa6'] ? 'etapa-ok' : 'etapa-pendente' ?>">
                    6. Resultado
                </a>
            </div>
            
            <?php if ($item['carga_btu'] > 0): ?>
            <div class="resultado-box">
                <div class="resultado-valores">
                    <div class="resultado-item">
                        <div class="resultado-label">Carga Térmica</div>
                        <div class="resultado-valor"><?= number_format($item['carga_w'], 0, ',', '.') ?> W</div>
                    </div>
                    <div class="resultado-item">
                        <div class="resultado-label">Em BTU/h</div>
                        <div class="resultado-valor"><?= number_format($item['carga_btu'], 0, ',', '.') ?></div>
                    </div>
                    <div class="resultado-item">
                        <div class="resultado-label">Equipamento</div>
                        <div class="resultado-valor" style="font-size:1rem;">
                            <?= htmlspecialchars($item['equip']) ?>
                        </div>
                    </div>
                </div>
                <div class="acoes">
                    <a href="ambiente_resultado.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                       class="btn btn-abrir">🏆 Ver Resultado</a>
                    <a href="ambiente_memoria_calculo.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $aid ?>" 
                       class="btn btn-abrir" style="background:linear-gradient(135deg, #8b5cf6, #7c3aed);">📐 Ver Memória</a>
                </div>
            </div>
            <?php else: ?>
            <div style="background:#fffbeb; padding:12px 16px; border-radius:8px; margin-top:12px; border-left:3px solid #f59e0b; font-size:0.85rem; color:#92400e;">
                ⚠️ Cálculo pendente. Complete todas as etapas para gerar o resultado.
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($total_btu > 0): ?>
<div class="total-box">
    <div class="total-item">
        <div class="label">Carga Total do Projeto</div>
        <div class="valor"><?= number_format($total_w, 0, ',', '.') ?> W</div>
    </div>
    <div class="total-item">
        <div class="label">Em BTU/h</div>
        <div class="valor"><?= number_format($total_btu, 0, ',', '.') ?> BTU/h</div>
    </div>
    <div class="total-item">
        <div class="label">Em TR</div>
        <div class="valor"><?= number_format($total_btu / 12000, 2, ',', '.') ?> TR</div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
