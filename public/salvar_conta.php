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

        // --- 1. BUSCAR DIA DE FECHAMENTO DO CARTÃO ---
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

            // Avança os meses se for parcelado (i > 1)
            if ($i > 1) {
                $data->modify('first day of +' . ($i - 1) . ' month');
                $ultimo_dia_mes = (int)$data->format('t');
                $novo_dia = min($dia_original, $ultimo_dia_mes);
                $data->setDate((int)$data->format('Y'), (int)$data->format('m'), $novo_dia);
            }
            
            $vencimento_parcela = $data->format('Y-m-d');
            $dia_vencimento_atual = (int)$data->format('d');

            // --- 2. LÓGICA DE COMPETÊNCIAS ---
            
            // A) Competência Contábil (Mês da compra/parcela real)
            $conta_competencia = $data->format('Y-m');

            // B) Competência da Fatura (Para fluxo de caixa do cartão)
            $competencia_fatura = null; // Padrão nulo para contas sem cartão

            if ($cartoid) {
                $data_calc_fatura = clone $data; // Clona para não alterar a data original
                
                // Se o dia da parcela for MAIOR ou IGUAL ao fechamento, joga para o próximo mês
                if ($dia_vencimento_atual >= $dia_fechamento) {
                    $data_calc_fatura->modify('first day of next month');
                }
                
                $competencia_fatura = $data_calc_fatura->format('Y-m');
            }

            // Ajusta descrição para parcelados
            $desc_final = ($parcelas_total > 1) ? $contadescricao . " ($i/$parcelas_total)" : $contadescricao;

            // --- 3. INSERT ATUALIZADO COM O NOVO CAMPO ---
            $sql = "INSERT INTO contas (
                usuarioid, categoriaid, contadescricao, contavalor, 
                contavencimento, contacompetencia, competenciafatura, contatipo, 
                contafixa, cartoid, contaparcela_num, contaparcela_total, contasituacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $uid,
                $categoriaid,
                $desc_final,
                $contavalor,
                $vencimento_parcela,
                $conta_competencia,  // Ex: 2025-12 (Mês que usou o cartão)
                $competencia_fatura, // Ex: 2026-01 (Mês que vai pagar a fatura)
                $contatipo,
                $contafixa,
                $cartoid,
                $i,
                $parcelas_total
            ]);
        }

        $pdo->commit();

        // --- LÓGICA DE REDIRECIONAMENTO ---
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
?>