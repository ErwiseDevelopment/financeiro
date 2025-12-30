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
    $contafixa = isset($_POST['contafixa']) ? 1 : 0;
    $parcelas_total = (int)$_POST['contaparcela_total'];

    try {
        $pdo->beginTransaction();

        // --- LÓGICA DE FECHAMENTO DO CARTÃO ---
        $dia_fechamento = 0; 
        if ($cartoid) {
            $stmt_cartao = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ? AND usuarioid = ?");
            $stmt_cartao->execute([$cartoid, $uid]);
            $res_cartao = $stmt_cartao->fetch();
            $dia_fechamento = (int)($res_cartao['cartofechamento'] ?? 1);
        }

        // --- DEFINIÇÃO DE REPETIÇÕES ---
        $limite_repeticao = ($contafixa == 1 && $parcelas_total <= 1) ? 12 : $parcelas_total;

        for ($i = 1; $i <= $limite_repeticao; $i++) {
            $data = new DateTime($contavencimento);
            $dia_original = (int)$data->format('d'); 

            if ($i > 1) {
                $data->modify('first day of +' . ($i - 1) . ' month');
                $ultimo_dia_mes = (int)$data->format('t');
                $novo_dia = min($dia_original, $ultimo_dia_mes);
                $data->setDate((int)$data->format('Y'), (int)$data->format('m'), $novo_dia);
            }
            
            $vencimento_parcela = $data->format('Y-m-d');
            $dia_compra = (int)$data->format('d');

            // --- LÓGICA DE COMPETÊNCIA AJUSTADA (Mês de Pagamento) ---
            $data_competencia = clone $data;
            if ($cartoid) {
                // REGRA ATUALIZADA:
                // Se comprou ATÉ o dia do fechamento, paga no mês seguinte.
                // Se comprou APÓS o fechamento, a fatura desse mês já fechou, paga em 2 meses.
                if ($dia_compra <= $dia_fechamento) {
                    $data_competencia->modify('first day of next month');
                } else {
                    $data_competencia->modify('first day of +2 month');
                }
            }
            $competencia = $data_competencia->format('Y-m');
            
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

        if (isset($_POST['manter_dados']) && $_POST['manter_dados'] == '1') {
            $params = http_build_query([
                'msg'  => 'sucesso',
                'tipo' => $contatipo,
                'cat'  => $categoriaid,
                'car'  => $cartoid ?? '',
                'venc' => $contavencimento,
                'fixa' => $contafixa
            ]);
            header("Location: cadastro_conta.php?" . $params);
        } else {
            header("Location: index.php?msg=sucesso");
        }
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erro ao salvar: " . $e->getMessage());
    }
}