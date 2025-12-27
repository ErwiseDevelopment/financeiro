<?php
// Exemplo de cálculo de fatura
$data_compra = $_POST['contavencimento']; // Data que a compra foi feita
$dia_compra = (int)date('d', strtotime($data_compra));
$carto_id = $_POST['cartoid'];

// Busca dados do cartão (dia de fechamento)
$stmt = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ?");
$stmt->execute([$carto_id]);
$cartao = $stmt->fetch();

$data_vencimento = new DateTime($data_compra);

// Se o dia da compra for maior ou igual ao fechamento, cai no mês seguinte
if ($dia_compra >= $cartao['cartofechamento']) {
    $data_vencimento->modify('+1 month');
}

// Define a competência da fatura (mês/ano que será pago)
$competencia_fatura = $data_vencimento->format('Y-m');
?>