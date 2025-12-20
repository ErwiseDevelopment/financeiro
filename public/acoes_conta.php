<?php
require_once "../config/database.php";
session_start();

$id    = $_GET['id'] ?? null;
$acao  = $_GET['acao'] ?? null;
$grupo = $_GET['grupo'] ?? null;
$uid   = $_SESSION['usuarioid'];

if ($acao === 'pagar' && $id) {
    $sql = $pdo->prepare("UPDATE contas SET contasituacao = 'Pago' WHERE contasid = ? AND usuarioid = ?");
    $sql->execute([$id, $uid]);
}

if ($acao === 'estornar' && $id) {
    $sql = $pdo->prepare("UPDATE contas SET contasituacao = 'Pendente' WHERE contasid = ? AND usuarioid = ?");
    $sql->execute([$id, $uid]);
}

if ($acao === 'excluir_serie' && $grupo) {
    // Exclui todas as parcelas futuras de um mesmo grupo que ainda não foram pagas
    $sql = $pdo->prepare("DELETE FROM contas WHERE contagrupoid = ? AND usuarioid = ? AND contasituacao = 'Pendente'");
    $sql->execute([$grupo, $uid]);
}

header("Location: " . $_SERVER['HTTP_REFERER']);