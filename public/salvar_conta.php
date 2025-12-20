<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['usuarioid'])) {
    $uid         = $_SESSION['usuarioid'];
    $descricao   = $_POST['contadescricao'];
    $valor       = $_POST['contavalor'];
    $vencimento  = $_POST['contavencimento'];
    $parcelas    = (int)$_POST['contaparcela_total'];
    $tipo        = $_POST['contatipo'];
    $categoria   = $_POST['categoriaid'];
    $fixa        = isset($_POST['contafixa']) ? 1 : 0;
    $grupo_id    = bin2hex(random_bytes(8)); 

    try {
        $pdo->beginTransaction();
        for ($i = 0; $i < $parcelas; $i++) {
            // Calcula vencimento: adiciona $i meses à data inicial
            $data_venc   = date('Y-m-d', strtotime("+$i month", strtotime($vencimento)));
            $competencia = date('Y-m', strtotime($data_venc));
            
            $sql = $pdo->prepare("INSERT INTO contas 
                (usuarioid, categoriaid, contadescricao, contavalor, contavencimento, contacompetencia, contaparcela_atual, contaparcela_total, contagrupoid, contatipo, contafixa) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $sql->execute([$uid, $categoria, $descricao, $valor, $data_venc, $competencia, ($i + 1), $parcelas, $grupo_id, $tipo, $fixa]);
        }
        $pdo->commit();
        header("Location: index.php?sucesso=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao projetar contas: " . $e->getMessage());
    }
}