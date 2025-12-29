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
        // Se for FIXA e não for parcelada, projetamos 12 meses automáticos
        // Se for parcelada, usamos o número de parcelas definido
        $limite_repeticao = ($contafixa == 1 && $parcelas_total <= 1) ? 12 : $parcelas_total;

        for ($i = 1; $i <= $limite_repeticao; $i++) {
            $data = new DateTime($contavencimento);
            $dia_original = (int)$data->format('d'); // Salva o dia original (ex: 31)

            if ($i > 1) {
                // Modificamos para o dia 1 do mês de destino primeiro para evitar o erro de meses curtos
                $data->modify('first day of +' . ($i - 1) . ' month');
                
                // Pegamos o último dia do mês de destino
                $ultimo_dia_mes = (int)$data->format('t');
                
                // Se o dia original (31) for maior que o último dia do mês (ex: 28), usamos o último dia
                $novo_dia = min($dia_original, $ultimo_dia_mes);
                $data->setDate((int)$data->format('Y'), (int)$data->format('m'), $novo_dia);
            }
            
            $vencimento_parcela = $data->format('Y-m-d');
            $dia_compra = (int)$data->format('d');

            // Calcula a competência baseada no fechamento do cartão
            $data_competencia = clone $data;
            if ($cartoid && $dia_compra >= $dia_fechamento) {
                $data_competencia->modify('first day of next month'); // Seguro contra erro de virada de mês
            }
            $competencia = $data_competencia->format('Y-m');
            
            // Ajusta descrição: Se for parcelado mostra (1/12), se for fixa simples não precisa
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
        
        require_once "../includes/header.php";
        ?>
        <div class="container py-5 text-center">
            <div class="card border-0 shadow-sm p-5 rounded-4 bg-white">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h2 class="fw-bold">Lançamento Realizado!</h2>
                <p class="text-muted mb-4">
                    <?php if($contafixa && $parcelas_total <= 1): ?>
                        Sua conta fixa foi projetada para os próximos <strong>12 meses</strong>.
                    <?php else: ?>
                        Seu lançamento foi registrado com sucesso.
                    <?php endif; ?>
                </p>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="cadastro_conta.php" class="btn btn-primary btn-lg px-4 rounded-4 fw-bold">Novo Lançamento</a>
                    <a href="index.php" class="btn btn-light btn-lg px-4 rounded-4 fw-bold border">Voltar ao Início</a>
                </div>
            </div>
        </div>
        <?php
        require_once "../includes/footer.php";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erro ao salvar: " . $e->getMessage());
    }
}