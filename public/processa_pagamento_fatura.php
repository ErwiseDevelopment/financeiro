<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $cartoid = $_POST['cartoid'];
    $mes_fatura = $_POST['mes_fatura']; // Ex: 2025-12
    $valor_pago = (float)str_replace(',', '.', $_POST['valor_pagamento']);
    $total_fatura = (float)$_POST['total_fatura'];

    try {
        $pdo->beginTransaction();

        // 1. Buscamos todos os lançamentos abertos deste cartão neste mês
        $sql_select = "SELECT * FROM contas WHERE usuarioid = ? AND cartoid = ? AND contacompetencia = ? AND contasituacao = 'Pendente'";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute([$uid, $cartoid, $mes_fatura]);
        $contas = $stmt_select->fetchAll();

        if ($valor_pago >= $total_fatura) {
            // --- PAGAMENTO INTEGRAL ---
            $sql_update = "UPDATE contas SET contasituacao = 'Pago' WHERE usuarioid = ? AND cartoid = ? AND contacompetencia = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$uid, $cartoid, $mes_fatura]);
        } else {
            // --- PAGAMENTO PARCIAL (ROLAGEM) ---
            
            // 1. Marcamos os itens atuais como 'Pago' (pois a dívida original foi "refinanciada")
            $sql_update = "UPDATE contas SET contasituacao = 'Pago' WHERE usuarioid = ? AND cartoid = ? AND contacompetencia = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$uid, $cartoid, $mes_fatura]);

            // 2. Calcula o resto
            $residuo = $total_fatura - $valor_pago;

            // 3. Define a data do próximo mês
            $data_obj = new DateTime($mes_fatura . "-01");
            $data_obj->modify('+1 month');
            $proxima_competencia = $data_obj->format('Y-m');
            
            // Buscamos o dia de vencimento do cartão para colocar na data de vencimento da conta residual
            $st_c = $pdo->prepare("SELECT cartovencimento FROM cartoes WHERE cartoid = ?");
            $st_c->execute([$cartoid]);
            $dia_venc = $st_c->fetchColumn();
            $data_vencimento_residuo = $data_obj->format("Y-m-$dia_venc");

            // 4. Insere o saldo devedor no próximo mês
            $sql_ins = "INSERT INTO contas (usuarioid, contatipo, contavalor, contadescricao, categoriaid, contavencimento, contacompetencia, contasituacao, cartoid) 
                        VALUES (?, 'Saída', ?, ?, ?, ?, ?, 'Pendente', ?)";
            
            $stmt_ins = $pdo->prepare($sql_ins);
            $stmt_ins->execute([
                $uid,
                $residuo,
                "Resíduo de Fatura Anterior (" . date('m/Y', strtotime($mes_fatura."-01")) . ")",
                1, // ID de uma categoria genérica ou 'Encargos'
                $data_vencimento_residuo,
                $proxima_competencia,
                $cartoid
            ]);
        }

        $pdo->commit();
        header("Location: faturas.php?mes=$mes_fatura&cartoid=$cartoid&msg=pagamento_registrado");

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro: " . $e->getMessage());
    }
}