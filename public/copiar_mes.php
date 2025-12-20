<?php
require_once "../config/database.php";
session_start();

if (!isset($_SESSION['usuarioid']) || !isset($_GET['mes_origem'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['usuarioid'];
$mes_origem = $_GET['mes_origem'];
// Calcula o próximo mês baseado no mês de origem
$proximo_mes = date('Y-m', strtotime("+1 month", strtotime($mes_origem . "-01")));

try {
    // Busca todas as contas do mês de origem
    $stmt = $pdo->prepare("SELECT * FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
    $stmt->execute([$uid, $mes_origem]);
    $contas = $stmt->fetchAll();

    if ($contas) {
        $pdo->beginTransaction();

        foreach ($contas as $c) {
            // Calcula a nova data de vencimento (mesma data, mas no mês seguinte)
            $nova_data = date('Y-m-d', strtotime("+1 month", strtotime($c['contavencimento'])));
            
            $sql = $pdo->prepare("INSERT INTO contas 
                (usuarioid, categoriaid, contadescricao, contavalor, contavencimento, contacompetencia, contatipo, contasituacao, contaparcela_atual, contaparcela_total, contagrupoid) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?, ?, ?)");
            
            $sql->execute([
                $uid, 
                $c['categoriaid'], 
                $c['contadescricao'], 
                $c['contavalor'], 
                $nova_data, 
                $proximo_mes, 
                $c['contatipo'],
                $c['contaparcela_atual'],
                $c['contaparcela_total'],
                $c['contagrupoid']
            ]);
        }

        $pdo->commit();
        header("Location: index.php?mes=$proximo_mes&msg=sucesso");
    } else {
        header("Location: index.php?mes=$mes_origem&msg=vazio");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erro ao copiar: " . $e->getMessage());
}