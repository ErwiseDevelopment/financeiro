<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    
    $cartao_id = $_POST['cartao_id'];
    $competencia = $_POST['competencia'];
    
    // Apenas para fins de registro no banco (se um dia quiser usar), 
    // podemos atualizar a data de pagamento real dos itens
    $data_pagto = $_POST['data_pagamento']; 

    try {
        $pdo->beginTransaction();

        // 1. ATUALIZAR STATUS PARA 'PAGO'
        $sql_update = "UPDATE contas 
                       SET contasituacao = 'Pago' 
                       WHERE usuarioid = ? 
                       AND cartoid = ? 
                       AND COALESCE(competenciafatura, contacompetencia) = ? 
                       AND contasituacao = 'Pendente'";
        
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([$uid, $cartao_id, $competencia]);
        
        $pdo->commit();
        header("Location: faturas.php?cartoid=$cartao_id&mes=$competencia&msg=fatura_paga");

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao processar pagamento: " . $e->getMessage());
    }
}
?>