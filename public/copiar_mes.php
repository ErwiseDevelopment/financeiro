<?php
require_once "../config/database.php";
session_start();

$uid = $_SESSION['usuarioid'];
$mes_origem = $_GET['mes_origem'] ?? date('Y-m');

// 1. CALCULA O MÊS DE DESTINO (Próximo mês)
$data_obj = new DateTime($mes_origem . "-01");
$data_obj->modify('+1 month');
$mes_destino = $data_obj->format('Y-m');

try {
    // 2. VERIFICAÇÃO DE SEGURANÇA: Já existem lançamentos "copiados" no mês de destino?
    // Usamos uma técnica de marcar ou apenas contar se o mês de destino já tem dados para evitar duplicidade total.
    $check = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
    $check->execute([$uid, $mes_destino]);
    $ja_existe = $check->fetchColumn();

    if ($ja_existe > 0) {
        // Se já existem contas no mês seguinte, redireciona com aviso para não duplicar
        header("Location: index.php?mes=$mes_origem&erro=ja_copiado");
        exit();
    }

    // 3. BUSCA OS LANÇAMENTOS DO MÊS DE ORIGEM
    // Pegamos apenas o que faz sentido repetir (contas fixas, etc.) 
    // ou tudo conforme sua preferência. Aqui pegaremos todos.
    $stmt = $pdo->prepare("SELECT * FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
    $stmt->execute([$uid, $mes_origem]);
    $contas = $stmt->fetchAll();

    if (empty($contas)) {
        header("Location: index.php?mes=$mes_origem&erro=sem_dados");
        exit();
    }

    $pdo->beginTransaction();

    foreach ($contas as $c) {
        // 4. PREPARA A NOVA DATA DE VENCIMENTO
        $venc_origem = new DateTime($c['contavencimento']);
        $venc_origem->modify('+1 month');
        $novo_vencimento = $venc_origem->format('Y-m-d');

        // 5. INSERE O NOVO REGISTRO
        $ins = $pdo->prepare("INSERT INTO contas 
            (usuarioid, contatipo, contavalor, contadescricao, categoriaid, contavencimento, contacompetencia, contasituacao) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $ins->execute([
            $uid,
            $c['contatipo'],
            $c['contavalor'],
            $c['contadescricao'],
            $c['categoriaid'],
            $novo_vencimento,
            $mes_destino,
            'Pendente' // Sempre nasce como pendente no mês novo
        ]);
    }

    $pdo->commit();
    header("Location: index.php?mes=$mes_destino&msg=copiado_sucesso");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erro ao processar cópia: " . $e->getMessage());
}