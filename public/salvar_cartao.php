<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $nome = $_POST['cartonome'];
    $fechamento = $_POST['cartofechamento'];
    $vencimento = $_POST['cartovencimento'];
    $limite = $_POST['cartolimite'] ?? 0;

    $sql = "INSERT INTO cartoes (usuarioid, cartonome, cartofechamento, cartovencimento, cartolimite) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$uid, $nome, $fechamento, $vencimento, $limite])) {
        header("Location: dashboard.php?msg=cartao_sucesso");
    } else {
        echo "Erro ao cadastrar cartão.";
    }
}