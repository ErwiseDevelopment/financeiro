<?php
require_once "config/database.php"; // Ajuste o caminho conforme sua estrutura

// Seleciona todas as contas fixas de todos os usuários
$stmt = $pdo->prepare("SELECT * FROM contas WHERE contafixa = 1");
$stmt->execute();
$contas_fixas = $stmt->fetchAll();

$mes_seguinte = date('Y-m', strtotime('+1 month'));
$criadas = 0;

foreach ($contas_fixas as $conta) {
    // Verifica se esta conta já foi clonada para o mês seguinte para evitar duplicidade
    $check = $pdo->prepare("SELECT contasid FROM contas WHERE usuarioid = ? AND contadescricao = ? AND contacompetencia = ?");
    $check->execute([$conta['usuarioid'], $conta['contadescricao'], $mes_seguinte]);
    
    if (!$check->fetch()) {
        // Calcula a nova data de vencimento (mesmo dia, mês seguinte)
        $data_venc = new DateTime($conta['contavencimento']);
        $dia = $data_venc->format('d');
        $nova_data = $mes_seguinte . "-" . $dia;

        // Insere a cópia
        $ins = $pdo->prepare("INSERT INTO contas 
            (usuarioid, categoriaid, contadescricao, contavalor, contavencimento, contacompetencia, contatipo, contafixa, cartoid, contasituacao) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'Pendente')");
        
        $ins->execute([
            $conta['usuarioid'], 
            $conta['categoriaid'], 
            $conta['contadescricao'], 
            $conta['contavalor'], 
            $nova_data, 
            $mes_seguinte, 
            $conta['contatipo'], 
            $conta['cartoid']
        ]);
        $criadas++;
    }
}

echo "Automação concluída: $criadas novas contas geradas para $mes_seguinte.";