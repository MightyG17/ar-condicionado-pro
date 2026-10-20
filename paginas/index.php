<?php 
include __DIR__ . "/../config/config.php";
$sql = "SELECT * FROM aplicacoes ORDER BY ordem_exibicao ASC";
$result = $conn->query($sql);

// Agrupar por categoria (se a coluna existir; senão, tudo em "Geral")
$categorias = [];
while ($row = $result->fetch_assoc()) {
    $cat = !empty($row["categoria"]) ? $row["categoria"] : "Geral";
    $categorias[$cat][] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aplicações de Refrigeração — RefrigeraTécnica</title>
<link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="loja.php" class="marca">❄️ Refrigera<span>Técnica</span></a>
    <nav class="menu">
      <a href="#catalogo">Catálogo</a>
      <a href="#sobre">Sobre</a>
      <a href="#contato">Contato</a>
    </nav>
  </div>
</header>

<section class="banner">
  <div class="container">
    <h1>Aplicações de Refrigeração e Ar Condicionado</h1>
    <p>Guia técnico baseado em <strong>Wilbert & Stoecker</strong> — escolha uma aplicação para ver finalidade, cálculos e normas</p>
  </div>
</section>

<section class="container barra-busca">
  <input type="text" id="busca" placeholder="🔍 Buscar aplicação, cálculo ou norma..." onkeyup="filtrar()">
</section>

<main class="container" id="catalogo">

<?php foreach ($categorias as $cat => $itens): ?>
  <section class="categoria" data-categoria>
    <h2 class="categoria-titulo"><?=htmlspecialchars($cat)?></h2>
    <div class="grid-posters">

    <?php foreach ($itens as $a): 
        $calculos = array_filter(array_map('trim', explode('|', $a['calculos'] ?? '')));
        $icone = !empty($a["icone"]) ? $a["icone"] : "❄️";
        $busca = strtolower($a["nome"].' '.$a["codigo"].' '.($a["finalidade"]??'').' '.($a["calculos"]??'').' '.($a["normas_referencia"]??''));
    ?>
      <a href="aplicacao.php?id=<?=$a["id"]?>" 
         class="poster" 
         data-busca="<?=htmlspecialchars($busca)?>">

        <div class="poster-capa">
          <span class="poster-icone"><?=htmlspecialchars($icone)?></span>
          <span class="poster-codigo"><?=htmlspecialchars($a["codigo"])?></span>
          <span class="poster-categoria"><?=htmlspecialchars($cat)?></span>
        </div>

        <div class="poster-info">
          <h3 class="poster-titulo"><?=htmlspecialchars($a["nome"])?></h3>

          <div class="poster-meta">
            <span>🌡️ <?=$a["temp_interna_min"]?>° a <?=$a["temp_interna_max"]?>°C</span>
            <span>💧 <?=$a["umid_interna_min"]?>–<?=$a["umid_interna_max"]?>%</span>
          </div>

          <?php if ($calculos): ?>
          <div class="poster-tags">
            <?php foreach (array_slice($calculos, 0, 3) as $c): ?>
              <span class="tag"><?=htmlspecialchars($c)?></span>
            <?php endforeach; ?>
            <?php if (count($calculos) > 3): ?>
              <span class="tag tag-mais">+<?=count($calculos)-3?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <span class="poster-ver">Ver detalhes →</span>
      </a>
    <?php endforeach; ?>

    </div>
  </section>
<?php endforeach; ?>

<p id="sem-resultado" style="display:none; text-align:center; padding:60px 20px; color:#64748b; font-size:1.1rem;">
  Nenhuma aplicação encontrada para sua busca.
</p>

</main>

<footer class="rodape" id="contato">
  <div class="container">
    <p>© <?=date('Y')?> RefrigeraTécnica — Baseado em Wilbert & Stoecker</p>
  </div>
</footer>

<script>
function filtrar() {
  const termo = document.getElementById('busca').value.toLowerCase().trim();
  let visiveis = 0;
  document.querySelectorAll('.poster').forEach(p => {
    const bate = !termo || p.dataset.busca.includes(termo);
    p.style.display = bate ? '' : 'none';
    if (bate) visiveis++;
  });
  document.querySelectorAll('[data-categoria]').forEach(sec => {
    const alguma = [...sec.querySelectorAll('.poster')].some(p => p.style.display !== 'none');
    sec.style.display = alguma ? '' : 'none';
  });
  document.getElementById('sem-resultado').style.display = visiveis ? 'none' : 'block';
}
</script>

</body>
</html>
