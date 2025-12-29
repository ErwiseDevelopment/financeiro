<?php
require_once "../config/database.php";
session_start();

$id    = $_GET['id'] ?? null;
$acao  = $_GET['acao'] ?? null;
$grupo = $_GET['grupo'] ?? null;
$uid   = $_SESSION['usuarioid'];

// AÇÃO DE EXCLUIR ÚNICA (Adicione este bloco)
if ($acao === 'excluir' && $id) {
    $sql = $pdo->prepare("DELETE FROM contas WHERE contasid = ? AND usuarioid = ?");
    $sql->execute([$id, $uid]);
}

if ($acao === 'pagar' && $id) {
    $sql = $pdo->prepare("UPDATE contas SET contasituacao = 'Pago' WHERE contasid = ? AND usuarioid = ?");
    $sql->execute([$id, $uid]);
}

if ($acao === 'estornar' && $id) {
    $sql = $pdo->prepare("UPDATE contas SET contasituacao = 'Pendente' WHERE contasid = ? AND usuarioid = ?");
    $sql->execute([$id, $uid]);
}

if ($acao === 'excluir' && $id) {
    // 1. Primeiro, buscamos se essa conta específica tem um contagrupoid ou contadescricao similar
    $stmt = $pdo->prepare("SELECT contagrupoid, contadescricao, contaparcela_total FROM contas WHERE contasid = ? AND usuarioid = ?");
    $stmt->execute([$id, $uid]);
    $conta = $stmt->fetch();

    if ($conta) {
        // Se a conta for parcelada (contaparcela_total > 1)
        if ($conta['contaparcela_total'] > 1) {
            // Removemos o sufixo da parcela (ex: "Compra (1/5)" vira "Compra") para garantir
            $descricao_base = preg_replace('/\s\(\d+\/\d+\)$/', '', $conta['contadescricao']);
            
            // Deleta todas as contas que tenham a mesma descrição base e sejam do mesmo usuário
            // OU que compartilhem o mesmo agrupoid (se você estiver usando essa coluna)
            $sql_del = $pdo->prepare("DELETE FROM contas WHERE usuarioid = ? AND (contadescricao LIKE ? OR (contagrupoid = ? AND contagrupoid IS NOT NULL))");
            $sql_del->execute([$uid, $descricao_base . '%', $conta['contagrupoid']]);
        } else {
            // Se não for parcelada, deleta apenas ela
            $sql_del = $pdo->prepare("DELETE FROM contas WHERE contasid = ? AND usuarioid = ?");
            $sql_del->execute([$id, $uid]);
        }
    }
}
// Retorna para a página anterior
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;