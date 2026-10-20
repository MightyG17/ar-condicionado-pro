<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/config/config.php";

// Criar projeto novo
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['nome_projeto'])) {
    $nome = trim($_POST['nome_projeto']);
    $cliente = trim($_POST['nome_cliente'] ?? '');
    $stmt = $conn->prepare("INSERT INTO projetos (nome, nome_cliente, data_criacao) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $nome, $cliente);
    $stmt->execute();
    header("Location: paginas/residencial_ambiente.php?projeto_id=" . $conn->insert_id);
    exit;
}

// Excluir projeto
if (!empty($_GET['excluir'])) {
    $pid = (int)$_GET['excluir'];
    
    // Apagar tudo relacionado
    $ambientes = $conn->query("SELECT id FROM ambientes WHERE projeto_id = $pid")->fetch_all(MYSQLI_ASSOC);
    foreach ($ambientes as $amb) {
        $aid = $amb['id'];
        $conn->query("DELETE FROM resultados_calculo WHERE ambiente_id = $aid");
        $conn->query("DELETE FROM renovacao_ar WHERE ambiente_id = $aid");
        $conn->query("DELETE FROM cargas_internas WHERE ambiente_id = $aid");
        $conn->query("DELETE FROM ganho_solar WHERE ambiente_id = $aid");
        $conn->query("DELETE FROM paredes WHERE ambiente_id = $aid");
    }
    $conn->query("DELETE FROM ambientes WHERE projeto_id = $pid");
    $conn->query("DELETE FROM projetos WHERE id = $pid");
    
    header("Location: index.php");
    exit;
}

$projetos = $conn->query("SELECT * FROM projetos ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistema de Cálculo de Carga Térmica</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:linear-gradient(135deg, #0ea5e9, #6366f1); min-height:100vh; padding:30px 20px; }
.container { max-width:900px; margin:0 auto; }
.cabecalho { text-align:center; color:#fff; margin-bottom:40px; }
.cabecalho h1 { font-size:2rem; margin-bottom:10px; }
.cabecalho p { opacity:0.9; font-size:1.05rem; }
.card { background:#fff; padding:30px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.15); margin-bottom:24px; }
h2 { font-size:1.2rem; color:#1e293b; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #e2e8f0; }
form label { display:block; font-weight:600; color:#374151; margin:14px 0 6px; }
form input { padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px; font-size:1rem; width:100%; max-width:500px; }
form input:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,0.15); }
.btn-novo { background:linear-gradient(135deg, #0ea5e9, #6366f1); color:#fff; border:none; padding:14px 32px; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; margin-top:20px; }
.projeto-lista { list-style:none; margin-top:16px; }
.projeto-item { padding:16px; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; transition:all 0.2s; }
.projeto-item:hover { border-color:#6366f1; background:#f8fafc; }
.projeto-link { font-weight:600; color:#1e40af; text-decoration:none; font-size:1rem; }
.projeto-dados { font-size:0.85rem; color:#6b7280; margin-top:4px; }
.btn-excluir { color:#dc2626; text-decoration:none; font-size:0.85rem; padding:6px 12px; border-radius:6px; background:#fef2f2; }
.vazio { text-align:center; color:#6b7280; padding:30px; }
.total-projeto { font-weight:700; color:#059669; }
</style>
</head>
<body>

<div class="container">
    <div class="cabecalho">
        <h1>❄️ Cálculo de Carga Térmica</h1>
        <p>Sistema profissional — ABNT NBR 16401</p>
    </div>

    <div class="card">
        <h2>➕ Criar Novo Projeto</h2>
        <form method="POST">
            <label>Nome do Projeto</label>
            <input type="text" name="nome_projeto" placeholder="Ex: Residência — Rua das Flores, 123" required>
            
            <label>Nome do Cliente</label>
            <input type="text" name="nome_cliente" placeholder="Ex: Jardel de Avelar Teixeira">
            
            <button type="submit" class="btn-novo">Criar Projeto →</button>
        </form>
    </div>

    <div class="card">
        <h2>📁 Projetos Salvos</h2>
        <?php if ($projetos->num_rows === 0): ?>
            <p class="vazio">Nenhum projeto ainda. Crie o primeiro acima! ☝️</p>
        <?php else: ?>
            <ul class="projeto-lista">
            <?php while ($proj = $projetos->fetch_assoc()): 
                $pid = $proj['id'];
                $amb_qtd = $conn->query("SELECT COUNT(*) as qtd FROM ambientes WHERE projeto_id = $pid")->fetch_assoc()['qtd'];
                $soma_w = $conn->query("SELECT SUM(carga_total_w) as total FROM resultados_calculo r 
                    INNER JOIN ambientes a ON r.ambiente_id = a.id WHERE a.projeto_id = $pid")->fetch_assoc()['total'] ?? 0;
            ?>
                <li class="projeto-item">
                    <div>
                        <a href="paginas/residencial_ambiente.php?projeto_id=<?= $pid ?>" class="projeto-link">
                            <?= htmlspecialchars($proj['nome']) ?>
                        </a>
                        <div class="projeto-dados">
                            Cliente: <?= htmlspecialchars($proj['nome_cliente'] ?? 'Não informado') ?> · 
                            Ambientes: <?= $amb_qtd ?> · 
                            <?php if ($soma_w > 0): ?>
                                <span class="total-projeto">Carga total: <?= number_format($soma_w,0,',','.') ?> W</span>
                            <?php else: ?>
                                <span style="color:#f59e0b;">Aguardando cálculo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="index.php?excluir=<?= $pid ?>" class="btn-excluir" onclick="return confirm('Excluir este projeto e TODOS os ambientes?')">Excluir</a>
                </li>
            <?php endwhile; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
