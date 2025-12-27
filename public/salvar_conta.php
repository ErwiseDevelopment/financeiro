<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $contadescricao = $_POST['contadescricao'];
    $contavalor = $_POST['contavalor'];
    $contatipo = $_POST['contatipo'];
    $categoriaid = $_POST['categoriaid'];
    $contavencimento = $_POST['contavencimento'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;
    $contafixa = isset($_POST['contafixa']) ? 1 : 0; // Captura o novo campo
    $parcelas_total = (int)$_POST['contaparcela_total'];

    try {
        $pdo->beginTransaction();

        for ($i = 1; $i <= $parcelas_total; $i++) {
            // Calcula a data de vencimento e a competência para cada parcela
            $data = new DateTime($contavencimento);
            if ($i > 1) {
                $data->modify('+' . ($i - 1) . ' month');
            }
            
            $vencimento_parcela = $data->format('Y-m-d');
            $competencia = $data->format('Y-m');
            
            // Se for parcelado, ajusta a descrição para "Nome da Conta (1/12)"
            $desc_final = ($parcelas_total > 1) ? $contadescricao . " ($i/$parcelas_total)" : $contadescricao;

            $sql = "INSERT INTO contas (
                usuarioid, categoriaid, contadescricao, contavalor, 
                contavencimento, contacompetencia, contatipo, 
                contafixa, cartoid, contaparcela_num, contaparcela_total, contasituacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $uid,
                $categoriaid,
                $desc_final,
                $contavalor,
                $vencimento_parcela,
                $competencia,
                $contatipo,
                $contafixa,
                $cartoid,
                $i,
                $parcelas_total
            ]);
        }

        $pdo->commit();
        
        // Redireciona para o mês da primeira parcela
        $mes_inicial = date('Y-m', strtotime($contavencimento));
        header("Location: index.php?mes=$mes_inicial&msg=Lançamento realizado com sucesso!");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar: " . $e->getMessage());
    }
}