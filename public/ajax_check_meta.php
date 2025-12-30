<?php
// ajax_check_meta.php
require_once "../config/database.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuarioid'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Sessão expirada']);
    exit;
}

$uid = $_SESSION['usuarioid'];
$cat_id = $_POST['categoria_id'] ?? 0;

// SE A DATA VIER VAZIA, ASSUME HOJE
$data_raw = $_POST['data'] ?? date('Y-m-d');
if(empty($data_raw)) $data_raw = date('Y-m-d');

$cartao_id = $_POST['cartao_id'] ?? '';

if (!$cat_id) {
    echo json_encode(['status' => 'empty']);
    exit;
}

// 1. CÁLCULO DA COMPETÊNCIA
$competencia = date('Y-m', strtotime($data_raw));

// Lógica do Cartão (Fatura)
if ($cartao_id) {
    $stmtC = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ? AND usuarioid = ?");
    $stmtC->execute([$cartao_id, $uid]);
    $fechamento = $stmtC->fetchColumn();

    if ($fechamento) {
        $dia_compra = (int)date('d', strtotime($data_raw));
        if ($dia_compra >= $fechamento) {
            $competencia = date('Y-m', strtotime('+1 month', strtotime($data_raw)));
        }
    }
}

// 2. BUSCA A META (DEBUG ATIVADO)
$meta = 0;
$origem = "Nenhuma";

// Tenta achar a meta MENSAL (exata)
$stmtMensal = $pdo->prepare("
    SELECT valor FROM categorias_metas 
    WHERE categoriaid = ? AND usuarioid = ? AND competencia = ?
");
$stmtMensal->execute([$cat_id, $uid, $competencia]);
$meta_mensal = $stmtMensal->fetchColumn();

if ($meta_mensal > 0) {
    $meta = (float)$meta_mensal;
    $origem = "Mensal ($competencia)";
} else {
    // Tenta achar a meta FIXA
    $stmtFixa = $pdo->prepare("SELECT categoriameta FROM categorias WHERE categoriaid = ? AND usuarioid = ?");
    $stmtFixa->execute([$cat_id, $uid]);
    $meta_fixa = $stmtFixa->fetchColumn();
    
    if ($meta_fixa > 0) {
        $meta = (float)$meta_fixa;
        $origem = "Fixa (Padrão)";
    }
}

// Se não achou meta nenhuma, retorna aviso
if ($meta <= 0) {
    echo json_encode([
        'status' => 'no_meta', 
        'debug_data_recebida' => $data_raw,
        'debug_competencia_buscada' => $competencia
    ]);
    exit;
}

// 3. CÁLCULO DO GASTO
$stmtGasto = $pdo->prepare("
    SELECT COALESCE(SUM(contavalor), 0)
    FROM contas 
    WHERE categoriaid = ? 
    AND usuarioid = ? 
    AND (contatipo = 'Saída' OR contatipo = 'Despesa')
    AND COALESCE(competenciafatura, contacompetencia) = ?
");
$stmtGasto->execute([$cat_id, $uid, $competencia]);
$gasto = (float)$stmtGasto->fetchColumn();

$mes_label = ucfirst((new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy'))->format(strtotime($competencia."-01")));

echo json_encode([
    'status' => 'success',
    'meta' => $meta,
    'gasto' => $gasto,
    'competencia_label' => $mes_label,
    'percentual' => ($gasto / $meta) * 100,
    'disponivel' => $meta - $gasto,
    // Debug para você ver no console
    'debug_info' => [
        'data_form' => $data_raw,
        'competencia_final' => $competencia,
        'origem_meta' => $origem
    ]
]);
?>