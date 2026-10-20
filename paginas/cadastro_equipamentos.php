<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$mensagem = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['acao'])) {
    if ($_POST['acao'] === 'salvar_tipo') {
        $nome = trim($_POST['nome']);
        $categoria = trim($_POST['categoria'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $pot = (float)$_POST['potencia_w'];
        $fator_demanda = !empty($_POST['fator_demanda']) ? (float)$_POST['fator_demanda'] : 1.0;
        $obs = trim($_POST['observacoes'] ?? '');
        
        if (!empty($_POST['tipo_id'])) {
            $tid = (int)$_POST['tipo_id'];
            $stmt = $conn->prepare("UPDATE tipos_equipamentos SET nome=?, categoria=?, marca=?, modelo=?, potencia_w=?, fator_demanda=?, observacoes=? WHERE id=?");
            $stmt->bind_param("ssssddsi", $nome, $categoria, $marca, $modelo, $pot, $fator_demanda, $obs, $tid);
        } else {
            $stmt = $conn->prepare("INSERT INTO tipos_equipamentos (nome, categoria, marca, modelo, potencia_w, fator_demanda, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdds", $nome, $categoria, $marca, $modelo, $pot, $fator_demanda, $obs);
        }
        $stmt->execute();
        $mensagem = 'sucesso';
    }
    if ($_POST['acao'] === 'excluir_tipo' && !empty($_POST['tipo_id'])) {
        $tid = (int)$_POST['tipo_id'];
        $conn->query("DELETE FROM tipos_equipamentos WHERE id = $tid");
        $mensagem = 'excluido';
    }
}

$lista = $conn->query("SELECT * FROM tipos_equipamentos WHERE ativo = 1 ORDER BY categoria, nome")->fetch_all(MYSQLI_ASSOC);
$editar = null;
if (!empty($_GET['editar'])) {
    $editar = $conn->query("SELECT * FROM tipos_equipamentos WHERE id = ".(int)$_GET['editar'])->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro — Tipos de Equipamentos</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1000px; margin:0 auto; }
.voltar { color:#6366f1; text-decoration:none; font-weight:500; display:inline-block; margin-bottom:15px; }
h1 { font-size:1.4rem; color:#1e293b; margin-bottom:20px; }
.card { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h2 { font-size:1.05rem; color:#374151; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid #e5e7eb; }
.grid-2col { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:14px; }
.grupo { margin-bottom:14px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:5px; font-size:0.85rem; }
input, select, textarea { padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; width:100%; font-size:0.9rem; }
.btn { padding:10px 20px; border-radius:8px; font-weight:700; border:none; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-primario { background:#6366f1; color:#fff; }
.btn-aviso { background:#fef3c7; color:#92400e; }
.btn-perigo { background:#fee2e2; color:#b91c1c; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; font-size:0.9rem; }
th { background:#f8fafc; font-weight:700; color:#475569; }
.sucesso { background:#d1fae5; color:#065f46; padding:10px 16px; border-radius:8px; margin-bottom:16px; }
.valor-destaque { font-weight:700; color:#1e40af; }
.acoes { white-space:nowrap; }
.categoria { background:#e0e7ff; color:#3730a3; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700; }
</style>
</head>
<body>

<a href="ambiente_dados.php" class="voltar">← Voltar para Ambiente</a>
<h1>🔌 Cadastro de Tipos de Equipamentos</h1>

<?php if ($mensagem === 'sucesso'): ?>
<div class="sucesso">✅ Salvo com sucesso!</div>
<?php elseif ($mensagem === 'excluido'): ?>
<div class="sucesso">🗑️ Excluído!</div>
<?php endif; ?>

<div class="card">
    <h2><?= $editar ? '✏️ Editar Tipo' : '➕ Novo Equipamento' ?></h2>
    <form method="POST">
        <input type="hidden" name="acao" value="salvar_tipo">
        <?php if ($editar): ?>
        <input type="hidden" name="tipo_id" value="<?= $editar['id'] ?>">
        <?php endif; ?>
        
        <div class="grid-2col">
            <div class="grupo">
                <label>Nome do Equipamento *</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? '') ?>" placeholder="Ex: TV 50 polegadas" required>
            </div>
            <div class="grupo">
                <label>Categoria</label>
                <select name="categoria">
                    <?php $cat = $editar['categoria'] ?? ''; ?>
                    <option value="">— Selecione —</option>
                    <option value="Eletrodomésticos" <?= $cat==='Eletrodomésticos'?'selected':'' ?>>Eletrodomésticos</option>
                    <option value="Eletrônicos" <?= $cat==='Eletrônicos'?'selected':'' ?>>Eletrônicos</option>
                    <option value="Climatização" <?= $cat==='Climatização'?'selected':'' ?>>Climatização</option>
                    <option value="Cozinha" <?= $cat==='Cozinha'?'selected':'' ?>>Cozinha</option>
                    <option value="Escritório" <?= $cat==='Escritório'?'selected':'' ?>>Escritório</option>
                    <option value="Outros" <?= $cat==='Outros'?'selected':'' ?>>Outros</option>
                </select>
            </div>
            <div class="grupo">
                <label>Marca</label>
                <input type="text" name="marca" value="<?= htmlspecialchars($editar['marca'] ?? '') ?>" placeholder="Ex: Samsung">
            </div>
            <div class="grupo">
                <label>Modelo</label>
                <input type="text" name="modelo" value="<?= htmlspecialchars($editar['modelo'] ?? '') ?>" placeholder="Ex: UN50TU8000">
            </div>
            <div class="grupo">
                <label>Potência (W) *</label>
                <input type="number" step="0.01" name="potencia_w" value="<?= $editar['potencia_w'] ?? '' ?>" required>
            </div>
            <div class="grupo">
                <label>Fator de Demanda</label>
                <input type="number" step="0.05" name="fator_demanda" value="<?= $editar['fator_demanda'] ?? 1.0 ?>" placeholder="Ex: 0.5 = 50%">
            </div>
        </div>
        <div class="grupo">
            <label>Observações / Dados Técnicos</label>
            <textarea name="observacoes" rows="2" placeholder="Período de uso, consumo médio..."><?= htmlspecialchars($editar['observacoes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primario">💾 <?= $editar ? 'Atualizar' : 'Salvar' ?></button>
        <?php if ($editar): ?>
        <a href="cadastro_equipamentos.php" class="btn" style="background:#e5e7eb; color:#374151;">Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 Equipamentos Cadastrados</h2>
    <?php if (empty($lista)): ?>
    <p style="color:#6b7280; padding:20px;">Nenhum equipamento cadastrado. Adicione acima! ☝️</p>
    <?php else: ?>
    <table>
        <tr>
            <th>Equipamento</th>
            <th>Categoria</th>
            <th>Potência</th>
            <th>Fator</th>
            <th>Ações</th>
        </tr>
        <?php foreach ($lista as $item): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($item['nome']) ?></strong>
                <?php if ($item['marca']): ?><br><small style="color:#6b7280;"><?= htmlspecialchars($item['marca']) ?> <?= htmlspecialchars($item['modelo']) ?></small><?php endif; ?>
            </td>
            <td><span class="categoria"><?= $item['categoria'] ?: '—' ?></span></td>
            <td class="valor-destaque"><?= number_format($item['potencia_w'],2,',','.') ?> W</td>
            <td><?= number_format($item['fator_demanda'],2,',','.') ?></td>
            <td class="acoes">
                <a href="cadastro_equipamentos.php?editar=<?= $item['id'] ?>" class="btn btn-aviso" style="padding:4px 8px; font-size:0.8rem;">Editar</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este equipamento?');">
                    <input type="hidden" name="acao" value="excluir_tipo">
                    <input type="hidden" name="tipo_id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn-perigo" style="padding:4px 8px; font-size:0.8rem; border:none;">Excluir</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
