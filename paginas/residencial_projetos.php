<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/../config/config.php";

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dados = [
        'nome' => trim($_POST['nome']),
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep']),
        'endereco' => trim($_POST['endereco'] ?? ''),
        'numero' => trim($_POST['numero'] ?? ''),
        'complemento' => trim($_POST['complemento'] ?? ''),
        'bairro' => trim($_POST['bairro'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'uf' => trim($_POST['uf'] ?? ''),
        'latitude' => !empty($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null,
        'longitude' => !empty($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null,
        'altitude_m' => !empty($_POST['altitude_m']) && is_numeric($_POST['altitude_m']) ? (float)$_POST['altitude_m'] : 0,
        'temp_interna_verao' => (float)$_POST['temp_interna_verao'],
        'temp_externa_verao' => (float)$_POST['temp_externa_verao'],
        'umid_interna_verao' => (float)$_POST['umid_interna_verao'],
        'umid_externa_verao' => (float)$_POST['umid_externa_verao'],
        'fonte_dados_climaticos' => trim($_POST['fonte_dados'] ?? 'Manual'),
        'irradiacao_media_kwh_m2' => !empty($_POST['irradiacao_media_kwh_m2']) ? (float)$_POST['irradiacao_media_kwh_m2'] : null,
        'dados_nasa' => trim($_POST['dados_nasa'] ?? '')
    ];

    if (!empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE projetos SET 
            nome=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?, cidade=?, uf=?, 
            latitude=?, longitude=?, altitude_m=?, temp_interna_verao=?, temp_externa_verao=?, 
            umid_interna_verao=?, umid_externa_verao=?, fonte_dados_climaticos=?,
            irradiacao_media_kwh_m2=?, dados_nasa=? WHERE id=?");
        $stmt->bind_param("sssssssssdddsdsdssi",
            $dados['nome'], $dados['cep'], $dados['endereco'], $dados['numero'],
            $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['uf'],
            $dados['latitude'], $dados['longitude'], $dados['altitude_m'],
            $dados['temp_interna_verao'], $dados['temp_externa_verao'],
            $dados['umid_interna_verao'], $dados['umid_externa_verao'],
            $dados['fonte_dados_climaticos'], $dados['irradiacao_media_kwh_m2'],
            $dados['dados_nasa'], $id
        );
        $mensagem = $stmt->execute() ? "✅ Projeto atualizado!" : "❌ Erro: ".$conn->error;
    } else {
        $stmt = $conn->prepare("INSERT INTO projetos 
            (nome, cep, endereco, numero, complemento, bairro, cidade, uf, latitude, longitude, 
             altitude_m, temp_interna_verao, temp_externa_verao, umid_interna_verao, umid_externa_verao, 
             fonte_dados_climaticos, irradiacao_media_kwh_m2, dados_nasa)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssssdddsdssds",
            $dados['nome'], $dados['cep'], $dados['endereco'], $dados['numero'],
            $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['uf'],
            $dados['latitude'], $dados['longitude'], $dados['altitude_m'],
            $dados['temp_interna_verao'], $dados['temp_externa_verao'],
            $dados['umid_interna_verao'], $dados['umid_externa_verao'],
            $dados['fonte_dados_climaticos'], $dados['irradiacao_media_kwh_m2'],
            $dados['dados_nasa']
        );
        $mensagem = $stmt->execute() ? "✅ Projeto criado!" : "❌ Erro: ".$conn->error;
    }
}

if (!empty($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $conn->query("DELETE FROM ambientes WHERE projeto_id = $id");
    $conn->query("DELETE FROM projetos WHERE id = $id");
    header("Location: residencial_projetos.php");
    exit;
}

$editar = null;
if (!empty($_GET['editar'])) {
    $editar = $conn->query("SELECT * FROM projetos WHERE id = ".(int)$_GET['editar'])->fetch_assoc();
}

$projetos = $conn->query("SELECT * FROM projetos ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Projetos Residenciais — Climatização Eficiente</title>
<style>
* { box-sizing: border-box; font-family: system-ui, sans-serif; margin:0; padding:0; }
body { background:#f8fafc; padding:20px; max-width:1000px; margin:0 auto; }
h1 { color:#1e293b; margin-bottom:20px; text-align:center; }
.mensagem { padding:14px; border-radius:10px; margin-bottom:20px; text-align:center; font-weight:600; }
.sucesso { background:#dcfce7; color:#166534; }
.erro { background:#fee2e2; color:#991b1b; }
.aviso { background:#fef3c7; color:#92400e; padding:10px; border-radius:8px; margin:10px 0; font-size:0.9rem; }
.sucesso-fonte { background:#dcfce7; color:#166534; }
.dados-nasa { background:#f0f9ff; border-left:4px solid #0ea5e9; padding:12px; border-radius:0 10px 10px 0; margin:12px 0; font-size:0.85rem; display:none; }
.dados-nasa.visivel { display:block; }
.dados-nasa h4 { color:#0369a1; margin-bottom:8px; font-size:0.9rem; }
.dados-nasa .linha { display:flex; gap:15px; flex-wrap:wrap; margin-top:4px; }
.dados-nasa .item { color:#1e293b; }
.dados-nasa .valor { font-weight:700; color:#0284c7; }

.form-card { background:#fff; border-radius:14px; padding:25px; margin-bottom:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-top:4px solid #2563eb; }
.form-card h2 { font-size:1.15rem; color:#1e40af; margin-bottom:20px; }
.cep-destaque { background:#eff6ff; padding:15px; border-radius:10px; margin-bottom:20px; border-left:4px solid #3b82f6; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:15px; }
.grupo { margin-bottom:12px; }
.grupo label { display:block; font-weight:500; color:#334155; margin-bottom:6px; font-size:0.9rem; }
.grupo input { width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem; background:#fafafa; }
.grupo input:focus { outline:none; border-color:#2563eb; background:#fff; box-shadow:0 0 0 3px rgba(59,130,246,0.12); }
.grupo input:disabled, .grupo input[readonly] { background:#f1f5f9; color:#475569; }
.endereco-completo { grid-column:span 2; }
.linha-clima { background:#f0fdf4; padding:15px; border-radius:10px; margin:15px 0; border-left:4px solid #22c55e; }
.linha-clima h3 { color:#15803d; font-size:0.95rem; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
.fonte-tag { font-size:0.75rem; background:#e2e8f0; color:#475569; padding:3px 10px; border-radius:12px; font-weight:600; }
.fonte-tag.nasa { background:#e0f2fe; color:#0369a1; }
.fonte-tag.openmeteo { background:#dcfce7; color:#166534; }
.fonte-tag.nbr { background:#fef3c7; color:#92400e; }
.btn-salvar { background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#fff; border:none; padding:13px 28px; font-size:1rem; font-weight:700; border-radius:10px; cursor:pointer; width:100%; margin-top:10px; }
.btn-salvar:hover { transform:scale(1.01); }
.btn-cancelar { display:inline-block; margin-top:10px; color:#64748b; text-decoration:none; font-size:0.9rem; text-align:center; }

.lista-projetos { margin-top:30px; }
.lista-projetos h2 { font-size:1.2rem; color:#1e293b; margin-bottom:15px; padding-bottom:8px; border-bottom:1px solid #e2e8f0; }
.projeto-card { background:#fff; border-radius:10px; padding:18px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
.projeto-nome { font-weight:700; color:#1e293b; font-size:1rem; }
.projeto-local { color:#64748b; font-size:0.85rem; margin-top:4px; }
.projeto-acoes { display:flex; gap:8px; }
.btn { padding:8px 14px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:600; border:none; cursor:pointer; }
.btn-ver { background:#dbeafe; color:#1e40af; }
.btn-editar { background:#fef3c7; color:#92400e; }
.btn-excluir { background:#fee2e2; color:#991b1b; }
.sem-projetos { text-align:center; color:#64748b; padding:30px; background:#fff; border-radius:10px; }

@media (max-width:640px) {
    .endereco-completo { grid-column:span 1; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<h1>🏠 Projetos de Climatização Residencial</h1>

<?php if ($mensagem): ?>
<div class="mensagem <?= str_starts_with($mensagem, '✅') ? 'sucesso' : 'erro' ?>">
    <?= $mensagem ?>
</div>
<?php endif; ?>

<div class="form-card">
    <h2><?= $editar ? '✏️ Editar Projeto' : '➕ Novo Projeto' ?></h2>
    <form method="POST" id="formProjeto">
        <?php if ($editar): ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="grupo" style="grid-column:span 2;">
                <label>👤 Nome do Cliente / Projeto</label>
                <input type="text" name="nome" required
                       value="<?= $editar ? htmlspecialchars($editar['nome']) : '' ?>"
                       placeholder="Digite o nome do cliente — identificação do projeto">
            </div>
        </div>

        <div class="cep-destaque" style="margin-top:15px;">
            <div class="form-grid">
                <div class="grupo" style="grid-column:span 1;">
                    <label for="cep">📮 Digite o CEP</label>
                    <input type="text" id="cep" name="cep" 
                           value="<?= $editar ? $editar['cep'] : '' ?>" 
                           placeholder="00000-000" maxlength="10"
                           onblur="buscarEndereco()">
                </div>
                <div class="grupo" style="grid-column:span 1;">
                    <label>Número *</label>
                    <input type="text" name="numero" id="numero" required
                           value="<?= $editar ? $editar['numero'] : '' ?>"
                           placeholder="Ex: 123">
                </div>
            </div>
            
            <div class="form-grid" style="margin-top:10px;">
                <div class="grupo endereco-completo">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" readonly
                           value="<?= $editar ? $editar['endereco'] : '' ?>">
                </div>
                <div class="grupo">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" readonly
                           value="<?= $editar ? $editar['bairro'] : '' ?>">
                </div>
                <div class="grupo">
                    <label>Cidade / UF</label>
                    <input type="text" id="cidade_uf" readonly
                           value="<?= $editar ? $editar['cidade'].' / '.$editar['uf'] : '' ?>">
                    <input type="hidden" name="cidade" id="cidade_hid" value="<?= $editar ? $editar['cidade'] : '' ?>">
                    <input type="hidden" name="uf" id="uf_hid" value="<?= $editar ? $editar['uf'] : '' ?>">
                </div>
                <div class="grupo">
                    <label>Complemento</label>
                    <input type="text" name="complemento"
                           value="<?= $editar ? $editar['complemento'] : '' ?>"
                           placeholder="Apto, Bloco...">
                </div>
            </div>
            
            <div id="coordenadas" style="margin-top:10px; font-size:0.8rem; color:#475569; display:none;">
                <input type="hidden" name="latitude" id="latitude" value="<?= $editar ? $editar['latitude'] : '' ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= $editar ? $editar['longitude'] : '' ?>">
                <input type="hidden" name="altitude_m" id="altitude_m" value="<?= $editar ? $editar['altitude_m'] : '' ?>">
                <input type="hidden" name="irradiacao_media_kwh_m2" id="irrad" value="<?= $editar ? $editar['irradiacao_media_kwh_m2'] : '' ?>">
                <input type="hidden" name="dados_nasa" id="dados_nasa" value="<?= $editar ? htmlspecialchars($editar['dados_nasa']) : '' ?>">
                📍 <span id="coord_texto"></span>
            </div>
        </div>

        <!-- Painel de Dados Climáticos -->
        <div id="blocoDadosNasa" class="dados-nasa">
            <h4>🛰️ Dados Climáticos da Região</h4>
            <div class="linha">
                <div class="item">Temp. máx. verão: <span class="valor" id="nasa_temp">—</span> °C</div>
                <div class="item">UR média (2m): <span class="valor" id="nasa_ur">—</span> %</div>
            </div>
            <div class="linha">
                <div class="item">Irradiação solar: <span class="valor" id="nasa_irrad">—</span> kWh/m²·dia</div>
                <div class="item">Altitude: <span class="valor" id="nasa_alt">—</span> m</div>
            </div>
        </div>

        <div class="linha-clima" id="blocoClima">
            <h3>
                🌤️ Condições de Projeto
                <span id="fonteTag" class="fonte-tag nbr">NBR 16401-1</span>
            </h3>
            <input type="hidden" name="fonte_dados" id="fonteDados" value="<?= $editar ? $editar['fonte_dados_climaticos'] : 'NBR 16401-1' ?>">
            
            <div id="avisoClima" class="aviso" style="display:none;">
                ⏳ Atualizando dados climáticos...
            </div>
            
            <div class="form-grid">
                <div class="grupo">
                    <label>Temp. Ext. Verão (°C)</label>
                    <input type="number" step="0.1" name="temp_externa_verao" id="temp_ext" required
                           value="<?= $editar ? $editar['temp_externa_verao'] : '31.0' ?>">
                </div>
                <div class="grupo">
                    <label>UR Externa (%)</label>
                    <input type="number" step="0.1" name="umid_externa_verao" id="ur_ext" required
                           value="<?= $editar ? $editar['umid_externa_verao'] : '65.0' ?>">
                </div>
                <div class="grupo">
                    <label>Temp. Int. Conforto (°C)</label>
                    <input type="number" step="0.1" name="temp_interna_verao" required
                           value="<?= $editar ? $editar['temp_interna_verao'] : '23.0' ?>">
                </div>
                <div class="grupo">
                    <label>UR Interna (%)</label>
                    <input type="number" step="0.1" name="umid_interna_verao" required
                           value="<?= $editar ? $editar['umid_interna_verao'] : '50.0' ?>">
                </div>
            </div>
            <p style="font-size:0.8rem; color:#64748b; margin-top:8px;">
                💡 Valores: NBR 16401-1 (inicial) → Open-Meteo (tempo real) → NASA POWER (satélite, quando disponível). Ajuste conforme projeto.
            </p>
        </div>

        <button type="submit" class="btn-salvar">
            <?= $editar ? '✏️ Atualizar Projeto' : '➕ Criar Projeto' ?>
        </button>
        <?php if ($editar): ?>
            <a href="residencial_projetos.php" class="btn-cancelar" style="display:block; text-align:center;">↩️ Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<div class="lista-projetos">
    <h2>📋 Projetos Cadastrados</h2>
    <?php if ($projetos->num_rows === 0): ?>
        <p class="sem-projetos">Nenhum projeto criado ainda. Preencha o formulário acima.</p>
    <?php else: ?>
        <?php while ($proj = $projetos->fetch_assoc()): ?>
        <div class="projeto-card">
            <div>
                <div class="projeto-nome"><?= htmlspecialchars($proj['nome']) ?></div>
                <div class="projeto-local">
                    <?= $proj['cidade'] ? htmlspecialchars($proj['cidade']).' / '.$proj['uf'] : 'Sem endereço' ?>
                    · Altitude: <?= number_format($proj['altitude_m'] ?? 0, 1, ',', '.') ?> m
                    · Temp: <?= number_format($proj['temp_externa_verao'], 1, ',', '.') ?>°C / <?= number_format($proj['temp_interna_verao'], 1, ',', '.') ?>°C
                    <?php if (!empty($proj['irradiacao_media_kwh_m2'])): ?>
                    · ☀️ Irradiação: <?= number_format($proj['irradiacao_media_kwh_m2'], 2, ',', '.') ?> kWh/m²·dia
                    <?php endif; ?>
                    <br>
                    <small>Fonte: <?= htmlspecialchars($proj['fonte_dados_climaticos'] ?? 'Não informada') ?></small>
                </div>
            </div>
            <div class="projeto-acoes">
                <a href="residencial_ambiente.php?projeto_id=<?= $proj['id'] ?>" class="btn btn-ver">🏠 Ambientes</a>
                <a href="residencial_projetos.php?editar=<?= $proj['id'] ?>" class="btn btn-editar">✏️ Editar</a>
                <a href="residencial_projetos.php?excluir=<?= $proj['id'] ?>" class="btn btn-excluir" 
                   onclick="return confirm('Excluir este projeto e todos os ambientes? Não tem desfazer.')">🗑️ Excluir</a>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<script>
// Referência NBR 16401-1 — valor imediato
const referenciaNBR = {
    'Rio de Janeiro': { temp: 32.0, ur: 65.0, alt: 5.0 },
    'Volta Redonda': { temp: 32.0, ur: 62.0, alt: 350.0 },
    'Barra Mansa': { temp: 32.0, ur: 62.0, alt: 380.0 },
    'São Paulo': { temp: 31.0, ur: 60.0, alt: 760.0 },
    'Niterói': { temp: 31.5, ur: 65.0, alt: 10.0 },
    'Duque de Caxias': { temp: 32.0, ur: 63.0, alt: 10.0 },
    'São Gonçalo': { temp: 31.5, ur: 64.0, alt: 15.0 },
    'Belo Horizonte': { temp: 31.5, ur: 55.0, alt: 920.0 },
    'Brasília': { temp: 32.0, ur: 50.0, alt: 1172.0 },
    'Salvador': { temp: 30.0, ur: 75.0, alt: 10.0 },
    'Recife': { temp: 30.5, ur: 72.0, alt: 7.0 },
    'Fortaleza': { temp: 31.0, ur: 70.0, alt: 25.0 },
    'Porto Alegre': { temp: 31.5, ur: 65.0, alt: 10.0 },
    'Curitiba': { temp: 29.0, ur: 60.0, alt: 930.0 },
    'Manaus': { temp: 32.5, ur: 75.0, alt: 90.0 }
};

function aplicarValores(dados, fonteClasse, fonteTexto) {
    document.getElementById('temp_ext').value = dados.temp.toFixed(1);
    document.getElementById('ur_ext').value = dados.ur.toFixed(1);
    if (dados.alt !== undefined && dados.alt > 0) {
        document.getElementById('altitude_m').value = dados.alt.toFixed(1);
        document.getElementById('nasa_alt').textContent = dados.alt.toFixed(1);
    }
    if (dados.irrad !== undefined) {
        document.getElementById('irrad').value = dados.irrad.toFixed(2);
        document.getElementById('nasa_irrad').textContent = dados.irrad.toFixed(2);
    }
    document.getElementById('nasa_temp').textContent = dados.temp.toFixed(1);
    document.getElementById('nasa_ur').textContent = dados.ur.toFixed(1);
    
    const tag = document.getElementById('fonteTag');
    tag.textContent = fonteTexto;
    tag.className = 'fonte-tag ' + fonteClasse;
    document.getElementById('fonteDados').value = fonteTexto;
    document.getElementById('avisoClima').style.display = 'none';
    document.getElementById('blocoDadosNasa').classList.add('visivel');
}

async function buscarEndereco() {
    const cep = document.getElementById('cep').value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    try {
        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const dados = await res.json();
        
        if (dados.erro) {
            alert('CEP não encontrado. Verifique e tente novamente.');
            return;
        }

        document.getElementById('endereco').value = dados.logradouro || '';
        document.getElementById('bairro').value = dados.bairro || '';
        document.getElementById('cidade_hid').value = dados.localidade;
        document.getElementById('uf_hid').value = dados.uf;
        document.getElementById('cidade_uf').value = `${dados.localidade} / ${dados.uf}`;

        // Aplica NBR IMEDIATAMENTE — sem esperar nada
        const chave = Object.keys(referenciaNBR).find(c => 
            c.toLowerCase() === dados.localidade.toLowerCase()
        );
        if (chave) {
            aplicarValores(referenciaNBR[chave], 'nbr', `NBR 16401-1 (${chave})`);
        }

        // Busca coordenadas e climas em paralelo
        document.getElementById('avisoClima').style.display = 'block';
        document.getElementById('avisoClima').textContent = '⏳ Buscando dados reais...';
        await buscarCoordenadas(dados.localidade, dados.uf, dados.logradouro);

    } catch (e) {
        console.error('Erro ViaCEP:', e);
    }
}

async function buscarCoordenadas(cidade, uf, logradouro) {
    try {
        let consulta = cidade;
        if (logradouro) consulta = logradouro + ', ' + cidade;
        
        const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(consulta + ', ' + uf + ', Brasil')}&format=json&limit=1`, {
            headers: { 'Accept-Language': 'pt-BR,pt;q=0.9' }
        });
        const dados = await res.json();

        if (dados.length > 0) {
            const lat = parseFloat(dados[0].lat);
            const lon = parseFloat(dados[0].lon);
            
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lon.toFixed(6);
            document.getElementById('coord_texto').textContent = `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
            document.getElementById('coordenadas').style.display = 'block';

            // Disparar fontes em paralelo — Open-Meteo responde rápido
            await buscarOpenMeteo(lat, lon);
        } else {
            document.getElementById('avisoClima').textContent = '⚠️ Usando referência NBR';
            setTimeout(() => document.getElementById('avisoClima').style.display = 'none', 3000);
        }
    } catch (e) {
        console.error('Erro localização:', e);
        document.getElementById('avisoClima').textContent = '⚠️ Usando referência NBR';
        setTimeout(() => document.getElementById('avisoClima').style.display = 'none', 3000);
    }
}

async function buscarOpenMeteo(lat, lon) {
    try {
        const res = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m&daily=temperature_2m_max,temperature_2m_min&timezone=America/Sao_Paulo&forecast_days=7`);
        
        if (!res.ok) throw new Error('Indisponível');
        const dados = await res.json();
        
        if (dados.current && dados.daily) {
            const tempMaxSemana = Math.max(...dados.daily.temperature_2m_max);
            const tempProjeto = Math.round((tempMaxSemana + 1.5) * 10) / 10;
            const umidadeAtual = Math.round(dados.current.relative_humidity_2m * 10) / 10;
            const altitude = dados.elevation || 0;

            aplicarValores(
                { temp: tempProjeto, ur: umidadeAtual, alt: altitude },
                'openmeteo',
                'Open-Meteo ✅'
            );
        }
    } catch (e) {
        console.warn('Open-Meteo falhou:', e.message);
        document.getElementById('avisoClima').textContent = '✅ Usando referência NBR 16401-1';
        setTimeout(() => document.getElementById('avisoClima').style.display = 'none', 3000);
    }
}
</script>

</body>
</html>
