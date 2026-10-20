<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
include __DIR__ . "/../config/config.php";

$projeto_id = (int)($_GET["projeto_id"] ?? 0);
if (!$projeto_id) { header("Location: residencial_projetos.php"); exit; }

$proj = $conn->query("SELECT * FROM projetos WHERE id = $projeto_id")->fetch_assoc();
if (!$proj) { header("Location: residencial_projetos.php"); exit; }

$mensagem = "";

function val($arr, $chave, $padrao = "") {
    return isset($arr[$chave]) ? $arr[$chave] : $padrao;
}

// Salvar Ambiente
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salvar_ambiente"])) {
    $nome = trim($_POST["nome_ambiente"] ?? "");
    $uso = trim($_POST["uso_principal"] ?? "Dormitório");
    $comp = (float)($_POST["comprimento_m"] ?? 0);
    $larg = (float)($_POST["largura_m"] ?? 0);
    $alt = (float)($_POST["altura_m"] ?? 2.8);
    $orient = trim($_POST["orientacao_solar"] ?? "Norte");
    $area_vidro = (float)($_POST["area_vidro_m2"] ?? 2.0);
    $pessoas = (int)($_POST["n_pessoas"] ?? 2);
    $pot_equip = (float)($_POST["potencia_equipamentos_w"] ?? 150);
    $temp_int = (float)($_POST["temp_interna"] ?? 24.0);
    $temp_ext = (float)($_POST["temp_externa"] ?? 32.0);
    $pot_ilum = (float)($_POST["potencia_iluminacao_w"] ?? 0);

    if (!empty($_POST["ambiente_id"])) {
        $id = (int)$_POST["ambiente_id"];
        $stmt = $conn->prepare("UPDATE ambientes SET nome=?, uso_principal=?, comprimento_m=?, largura_m=?, peDireita_m=?, orientacao_solar=?, area_vidro_m2=?, pessoas=?, potencia_equipamentos_w=?, temp_interna=?, temp_externa=?, potencia_iluminacao_w=? WHERE id=?");
        $stmt->bind_param("ssddssiddddi", $nome, $uso, $comp, $larg, $alt, $orient, $area_vidro, $pessoas, $pot_equip, $temp_int, $temp_ext, $pot_ilum, $id);
        $mensagem = $stmt->execute() ? "✅ Ambiente \"$nome\" atualizado!" : "❌ Erro: ".$conn->error;
    } else {
        $stmt = $conn->prepare("INSERT INTO ambientes 
            (projeto_id, nome, uso_principal, comprimento_m, largura_m, peDireita_m, orientacao_solar, area_vidro_m2, pessoas, potencia_equipamentos_w, temp_interna, temp_externa, potencia_iluminacao_w)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issddssiddddi", $projeto_id, $nome, $uso, $comp, $larg, $alt, $orient, $area_vidro, $pessoas, $pot_equip, $temp_int, $temp_ext, $pot_ilum);
        $mensagem = $stmt->execute() ? "✅ Ambiente \"$nome\" salvo!" : "❌ Erro: ".$conn->error;
    }
}

// Salvar Super Dimensionamento
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salvar_super"])) {
    $ambiente_id = (int)($_POST["ambiente_id_super"] ?? 0);
    
    if ($ambiente_id) {
        $conn->query("DELETE FROM paredes WHERE ambiente_id = $ambiente_id");
        if (!empty($_POST["parede_orientacao"])) {
            foreach ($_POST["parede_orientacao"] as $k => $orient) {
                $alt = (float)($_POST["parede_altura"][$k] ?? 0);
                $lar = (float)($_POST["parede_largura"][$k] ?? 0);
                $tipo = trim($_POST["parede_tipo"][$k] ?? "Alvenaria comum");
                $esp = (float)($_POST["parede_espessura"][$k] ?? 15.0);
                $ext = isset($_POST["parede_exterior"][$k]) ? 1 : 0;
                $area_vidro_parede = (float)($_POST["parede_area_vidro"][$k] ?? 0);
                $tipo_vidro = trim($_POST["parede_tipo_vidro"][$k] ?? "comum");
                
                $stmt = $conn->prepare("INSERT INTO paredes (ambiente_id, orientacao, altura_m, largura_m, tipo_construcao, espessura_cm, do_lado_exterior, area_vidro_m2, tipo_vidro) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("isddsdids", $ambiente_id, $orient, $alt, $lar, $tipo, $esp, $ext, $area_vidro_parede, $tipo_vidro);
                $stmt->execute();
            }
        }

        $tx = (float)($_POST["taxa_renovacao"] ?? 6.0);
        $vazao_min_nbr = (float)($_POST["nbr_vazao_minima"] ?? 30) * (int)($_POST["nbr_pessoas"] ?? 2);
        $insu = (float)($_POST["insuflamento_m3h"] ?? 0);
        $exau = (float)($_POST["exaustao_m3h"] ?? 0);
        $infiltracao = (float)($_POST["infiltracao_percentual"] ?? 10);
        $obs = trim($_POST["observacoes_ar"] ?? "");
        
        $conn->query("DELETE FROM renovacao_ar WHERE ambiente_id = $ambiente_id");
        $stmt = $conn->prepare("INSERT INTO renovacao_ar (ambiente_id, taxa_renovacao_por_hora, vazao_necessaria_m3h, vazao_nbr_minima_m3h, insuflamento_m3h, exaustao_m3h, infiltracao_percentual, observacoes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("idddddds", $ambiente_id, $tx, $vazao_min_nbr, $vazao_min_nbr, $insu, $exau, $infiltracao, $obs);
        $stmt->execute();

        $mensagem = "✅ Super Dimensionamento salvo com detalhes!";
    }
}

if (!empty($_GET["excluir"])) {
    $id = (int)$_GET["excluir"];
    $conn->query("DELETE FROM ambientes WHERE id = $id AND projeto_id = $projeto_id");
    header("Location: residencial_ambiente.php?projeto_id=$projeto_id");
    exit;
}

// Adicionar colunas se não existirem
$res = $conn->query("DESCRIBE ambientes temp_interna");
if (!$res) {
    $conn->query("ALTER TABLE ambientes ADD COLUMN temp_interna DECIMAL(4,1) DEFAULT 24.0 AFTER potencia_equipamentos_w");
    $conn->query("ALTER TABLE ambientes ADD COLUMN temp_externa DECIMAL(4,1) DEFAULT 32.0 AFTER temp_interna");
    $conn->query("ALTER TABLE ambientes ADD COLUMN potencia_iluminacao_w DECIMAL(8,2) DEFAULT 0 AFTER temp_externa");
}
$res = $conn->query("DESCRIBE paredes area_vidro_m2");
if (!$res) {
    $conn->query("ALTER TABLE paredes ADD COLUMN area_vidro_m2 DECIMAL(6,2) DEFAULT 0 AFTER do_lado_exterior");
    $conn->query("ALTER TABLE paredes ADD COLUMN tipo_vidro VARCHAR(20) DEFAULT comum AFTER area_vidro_m2");
}
$res = $conn->query("DESCRIBE renovacao_ar vazao_nbr_minima_m3h");
if (!$res) {
    $conn->query("ALTER TABLE renovacao_ar ADD COLUMN vazao_nbr_minima_m3h DECIMAL(8,2) DEFAULT 0 AFTER vazao_necessaria_m3h");
    $conn->query("ALTER TABLE renovacao_ar ADD COLUMN infiltracao_percentual DECIMAL(5,2) DEFAULT 10.0 AFTER exaustao_m3h");
}

$ambientes = $conn->query("SELECT * FROM ambientes WHERE projeto_id = $projeto_id ORDER BY id DESC");
$editarAmb = null;
$paredesAmb = [];
$renovacaoAmb = null;
if (!empty($_GET["editar"])) {
    $editarAmb = $conn->query("SELECT * FROM ambientes WHERE id = ".(int)$_GET["editar"])->fetch_assoc();
    $paredesAmb = $conn->query("SELECT * FROM paredes WHERE ambiente_id = ".(int)$_GET["editar"])->fetch_all(MYSQLI_ASSOC);
    $renovacaoAmb = $conn->query("SELECT * FROM renovacao_ar WHERE ambiente_id = ".(int)$_GET["editar"])->fetch_assoc();
}

// === FUNÇÕES DE CÁLCULO — ABNT NBR 16401 ===

function coeficienteU($tipo) {
    $valores = [
        "Alvenaria comum" => 1.7,
        "Alvenaria dupla" => 1.0,
        "Concreto" => 2.5,
        "Madeira" => 1.0,
        "Fachada leve" => 1.4,
        "Laje com forro" => 1.0,
        "Telha sem isolamento" => 2.5,
        "Piso sobre solo" => 1.0,
    ];
    return $valores[$tipo] ?? 1.7;
}

function fatorSolar($orientacao, $vidro = "comum") {
    $mapa = [
        "Norte" => "N", "Nordeste" => "NE", "Noroeste" => "NW",
        "Sul" => "S", "Sudeste" => "SE", "Sudoeste" => "SW",
        "Leste" => "L", "Oeste" => "O",
        "N" => "N", "NE" => "NE", "NW" => "NW",
        "S" => "S", "SE" => "SE", "SW" => "SW",
        "L" => "L", "O" => "O",
    ];
    $chave = $mapa[$orientacao] ?? "N";
    
    $fatores = [
        "N"  => ["comum" => 180, "refletivo" => 110, "duplo" => 130],
        "NE" => ["comum" => 230, "refletivo" => 140, "duplo" => 165],
        "NW" => ["comum" => 230, "refletivo" => 140, "duplo" => 165],
        "S"  => ["comum" => 120, "refletivo" => 70, "duplo" => 90],
        "SE" => ["comum" => 255, "refletivo" => 150, "duplo" => 180],
        "SW" => ["comum" => 280, "refletivo" => 160, "duplo" => 195],
        "L"  => ["comum" => 280, "refletivo" => 160, "duplo" => 195],
        "O"  => ["comum" => 320, "refletivo" => 190, "duplo" => 220],
    ];
    return $fatores[$chave][$vidro] ?? 180;
}

function calcularCarga($amb, $proj, $paredes = [], $renov = null) {
    $res = ["passos" => [], "total_w" => 0, "btu" => 0];
    
    $comp = val($amb, "comprimento_m", 0);
    $larg = val($amb, "largura_m", 0);
    $alt = val($amb, "peDireita_m", 2.8);
    $pessoas = val($amb, "pessoas", 2);
    $pot_equip = val($amb, "potencia_equipamentos_w", 0);
    $pot_ilum = val($amb, "potencia_iluminacao_w", 0);
    $temp_int = val($amb, "temp_interna", 24.0);
    $temp_ext = val($amb, "temp_externa", 32.0);
    $deltaT = $temp_ext - $temp_int;
    $volume = $comp * $larg * $alt;
    $area_piso_teto = $comp * $larg;
    
    $res["passos"]["dados"] = [
        "titulo" => "📋 Dados do Ambiente",
        "itens" => [
            "Dimensões: {$comp} × {$larg} × {$alt} m",
            "Área: ".number_format($comp*$larg, 1, ",", ".")." m²",
            "Volume: ".number_format($volume, 1, ",", ".")." m³",
            "Temperatura interna: {$temp_int} °C",
            "Temperatura externa: {$temp_ext} °C",
            "ΔT = {$deltaT} °C",
            "Ocupação: {$pessoas} pessoas",
        ]
    ];

    // Passo 1 — Condução
    $ganho_conducao = 0;
    $itens_paredes = [];
    
    if (!empty($paredes)) {
        foreach ($paredes as $p) {
            $orient = $p["orientacao"];
            $alt_p = $p["altura_m"];
            $larg_p = $p["largura_m"];
            $tipo_p = $p["tipo_construcao"];
            $area_vid_p = val($p, "area_vidro_m2", 0);
            $lado_ext = $p["do_lado_exterior"];
            
            $area_total = $alt_p * $larg_p;
            $area_opaca = max(0, $area_total - $area_vid_p);
            $U = $lado_ext ? coeficienteU($tipo_p) : 0.5;
            $q_parede = $area_opaca * $U * $deltaT;
            $ganho_conducao += $q_parede;
            
            $itens_paredes[] = "Parede {$orient}: {$alt_p}×{$larg_p} = ".number_format($area_total,1,",",".").
