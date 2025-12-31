<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $contadescricao = $_POST['contadescricao'];
    
    // --- CORREÇÃO DO VALOR AQUI ---
    // O JavaScript já envia o valor formatado (ex: 10.99) no input hidden
    // Então pegamos direto, sem remover pontos.
    $contavalor = $_POST['contavalor']; 

    // Se por acaso vier vazio ou com erro, forçamos float
    if (empty($contavalor)) $contavalor = 0.00;

    $contatipo = $_POST['contatipo'];
    $categoriaid = $_POST['categoriaid'];
    $contavencimento = $_POST['contavencimento'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;
    $contafixa = isset($_POST['contafixa']) ? 1 : 0;
    
    $parcelas_total = (int)($_POST['contaparcela_total'] ?? 1);
    if ($parcelas_total < 1) $parcelas_total = 1;

    try {
        $pdo->beginTransaction();

        // 1. BUSCAR O DIA DE FECHAMENTO REAL NO BANCO
        $dia_fechamento = 30; 
        if ($cartoid) {
            $stmt_cartao = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ? AND usuarioid = ?");
            $stmt_cartao->execute([$cartoid, $uid]);
            $res = $stmt_cartao->fetch();
            if ($res) {
                $dia_fechamento = (int)$res['cartofechamento'];
            }
        }

        // Definição do loop (Fixa ou Parcelada)
        $limite_repeticao = ($contafixa == 1 && $parcelas_total <= 1) ? 12 : $parcelas_total;

        for ($i = 1; $i <= $limite_repeticao; $i++) {
            
            // Data base da compra/parcela
            $data = new DateTime($contavencimento);
            $dia_compra = (int)$data->format('d');

            // Lógica de avanço das parcelas
            if ($i > 1) {
                $data->modify("+$i months -1 month");
            }
            
            $vencimento_db = $data->format('Y-m-d');
            $conta_competencia = $data->format('Y-m'); 

            // --- 2. LÓGICA DE FATURA (MANTIDA CORRETA) ---
            $competencia_fatura = null;

            if ($cartoid) {
                $data_calc = clone $data;
                $dia_atual_parcela = (int)$data_calc->format('d');

                // REGRA: Compra >= Fechamento -> Pula 2 meses
                if ($dia_atual_parcela >= $dia_fechamento) {
                    $data_calc->modify('first day of +2 months');
                } else {
                    $data_calc->modify('first day of next month');
                }
                
                $competencia_fatura = $data_calc->format('Y-m');
            }

            // Descrição
            $desc_final = ($parcelas_total > 1) ? "$contadescricao ($i/$parcelas_total)" : $contadescricao;

            // 3. INSERT
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
                $contavalor, // Agora vai o valor correto (ex: 10.99)
                $vencimento_db,
                $conta_competencia,
                $competencia_fatura,
                $contatipo,
                $contafixa,
                $cartoid,
                $i,
                $parcelas_total
            ]);
        }

        $pdo->commit();

        // Redirecionamento
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
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Erro ao salvar: " . $e->getMessage());
    }
}
?>