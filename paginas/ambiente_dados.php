<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);
if (!$projeto_id) { header("Location: residencial_ambiente.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
if (!$proj) { header("Location: residencial_ambiente.php"); exit; }

$amb = null;
if ($ambiente_id) {
    $amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();
}

$mensagem_sucesso = '';

// ============================================================
// SALVAR
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['salvar_ambiente'])) {
    $nome = trim($_POST['nome'] ?? '');
    $uso = trim($_POST['uso_principal'] ?? 'Dormitório');
    $compr = (float)($_POST['comprimento_m'] ?? 0);
    $larg = (float)($_POST['largura_m'] ?? 0);
    $pe = (float)($_POST['peDireita_m'] ?? 2.8);

    // Validação
    $erros = [];
    if ($nome === '') $erros[] = 'Nome é obrigatório';
    if ($compr <= 0) $erros[] = 'Comprimento deve ser maior que zero';
    if ($larg <= 0) $erros[] = 'Largura deve ser maior que zero';
    if ($pe <= 0) $erros[] = 'Pé-direito deve ser maior que zero';

    if (empty($erros)) {
        if ($ambiente_id) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE ambientes SET nome=?, uso_principal=?, comprimento_m=?, largura_m=?, peDireita_m=? WHERE id=? AND projeto_id=?");
            $stmt->bind_param("ssddiii", $nome, $uso, $compr, $larg, $pe, $ambiente_id, $projeto_id);
            $stmt->execute();
            $mensagem_sucesso = "Ambiente atualizado com sucesso!";
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO ambientes (projeto_id, nome, uso_principal, comprimento_m, largura_m, peDireita_m) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issddd", $projeto_id, $nome, $uso, $compr, $larg, $pe);
            $stmt->execute();
            $ambiente_id = $conn->insert_id;
            $mensagem_sucesso = "Ambiente criado com sucesso!";
        }

        // Recarregar
        $amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id")->fetch_assoc();

        // Se não for salvar_apenas, redireciona
        if (empty($_POST['salvar_apenas'])) {
            header("Location: ambiente_paredes.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
            exit;
        }
    } else {
        $mensagem_erro = implode(' · ', $erros);
    }
}

function val($arr, $chave, $padrao = '') {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

$compr_atual = (float)val($amb, 'comprimento_m', 5.0);
$larg_atual = (float)val($amb, 'largura_m', 5.0);
$pe_atual = (float)val($amb, 'peDireita_m', 2.8);
$area_atual = $compr_atual * $larg_atual;
$volume_atual = $area_atual * $pe_atual;

// Uso sugerido → pessoas, iluminação, etc.
$SUGESTOES_USO = [
    'Dormitório'      => ['pessoas' => 2, 'ilum' => 8,  'equip' => 150,  'temp_int' => 23],
    'Sala de Estar'   => ['pessoas' => 4, 'ilum' => 8,  'equip' => 300,  'temp_int' => 23],
    'Sala de Jantar'  => ['pessoas' => 6, 'ilum' => 10, 'equip' => 200,  'temp_int' => 23],
    'Cozinha'         => ['pessoas' => 3, 'ilum' => 10, 'equip' => 1500, 'temp_int' => 24],
    'Banheiro'        => ['pessoas' => 1, 'ilum' => 6,  'equip' => 50,   'temp_int' => 24],
    'Escritório'      => ['pessoas' => 2, 'ilum' => 12, 'equip' => 500,  'temp_int' => 23],
    'Loja'            => ['pessoas' => 10,'ilum' => 15, 'equip' => 800,  'temp_int' => 24],
    'Restaurante'     => ['pessoas' => 20,'ilum' => 12, 'equip' => 2000, 'temp_int' => 24],
    'Sala de Aula'    => ['pessoas' => 30,'ilum' => 10, 'equip' => 500,  'temp_int' => 24],
    'Auditório'       => ['pessoas' => 50,'ilum' => 12, 'equip' => 1000, 'temp_int' => 23],
    'Sala de Servidores' => ['pessoas' => 0, 'ilum' => 5, 'equip' => 3000, 'temp_int' => 20],
    'Closet'          => ['pessoas' => 1, 'ilum' => 8,  'equip' => 50,   'temp_int' => 24],
    'Varanda'         => ['pessoas' => 2, 'ilum' => 5,  'equip' => 100,  'temp_int' => 25],
    'Outro'           => ['pessoas' => 2, 'ilum' => 8,  'equip' => 200,  'temp_int' => 23],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 1 — Dados do Ambiente</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1000px; margin:0 auto; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { flex:1; text-align:center; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#0ea5e9; border-bottom:3px solid #0ea5e9; font-weight:700; }
.passo-link.concluido { color:#2563eb; border-bottom:2px solid #bfdbfe; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.3rem; color:#1e293b; margin-bottom:5px; }
h2 { font-size:1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; text-transform:uppercase; letter-spacing:0.5px; }
.subtitulo { color:#64748b; margin-bottom:12px; font-size:0.9rem; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
.grupo { margin-bottom:16px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:6px; font-size:0.85rem; }
.grupo small { color:#94a3b8; font-size:0.75rem; font-weight:400; margin-left:6px; }
input, select { padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,0.12); background:#fff; }
input.erro { border-color:#ef4444; background:#fef2f2; }
.grid-3col { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; }
.calculo-box { background:linear-gradient(135deg, #e0f2fe, #bae6fd); padding:20px; border-radius:10px; margin-top:16px; display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:16px; }
.calculo-item { text-align:center; }
.calculo-label { font-size:0.75rem; color:#075985; font-weight:700; text-transform:uppercase; margin-bottom:6px; }
.calculo-valor { font-size:1.8rem; color:#0369a1; font-weight:800; }
.calculo-unidade { font-size:0.85rem; color:#0284c7; }
.btn { padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; gap:6px; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-proximo { background:linear-gradient(135deg, #0ea5e9, #0284c7); color:#fff; }
.btn-salvar { background:linear-gradient(135deg, #10b981, #059669); color:#fff; }
.botoes { display:flex; gap:12px; margin-top:24px; justify-content:space-between; padding-top:20px; border-top:2px solid #e2e8f0; flex-wrap:wrap; }
.aviso-sucesso { background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#065f46; font-weight:600; }
.aviso-erro { background:#fee2e2; border-left:4px solid #ef4444; padding:14px 18px; margin-bottom:16px; border-radius:8px; color:#991b1b; font-weight:600; }
.dica { background:#eef2ff; border-left:3px solid #6366f1; padding:12px 16px; margin:16px 0; font-size:0.85rem; color:#3730a3; border-radius:0 6px 6px 0; }
.sugestao-box { background:#f0fdf4; border-left:3px solid #22c55e; padding:12px 16px; margin-top:12px; font-size:0.85rem; color:#166534; border-radius:0 6px 6px 0; }
</style>
</head>
<body>
<?php $nav_projeto_id = (int)($projeto_id ?? ($_GET['projeto_id'] ?? 0)); ?>
<?php $nav_ambiente_id = (int)($ambiente_id ?? ($_GET['ambiente_id'] ?? ($_GET['id'] ?? 0))); ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<div class="barra-progresso">
    <a class="passo-link ativo">1. Dados</a>
    <a class="passo-link<?= $ambiente_id ? ' concluido' : '' ?>">2. Paredes</a>
    <a class="passo-link">3. Solar</a>
    <a class="passo-link">4. Internas</a>
    <a class="passo-link">5. Dimensionamento</a>
    <a class="passo-link">6. Resultado</a>
</div>

<div class="cabecalho">
    <h1>📐 Dados do Ambiente</h1>
    <p class="subtitulo">
        Passo 1 — Defina nome, uso e dimensões gerais do ambiente<br>
        <small>Projeto: <strong><?= htmlspecialchars($proj['nome']) ?></strong> · ID: <?= $projeto_id ?></small>
    </p>
</div>

<?php if (!empty($mensagem_sucesso)): ?>
<div class="aviso-sucesso">✅ <?= htmlspecialchars($mensagem_sucesso) ?></div>
<?php endif; ?>

<?php if (!empty($mensagem_erro)): ?>
<div class="aviso-erro">⚠️ <?= htmlspecialchars($mensagem_erro) ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="salvar_ambiente" value="1">
    
    <div class="form-card">
        <h2>🏠 Identificação</h2>
        
        <div class="grupo">
            <label>Nome do Ambiente <small>(obrigatório)</small></label>
            <input type="text" name="nome" required 
                   placeholder="Ex: Quarto Principal, Sala de Estar, Cozinha..."
                   value="<?= htmlspecialchars(val($amb, 'nome', '')) ?>">
        </div>
        
        <div class="grupo">
            <label>Uso Principal</label>
            <select name="uso_principal" id="uso_principal" onchange="aplicarSugestao(this)">
                <?php foreach ($SUGESTOES_USO as $uso => $sug): ?>
                <option value="<?= htmlspecialchars($uso) ?>" 
                        data-pessoas="<?= $sug['pessoas'] ?>"
                        data-ilum="<?= $sug['ilum'] ?>"
                        data-equip="<?= $sug['equip'] ?>"
                        data-temp="<?= $sug['temp_int'] ?>"
                        <?= val($amb, 'uso_principal', 'Dormitório') === $uso ? 'selected' : '' ?>>
                    <?= htmlspecialchars($uso) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div id="sugestao_info" class="sugestao-box" style="display:none;"></div>
        </div>
    </div>
    
    <div class="form-card">
        <h2>📏 Dimensões</h2>
        
        <div class="grid-3col">
            <div class="grupo">
                <label>Comprimento (m)</label>
                <input type="number" step="0.01" min="0.1" name="comprimento_m" 
                       id="comprimento" oninput="calcularDimensoes()"
                       value="<?= number_format($compr_atual, 2, '.', '') ?>">
            </div>
            <div class="grupo">
                <label>Largura (m)</label>
                <input type="number" step="0.01" min="0.1" name="largura_m" 
                       id="largura" oninput="calcularDimensoes()"
                       value="<?= number_format($larg_atual, 2, '.', '') ?>">
            </div>
            <div class="grupo">
                <label>Altura / Pé-direito (m)</label>
                <input type="number" step="0.01" min="0.1" name="peDireita_m" 
                       id="pe_direito" oninput="calcularDimensoes()"
                       value="<?= number_format($pe_atual, 2, '.', '') ?>">
            </div>
        </div>
        
        <div class="calculo-box">
            <div class="calculo-item">
                <div class="calculo-label">Área do Piso</div>
                <div class="calculo-valor"><span id="area"><?= number_format($area_atual, 2, ',', '.') ?></span></div>
                <div class="calculo-unidade">m²</div>
            </div>
            <div class="calculo-item">
                <div class="calculo-label">Volume</div>
                <div class="calculo-valor"><span id="volume"><?= number_format($volume_atual, 2, ',', '.') ?></span></div>
                <div class="calculo-unidade">m³</div>
            </div>
            <div class="calculo-item">
                <div class="calculo-label">Pé-direito</div>
                <div class="calculo-valor"><?= number_format($pe_atual, 2, ',', '.') ?></div>
                <div class="calculo-unidade">m</div>
            </div>
        </div>
    </div>
    
    <div class="dica">
        💡 <strong>Dica:</strong> As dimensões definidas aqui serão usadas para calcular o volume do ambiente, 
        essencial para o cálculo da carga térmica por infiltração.
    </div>
    
    <div class="botoes">
        <a href="residencial_ambiente.php?projeto_id=<?= $projeto_id ?>" class="btn btn-voltar">
            ← Voltar
        </a>
        <div style="display:flex; gap:12px; margin-left:auto; flex-wrap:wrap;">
            <button type="submit" name="salvar_apenas" value="1" class="btn btn-salvar">
                💾 Salvar
            </button>
            <button type="submit" class="btn btn-proximo">
                Salvar e Prosseguir → Paredes
            </button>
        </div>
    </div>
</form>

<script>
function calcularDimensoes() {
    const comp = parseFloat(document.getElementById('comprimento').value) || 0;
    const larg = parseFloat(document.getElementById('largura').value) || 0;
    const pe = parseFloat(document.getElementById('pe_direito').value) || 0;
    
    const area = comp * larg;
    const volume = area * pe;
    
    document.getElementById('area').textContent = area.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('volume').textContent = volume.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function aplicarSugestao(sel) {
    const opt = sel.options[sel.selectedIndex];
    const pessoas = opt.dataset.pessoas;
    const ilum = opt.dataset.ilum;
    const equip = opt.dataset.equip;
    const temp = opt.dataset.temp;
    
    const info = document.getElementById('sugestao_info');
    if (info) {
        info.innerHTML = `
            <strong>📊 Sugestões para "${sel.value}":</strong><br>
            • Pessoas: <strong>${pessoas}</strong> · 
            • Iluminação: <strong>${ilum} W/m²</strong> · 
            · Equipamentos: <strong>${equip} W</strong> · 
            • Temp. interna: <strong>${temp}°C</strong><br>
            <small>Estes valores podem ser ajustados na <strong>Etapa 4 — Cargas Internas</strong>.</small>
        `;
        info.style.display = 'block';
    }
}

// Executar ao carregar
document.addEventListener('DOMContentLoaded', function() {
    calcularDimensoes();
    const sel = document.getElementById('uso_principal');
    if (sel) aplicarSugestao(sel);
});
</script>

</body>
</html>
