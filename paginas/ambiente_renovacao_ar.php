<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);
if (!$projeto_id || !$ambiente_id) { header("Location: residencial_ambiente.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
$amb = $conn->query("SELECT * FROM ambientes WHERE id = $ambiente_id AND projeto_id = $projeto_id")->fetch_assoc();
if (!$proj || !$amb) { header("Location: residencial_ambiente.php?projeto_id=$projeto_id"); exit; }

function val($arr, $chave, $padrao = '') {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

$volume = val($amb, 'comprimento_m', 0) * val($amb, 'largura_m', 0) * val($amb, 'peDireita_m', 2.8);
$pessoas = val($amb, 'pessoas', 1);
$vazao_nbr_min = $pessoas * 30;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $insuflamento = (float)($_POST['insuflamento_m3h'] ?? 0);
    $exaustao = (float)($_POST['exaustao_m3h'] ?? 0);
    $infiltracao_perc = (float)($_POST['infiltracao_percentual'] ?? 10.0);
    $vazao_necessaria = (float)($_POST['vazao_necessaria_m3h'] ?? $vazao_nbr_min);
    $obs = trim($_POST['observacoes'] ?? '');
    
    $conn->query("DELETE FROM renovacao_ar WHERE ambiente_id = $ambiente_id");
    
    $stmt = $conn->prepare("INSERT INTO renovacao_ar (
        ambiente_id, vazao_nbr_minima_m3h, vazao_necessaria_m3h,
        insuflamento_m3h, exaustao_m3h, infiltracao_percentual, observacoes
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // 7 parâmetros = 7 letras: tudo string para não ter erro
    $stmt->bind_param("sssssss",
        $ambiente_id,
        $vazao_nbr_min,
        $vazao_necessaria,
        $insuflamento,
        $exaustao,
        $infiltracao_perc,
        $obs
    );
    $stmt->execute();
    
    header("Location: ambiente_memoria_calculo.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id");
    exit;
}

$ar = $conn->query("SELECT * FROM renovacao_ar WHERE ambiente_id = $ambiente_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passo 5 — Renovação de Ar</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1000px; margin:0 auto; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
.voltar { color:#10b981; text-decoration:none; font-weight:500; display:inline-block; margin-bottom:10px; }
h1 { font-size:1.3rem; color:#1e293b; margin-bottom:5px; }
.passo-indicador { display:inline-block; background:#e0f2fe; color:#0284c7; padding:6px 14px; border-radius:20px; font-weight:700; font-size:0.85rem; margin-bottom:15px; }
.barra-progresso { display:flex; justify-content:space-between; margin-bottom:24px; }
.passo-link { text-align:center; flex:1; font-size:0.75rem; color:#94a3b8; border-bottom:2px solid #e2e8f0; padding-bottom:8px; text-decoration:none; }
.passo-link.ativo { color:#0ea5e9; border-bottom:3px solid #0ea5e9; font-weight:700; }
.passo-link.concluido { color:#2563eb; border-bottom:2px solid #bfdbfe; }
.form-card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
.grupo { margin-bottom:16px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:6px; font-size:0.85rem; }
input, textarea { padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.9rem; width:100%; background:#fafafa; }
input:focus, textarea:focus { outline:none; border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,0.12); background:#fff; }
.grid-2col { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:16px; }
.aviso { background:#fef3c7; border-left:3px solid #f59e0b; padding:14px; margin:16px 0; border-radius:0 8px 8px 0; }
.valor-destaque { font-size:1.4rem; font-weight:700; color:#0369a1; }
.btn { padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:0.95rem; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-proximo { background:linear-gradient(135deg, #0ea5e9, #0284c7); color:#fff; }
.botoes { display:flex; gap:12px; margin-top:20px; justify-content:space-between; }
</style>
</head>
<body>

<div class="barra-progresso">
    <a href="ambiente_dados.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 1. Dados</a>
    <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 2. Paredes</a>
    <a href="ambiente_ganho_solar.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 3. Solar</a>
    <a href="ambiente_cargas_internas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="passo-link concluido">✓ 4. Internas</a>
    <a class="passo-link ativo">5. Ar</a>
    <a class="passo-link">6. Resultado</a>
</div>

<div class="cabecalho">
    <a href="ambiente_cargas_internas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="voltar">← Voltar Passo 4</a>
    <div class="passo-indicador">Passo 5 de 6</div>
    <h1>🌬️ Renovação de Ar — Ventilação</h1>
    <p style="color:#64748b; margin-top:8px;">Ambiente: <strong><?= htmlspecialchars(val($amb, 'nome')) ?></strong> — conforme ABNT NBR 16401</p>
</div>

<div class="aviso">
  <strong>📐 Volume do ambiente:</strong> <?= number_format($volume, 1, ',', '.') ?> m³<br>
  <strong>👥 Vazão mínima NBR 16401:</strong> <span class="valor-destaque"><?= $vazao_nbr_min ?> m³/h</span> (<?= $pessoas ?> pessoas × 30 m³/h)
</div>

<form method="POST" class="form-card">
    <div class="grid-2col">
        <div class="grupo">
            <label>Vazão necessária de projeto (m³/h)</label>
            <input type="number" step="1" name="vazao_necessaria_m3h" value="<?= $ar ? val($ar, 'vazao_necessaria_m3h') : $vazao_nbr_min ?>">
        </div>
        <div class="grupo">
            <label>Insuflamento — ar fornecido (m³/h)</label>
            <input type="number" step="1" name="insuflamento_m3h" value="<?= $ar ? val($ar, 'insuflamento_m3h') : 0 ?>">
        </div>
        <div class="grupo">
            <label>Exaustão — ar removido (m³/h)</label>
            <input type="number" step="1" name="exaustao_m3h" value="<?= $ar ? val($ar, 'exaustao_m3h') : 0 ?>">
        </div>
        <div class="grupo">
            <label>Infiltração por frestas (%)</label>
            <input type="number" step="1" name="infiltracao_percentual" value="<?= $ar ? val($ar, 'infiltracao_percentual') : 10 ?>">
        </div>
    </div>
    <div class="grupo">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= $ar ? val($ar, 'observacoes') : '' ?></textarea>
    </div>

    <div class="botoes">
        <a href="ambiente_cargas_internas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">← Voltar</a>
        <button type="submit" class="btn btn-proximo">Finalizar → Memória de Cálculo</button>
    </div>
</form>

</body>
</html>
