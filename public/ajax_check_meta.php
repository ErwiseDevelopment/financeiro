<?php
require_once "../config/database.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuarioid'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Sessão expirada']);
    exit;
}

$uid = $_SESSION['usuarioid'];
$cat_id = $_POST['categoria_id'] ?? 0;
$data_compra_post = $_POST['data'] ?? date('Y-m-d');
$cartao_id = $_POST['cartao_id'] ?? '';

if (!$cat_id) {
    echo json_encode(['status' => 'empty']);
    exit;
}

// ---------------------------------------------------------
// 1. DEFINIR A COMPETÊNCIA (O MÊS DA META)
// ---------------------------------------------------------
$data_obj = new DateTime($data_compra_post);
$dia_compra = (int)$data_obj->format('d');

// Por padrão, a competência é o próprio mês da compra
$competencia = $data_obj->format('Y-m');

// SE TEM CARTÃO, APLICA REGRA DE FATURA
if ($cartao_id) {
    $stmtC = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ? AND usuarioid = ?");
    $stmtC->execute([$cartao_id, $uid]);
    $fechamento_db = $stmtC->fetchColumn();

    if ($fechamento_db) {
        $dia_fechamento = (int)$fechamento_db;

        // REGRA DE CORTE (Ex: Fechamento dia 29)
        // Compra dia 28/12 -> Menor que 29 -> Fatura Jan/24 (+1 mês)
        // Compra dia 29/12 -> Igual/Maior 29 -> Fatura Fev/24 (+2 meses)
        
        if ($dia_compra >= $dia_fechamento) {
            // Pula para o mês seguinte do seguinte (+2 meses da data base)
            // Ex: Dez -> Fev
            $data_obj->modify('first day of +2 months');
        } else {
            // Vai para o próximo mês (+1 mês da data base)
            // Ex: Dez -> Jan
            $data_obj->modify('first day of next month');
        }
        
        $competencia = $data_obj->format('Y-m');
    }
}

// ---------------------------------------------------------
// 2. BUSCAR A META (Lógica Direta)
// ---------------------------------------------------------
$meta = 0;
$tipo_meta = "Nenhuma";

// TENTATIVA A: Buscar na tabela de metas mensais para ESTA competência exata
$stmtMensal = $pdo->prepare("
    SELECT valor 
    FROM categorias_metas 
    WHERE categoriaid = ? 
    AND usuarioid = ? 
    AND competencia = ? 
    LIMIT 1
");
$stmtMensal->execute([$cat_id, $uid, $competencia]);
$resultado_mensal = $stmtMensal->fetchColumn();

if ($resultado_mensal !== false && $resultado_mensal > 0) {
    $meta = (float)$resultado_mensal;
    $tipo_meta = "Mensal ($competencia)";
} else {
    // TENTATIVA B: Se não achou mensal, pega a fixa da categoria
    $stmtFixa = $pdo->prepare("SELECT categoriameta FROM categorias WHERE categoriaid = ? AND usuarioid = ?");
    $stmtFixa->execute([$cat_id, $uid]);
    $resultado_fixo = $stmtFixa->fetchColumn();
    
    if ($resultado_fixo > 0) {
        $meta = (float)$resultado_fixo;
        $tipo_meta = "Fixa";
    }
}

// Se não tem meta nenhuma, para por aqui
if ($meta <= 0) {
    echo json_encode(['status' => 'no_meta', 'debug_comp' => $competencia]);
    exit;
}

// ---------------------------------------------------------
// 3. CALCULAR O TOTAL GASTO NESSE PERÍODO
// ---------------------------------------------------------
// Aqui somamos tudo que caiu nessa competência (seja por fatura ou data direta)
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

// ---------------------------------------------------------
// 4. RETORNO
// ---------------------------------------------------------
$mes_label = ucfirst((new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy'))->format(strtotime($competencia."-01")));

echo json_encode([
    'status' => 'success',
    'meta' => $meta,
    'gasto' => $gasto,
    'competencia_label' => $mes_label,
    'percentual' => ($gasto / $meta) * 100,
    'disponivel' => $meta - $gasto,
    'debug_origem' => $tipo_meta
]);
?>