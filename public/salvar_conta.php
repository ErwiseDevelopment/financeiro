<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $tipo = $_POST['contatipo'];
    $valor = $_POST['contavalor']; // Recebe o valor limpo (ex: 1250.50) do campo hidden
    $descricao = $_POST['contadescricao'];
    $categoriaid = $_POST['categoriaid'];
    $data_vencimento_original = $_POST['contavencimento'];
    $parcelas_total = (int)$_POST['contaparcela_total'];
    $situacao = "Pendente"; // Todo lançamento novo nasce pendente

    try {
        $pdo->beginTransaction();

        for ($i = 1; $i <= $parcelas_total; $i++) {
            // Calcula a data de vencimento para a parcela atual
            // P1 = Data Original, P2 = +1 mês, etc.
            $meses_adicionar = $i - 1;
            $data_vencimento = date('Y-m-d', strtotime("+$meses_adicionar month", strtotime($data_vencimento_original)));
            
            // Define a competência (YYYY-MM) com base no vencimento da parcela
            $competencia = date('Y-m', strtotime($data_vencimento));

            // Ajusta a descrição para mostrar a parcela (ex: Internet 1/3)
            $descricao_parcelada = ($parcelas_total > 1) ? $descricao . " ($i/$parcelas_total)" : $descricao;

            $sql = "INSERT INTO contas (usuarioid, contatipo, contavalor, contadescricao, categoriaid, contavencimento, contacompetencia, contasituacao, contaparcela_num, contaparcela_total) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $uid, 
                $tipo, 
                $valor, 
                $descricao_parcelada, 
                $categoriaid, 
                $data_vencimento, 
                $competencia, 
                $situacao,
                $i, // Número da parcela atual
                $parcelas_total // Total de parcelas
            ]);
        }

        $pdo->commit();
        header("Location: index.php?msg=sucesso");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erro ao salvar: " . $e->getMessage();
    }
}