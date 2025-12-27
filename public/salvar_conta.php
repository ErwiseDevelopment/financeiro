<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $tipo = $_POST['contatipo'];
    $valor_total = (float)$_POST['contavalor']; // Ex: 1000.00
    $descricao = $_POST['contadescricao'];
    $categoriaid = $_POST['categoriaid'];
    $data_compra = $_POST['contavencimento'];
    $parcelas_total = (int)$_POST['contaparcela_total'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;

    // Cálculo do valor por parcela
    $valor_parcela = $valor_total / $parcelas_total;

    // Se for cartão, precisamos saber o dia de fechamento
    $dia_fechamento = 0;
    if ($cartoid) {
        $st = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ?");
        $st->execute([$cartoid]);
        $dia_fechamento = (int)$st->fetchColumn();
    }

    try {
        $pdo->beginTransaction();

        for ($i = 1; $i <= $parcelas_total; $i++) {
            $meses_a_frente = $i - 1;
            $data_parcela = new DateTime($data_compra);
            $data_parcela->modify("+$meses_a_frente month");
            
            $dia_da_compra = (int)$data_parcela->format('d');
            $competencia_obj = clone $data_parcela;

            // Lógica de Fechamento de Fatura
            if ($cartoid && $dia_da_compra >= $dia_fechamento) {
                $competencia_obj->modify('+1 month');
            }

            $competencia = $competencia_obj->format('Y-m');
            $vencimento_db = $data_parcela->format('Y-m-d');
            
            // Texto da descrição para parcelado: "Compra (1/10)"
            $desc_final = ($parcelas_total > 1) ? $descricao . " ($i/$parcelas_total)" : $descricao;

            $sql = "INSERT INTO contas (usuarioid, contatipo, contavalor, contadescricao, categoriaid, contavencimento, contacompetencia, contasituacao, contaparcela_num, contaparcela_total, cartoid) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $uid, $tipo, $valor_parcela, $desc_final, $categoriaid, 
                $vencimento_db, $competencia, $i, $parcelas_total, $cartoid
            ]);
        }

        $pdo->commit();
        header("Location: index.php?msg=sucesso");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar: " . $e->getMessage());
    }
}