<?php
// ajax_analise.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once "../config/database.php";
session_start();

header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => 'Erro desconhecido'];

try {
    if (!isset($_SESSION['usuarioid'])) throw new Exception("Sessão expirada.");
    $uid = $_SESSION['usuarioid'];
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'evolucao_categoria') {
        $cat_id = $_POST['categoria_id'];
        $dt_start = $_POST['mes_inicio'] . '-01';
        $dateObjFim = new DateTime($_POST['mes_fim'] . '-01');
        $dt_end = $dateObjFim->format('Y-m-t');

        // 1. EVOLUÇÃO MENSAL
        $stmt = $pdo->prepare("
            SELECT contacompetencia, SUM(contavalor) as total 
            FROM contas WHERE usuarioid = ? AND categoriaid = ? AND contatipo = 'Saída'
            AND contavencimento BETWEEN ? AND ?
            GROUP BY contacompetencia ORDER BY contacompetencia ASC
        ");
        $stmt->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $dados_evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = []; $values = [];
        $meses_nome = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

        foreach($dados_evolucao as $d) {
            $parts = explode('-', $d['contacompetencia']);
            if(count($parts)==2) $labels[] = ($meses_nome[$parts[1]]??$parts[1])."/".substr($parts[0],2,2);
            else $labels[] = $d['contacompetencia'];
            $values[] = (float)$d['total'];
        }

        // 2. DIAS DA SEMANA (1=Dom, 7=Sab)
        $stmtDay = $pdo->prepare("
            SELECT DAYOFWEEK(contavencimento) as dia, SUM(contavalor) as total
            FROM contas WHERE usuarioid = ? AND categoriaid = ? AND contatipo = 'Saída'
            AND contavencimento BETWEEN ? AND ?
            GROUP BY dia ORDER BY dia ASC
        ");
        $stmtDay->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $resDay = $stmtDay->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna [dia => total]
        
        $data_semana = [];
        // Garante ordem Dom -> Sab
        for($i=1; $i<=7; $i++) { $data_semana[] = (float)($resDay[$i] ?? 0); }

        // 3. SEMANAS DO MÊS (1 a 5)
        $stmtWeek = $pdo->prepare("
            SELECT FLOOR((DAY(contavencimento)-1)/7)+1 as semana, SUM(contavalor) as total
            FROM contas WHERE usuarioid = ? AND categoriaid = ? AND contatipo = 'Saída'
            AND contavencimento BETWEEN ? AND ?
            GROUP BY semana ORDER BY semana ASC
        ");
        $stmtWeek->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $resWeek = $stmtWeek->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $data_mes_semana = [];
        for($i=1; $i<=5; $i++) { $data_mes_semana[] = (float)($resWeek[$i] ?? 0); }

        // 4. TOTAIS GERAIS
        $total_periodo = array_sum($values);
        $qtd_registros = $pdo->query("SELECT COUNT(*) FROM contas WHERE usuarioid=$uid AND categoriaid=$cat_id AND contatipo='Saída' AND contavencimento BETWEEN '$dt_start' AND '$dt_end'")->fetchColumn();

        $response = [
            'status' => 'success',
            'evolucao' => ['labels' => $labels, 'data' => $values],
            'semana' => $data_semana, // Array com 7 posições
            'mes_semanas' => $data_mes_semana, // Array com 5 posições
            'kpi' => [
                'total' => number_format($total_periodo, 2, ',', '.'),
                'media' => ($qtd_registros > 0) ? number_format($total_periodo/$qtd_registros, 2, ',', '.') : '0,00',
                'qtd' => $qtd_registros
            ]
        ];
    } 
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_clean();
echo json_encode($response);
exit;
?>