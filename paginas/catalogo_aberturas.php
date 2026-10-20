<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

// ✅ CORRIGIDO: força charset utf8mb4 (evita corromper acentos no nome)
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$projeto_id = (int)($_GET['projeto_id'] ?? 0);
$ambiente_id = (int)($_GET['ambiente_id'] ?? 0);

if (!$projeto_id || !$ambiente_id) {
    die("Projeto ou ambiente não informados. Volte e selecione.");
}

$mensagem = "";

// ============================================================
// 1) ADICIONAR / EDITAR ITEM
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['salvar_item'])) {
    $nome = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $metodo_medida = trim($_POST['metodo_medida'] ?? 'ren/h');
    $coef = (float)($_POST['coeficiente_infiltracao'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    
    // ✅ CORRIGIDO: lê o valor REAL do select (antes sempre retornava 1)
    $ativo = (int)($_POST['ativo'] ?? 1);
    
    // ✅ CORRIGIDO: sanitização leve do nome (remove prefixo numérico legado tipo "0 Basculante")
    $nome = preg_replace('/^\d+\s+/', '', $nome);
    // Remove espaços duplos
    $nome = preg_replace('/\s+/', ' ', $nome);
    $nome = trim($nome);
    
    if ($nome === '' || $categoria === '') {
        $mensagem = '<div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:16px;">⚠️ Preencha Nome e Categoria!</div>';
    } else {
        if (!empty($_POST['item_id'])) {
            // UPDATE
            $id = (int)$_POST['item_id'];
            $stmt = $conn->prepare("UPDATE tipos_aberturas 
                SET nome=?, categoria=?, metodo_medida=?, coeficiente_infiltracao=?, descricao=?, ativo=?
                WHERE id=?");
            // ✅ CORRIGIDO: tipos corretos → sssdsii (ativo é 'i', não 's')
            $stmt->bind_param("sssdsii", $nome, $categoria, $metodo_medida, $coef, $descricao, $ativo, $id);
            
            // ✅ CORRIGIDO: verifica retorno do execute()
            if ($stmt->execute()) {
                $mensagem = '<div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:8px; margin-bottom:16px;">✅ Item atualizado com sucesso!</div>';
            } else {
                error_log("Erro UPDATE item {$id}: " . $stmt->error);
                $mensagem = '<div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:16px;">❌ Erro ao atualizar: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO tipos_aberturas 
                (nome, categoria, metodo_medida, coeficiente_infiltracao, descricao, ativo)
                VALUES (?, ?, ?, ?, ?, ?)");
            // ✅ CORRIGIDO: tipos corretos → sssdsi
            $stmt->bind_param("sssdsi", $nome, $categoria, $metodo_medida, $coef, $descricao, $ativo);
            
            if ($stmt->execute()) {
                $mensagem = '<div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:8px; margin-bottom:16px;">✅ Item adicionado com sucesso!</div>';
            } else {
                error_log("Erro INSERT item: " . $stmt->error);
                $mensagem = '<div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:16px;">❌ Erro ao adicionar: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        }
    }
}

// ============================================================
// 2) EXCLUIR / DESATIVAR ITEM
// ============================================================
if (!empty($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    
    // ✅ CORRIGIDO: verifica se o item está em uso em itens_parede
    // Como itens_parede usa tipo_item (nome da categoria) e nome_item (nome),
    // uma abordagem segura é fazer SOFT DELETE (ativo = 0)
    $stmt = $conn->prepare("UPDATE tipos_aberturas SET ativo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: catalogo_aberturas.php?projeto_id=$projeto_id&ambiente_id=$ambiente_id&msg=desativado");
        exit;
    } else {
        error_log("Erro ao desativar item {$id}: " . $stmt->error);
        $mensagem = '<div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:16px;">❌ Erro ao desativar item.</div>';
    }
}

// ✅ NOVO: mensagem pós-redirect
if (!empty($_GET['msg']) && $_GET['msg'] === 'desativado') {
    $mensagem = '<div style="background:#fef3c7; color:#92400e; padding:10px; border-radius:8px; margin-bottom:16px;">⚠️ Item desativado (soft-delete). Ele não aparecerá em novas seleções, mas permanece no histórico.</div>';
}

// ============================================================
// 3) CARREGAR DADOS
// ============================================================
$itens = $conn->query("SELECT * FROM tipos_aberturas ORDER BY ativo DESC, categoria, nome")->fetch_all(MYSQLI_ASSOC);

// ✅ CORRIGIDO: categorias dinâmicas (inclui as já cadastradas no banco)
$cats_db = $conn->query("SELECT DISTINCT categoria FROM tipos_aberturas WHERE categoria != '' ORDER BY categoria")->fetch_all(MYSQLI_ASSOC);
$categorias = array_values(array_unique(array_merge(
    ['Porta', 'Janela', 'Basculante', 'Fresta', 'Outro'],
    array_column($cats_db, 'categoria')
)));
sort($categorias);

$editar = null;
if (!empty($_GET['editar'])) {
    $editar_id = (int)$_GET['editar'];
    $editar = $conn->query("SELECT * FROM tipos_aberturas WHERE id = $editar_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo de Aberturas</title>
<style>
* { box-sizing:border-box; font-family:system-ui,sans-serif; margin:0; padding:0; }
body { background:#f0f4f8; padding:20px; max-width:1000px; margin:0 auto; }
.cabecalho { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h1 { font-size:1.4rem; color:#1e293b; margin-bottom:5px; }
.subtitulo { color:#64748b; margin-bottom:20px; }
.form-bloco { background:#fff; padding:24px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px; }
h2 { font-size:1.1rem; color:#334155; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.grid-2col { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:14px; }
.grupo { margin-bottom:14px; }
.grupo label { display:block; font-weight:600; color:#334155; margin-bottom:5px; font-size:0.85rem; }
input, select, textarea { padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.9rem; width:100%; background:#fafafa; }
input:focus, select:focus { outline:none; border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,0.12); background:#fff; }
.btn { padding:10px 20px; border-radius:8px; font-weight:700; border:none; cursor:pointer; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
.btn-salvar { background:linear-gradient(135deg, #0ea5e9, #0284c7); color:#fff; }
.btn-voltar { background:#e5e7eb; color:#374151; }
.btn-excluir { background:#fee2e2; color:#b91c1c; padding:6px 12px; font-size:0.8rem; }
.btn-editar { background:#dbeafe; color:#1e40af; padding:6px 12px; font-size:0.8rem; }
.tabela { width:100%; border-collapse:collapse; margin-top:16px; }
.tabela th { background:#f1f5f9; padding:12px 10px; text-align:left; font-weight:700; color:#475569; font-size:0.85rem; }
.tabela td { padding:12px 10px; border-bottom:1px solid #e2e8f0; font-size:0.9rem; }
.tabela tr:hover { background:#f8fafc; }
.etiqueta-cat { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; }
.cat-porta { background:#dbeafe; color:#1e40af; }
.cat-janela { background:#d1fae5; color:#0e7490; }
.cat-basculante { background:#f3e8ff; color:#7c3aed; }
.cat-fresta { background:#fee2e2; color:#dc2626; }
.cat-outro { background:#e5e7eb; color:#4b5563; }
.inativo { opacity:0.5; }
.inativo strong { text-decoration:line-through; }
.botoes { display:flex; gap:12px; margin-top:20px; flex-wrap:wrap; }
.aviso { background:#fffbeb; border-left:3px solid #f59e0b; padding:12px 16px; margin-bottom:20px; font-size:0.85rem; color:#92400e; }
</style>
</head>
<body>

<div class="cabecalho">
    <h1>📋 Catálogo de Aberturas e Frestas</h1>
    <p class="subtitulo">Gerencie os itens disponíveis para seleção nas paredes do ambiente</p>
    
    <?php echo $mensagem; ?>
    
    <div class="aviso">
        💡 <strong>Coeficiente de infiltração:</strong> ren/h = renovações por hora · m³/(h·m) = por metro de perímetro/fresta
    </div>
    
    <div class="botoes">
        <a href="ambiente_paredes.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">← Voltar para Paredes</a>
    </div>
</div>

<!-- Formulário -->
<div class="form-bloco">
    <h2><?= $editar ? '✏️ Editar Item' : '➕ Novo Item' ?></h2>
    <form method="POST">
        <?php if ($editar): ?>
        <input type="hidden" name="item_id" value="<?= (int)$editar['id'] ?>">
        <?php endif; ?>
        
        <div class="grid-2col">
            <div class="grupo" style="grid-column:1 / -1;">
                <label>Nome do Item *</label>
                <input type="text" name="nome" required placeholder="Ex: Janela basculante c/ vedação" 
                       value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                <small style="color:#64748b; font-size:0.75rem;">Este nome aparecerá completo na página de Paredes.</small>
            </div>
            
            <div class="grupo">
                <label>Categoria *</label>
                <select name="categoria" required>
                    <option value="">— Selecione —</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" 
                        <?= ($editar['categoria'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php // ✅ CORRIGIDO: se categoria do item não está na lista, mostra mesmo assim ?>
                    <?php if ($editar && !empty($editar['categoria']) && !in_array($editar['categoria'], $categorias)): ?>
                    <option value="<?= htmlspecialchars($editar['categoria']) ?>" selected>
                        <?= htmlspecialchars($editar['categoria']) ?> (legado)
                    </option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="grupo">
                <label>Método de Cálculo</label>
                <select name="metodo_medida">
                    <option value="ren/h" <?= ($editar['metodo_medida'] ?? 'ren/h') === 'ren/h' ? 'selected' : '' ?>>
                        Renovações por hora (ren/h)
                    </option>
                    <option value="m³/(h·m)" <?= ($editar['metodo_medida'] ?? '') === 'm³/(h·m)' ? 'selected' : '' ?>>
                        Por perímetro (m³/h·m)
                    </option>
                </select>
            </div>
            
            <div class="grupo">
                <label>Coeficiente de Infiltração</label>
                <input type="number" step="0.01" min="0" name="coeficiente_infiltracao" 
                       value="<?= htmlspecialchars($editar['coeficiente_infiltracao'] ?? '0.50') ?>">
            </div>
            
            <div class="grupo">
                <label>Status</label>
                <select name="ativo">
                    <option value="1" <?= ($editar['ativo'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo — aparece na lista</option>
                    <option value="0" <?= ($editar['ativo'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo — oculto</option>
                </select>
            </div>
            
            <div class="grupo" style="grid-column:1 / -1;">
                <label>Descrição / Observações</label>
                <textarea name="descricao" rows="2" placeholder="Características, referências..."><?= htmlspecialchars($editar['descricao'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="botoes">
            <button type="submit" name="salvar_item" class="btn btn-salvar">💾 Salvar Item</button>
            <?php if ($editar): ?>
            <a href="catalogo_aberturas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>" class="btn btn-voltar">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Lista -->
<div class="form-bloco">
    <h2>📋 Itens Cadastrados (<?= count($itens) ?>)</h2>
    
    <table class="tabela">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Coef.</th>
                <th>Método</th>
                <th>Status</th>
                <th style="text-align:right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($itens)): ?>
            <tr>
                <td colspan="6" style="text-align:center; color:#94a3b8; padding:20px;">
                    Nenhum item cadastrado ainda. Crie o primeiro acima!
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($itens as $item): 
                // ✅ CORRIGIDO: classe robusta (sem acento, minúscula, sem espaços)
                $cat_norm = strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $item['categoria'] ?? '')));
                $cat_classe = match($cat_norm) {
                    'porta' => 'cat-porta',
                    'janela' => 'cat-janela',
                    'basculante' => 'cat-basculante',
                    'fresta' => 'cat-fresta',
                    default => 'cat-outro'
                };
                $ativo_item = (int)($item['ativo'] ?? 1);
                $is_inativo = ($ativo_item === 0);
                
                // ✅ CORRIGIDO: cor do status em variável PHP (antes era PHP inline dentro do style)
                $cor_status = $is_inativo ? '#dc2626' : '#059669';
            ?>
            <tr class="<?= $is_inativo ? 'inativo' : '' ?>">
                <td><strong><?= htmlspecialchars($item['nome'] ?? '') ?></strong></td>
                <td><span class="etiqueta-cat <?= $cat_classe ?>"><?= htmlspecialchars($item['categoria'] ?? '—') ?></span></td>
                <td><?= number_format((float)($item['coeficiente_infiltracao'] ?? 0), 2, ',', '.') ?></td>
                <td style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($item['metodo_medida'] ?? '') ?></td>
                <td style="color:<?= $cor_status ?>; font-weight:600;">
                    <?= $is_inativo ? '❌ Inativo' : '✅ Ativo' ?>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                    <a href="catalogo_aberturas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>&editar=<?= (int)$item['id'] ?>" 
                       class="btn btn-editar">✏️ Editar</a>
                    <?php if (!$is_inativo): ?>
                    <a href="catalogo_aberturas.php?projeto_id=<?= $projeto_id ?>&ambiente_id=<?= $ambiente_id ?>&excluir=<?= (int)$item['id'] ?>" 
                       class="btn btn-excluir" onclick="return confirm('Desativar este item? Ele não aparecerá em novas seleções, mas permanecerá no histórico.')">🚫 Desativar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
