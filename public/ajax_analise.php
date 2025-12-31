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

        // ---------------------------------------------------------
        // 1. EVOLUÇÃO MENSAL (CORREÇÃO DO SQL AQUI)
        // ---------------------------------------------------------
        // A lógica do COALESCE foi simplificada para não usar JOIN na subquery,
        // evitando o erro de "Column not found".
        $stmt = $pdo->prepare("
            SELECT 
                c.contacompetencia, 
                SUM(c.contavalor) as total_gasto,
                COALESCE(
                    -- 1. Tenta pegar a meta específica daquele mês na tabela categorias_metas
                    (SELECT valor FROM categorias_metas WHERE categoriaid = c.categoriaid AND competencia = c.contacompetencia LIMIT 1),
                    
                    -- 2. Se não achar, pega a meta padrão da tabela categorias
                    (SELECT categoriameta FROM categorias WHERE categoriaid = c.categoriaid LIMIT 1),
                    
                    -- 3. Se não tiver nada, zero.
                    0
                ) as meta_do_mes
            FROM contas c
            WHERE c.usuarioid = ? 
            AND c.categoriaid = ? 
            AND (c.contatipo = 'Saída' OR c.contatipo = 'Despesa')
            AND c.contavencimento BETWEEN ? AND ?
            GROUP BY c.contacompetencia 
            ORDER BY c.contacompetencia ASC
        ");
        
        $stmt->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $dados_evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = []; 
        $values_gasto = [];
        $values_meta = [];
        $meses_nome = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

        foreach($dados_evolucao as $d) {
            // Formata Label (Ex: Jan/25)
            $parts = explode('-', $d['contacompetencia']);
            if(count($parts)==2) $labels[] = ($meses_nome[$parts[1]]??$parts[1])."/".substr($parts[0],2,2);
            else $labels[] = $d['contacompetencia'];
            
            // Dados
            $values_gasto[] = (float)$d['total_gasto'];
            $values_meta[]  = (float)$d['meta_do_mes'];
        }

        // ---------------------------------------------------------
        // 2. DADOS SECUNDÁRIOS (Semana/Dias)
        // ---------------------------------------------------------
        
        // Dias da Semana
        $stmtDay = $pdo->prepare("SELECT DAYOFWEEK(contavencimento) as dia, SUM(contavalor) as total FROM contas WHERE usuarioid = ? AND categoriaid = ? AND (contatipo = 'Saída' OR contatipo = 'Despesa') AND contavencimento BETWEEN ? AND ? GROUP BY dia ORDER BY dia ASC");
        $stmtDay->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $resDay = $stmtDay->fetchAll(PDO::FETCH_KEY_PAIR);
        $data_semana = []; for($i=1; $i<=7; $i++) $data_semana[] = (float)($resDay[$i] ?? 0);

        // Semanas do Mês
        $stmtWeek = $pdo->prepare("SELECT FLOOR((DAY(contavencimento)-1)/7)+1 as semana, SUM(contavalor) as total FROM contas WHERE usuarioid = ? AND categoriaid = ? AND (contatipo = 'Saída' OR contatipo = 'Despesa') AND contavencimento BETWEEN ? AND ? GROUP BY semana ORDER BY semana ASC");
        $stmtWeek->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $resWeek = $stmtWeek->fetchAll(PDO::FETCH_KEY_PAIR);
        $data_mes_semana = []; for($i=1; $i<=5; $i++) $data_mes_semana[] = (float)($resWeek[$i] ?? 0);

        // ---------------------------------------------------------
        // 3. TOTAIS GERAIS (KPIs)
        // ---------------------------------------------------------
        $total_gasto = array_sum($values_gasto);
        $total_meta_acumulada = array_sum($values_meta);
        
        $stmtQtd = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE usuarioid=? AND categoriaid=? AND (contatipo='Saída' OR contatipo='Despesa') AND contavencimento BETWEEN ? AND ?");
        $stmtQtd->execute([$uid, $cat_id, $dt_start, $dt_end]);
        $qtd_registros = $stmtQtd->fetchColumn();
        
        $perc_uso = ($total_meta_acumulada > 0) ? ($total_gasto / $total_meta_acumulada) * 100 : 0;

        $response = [
            'status' => 'success',
            'evolucao' => [
                'labels' => $labels, 
                'gasto' => $values_gasto, 
                'meta' => $values_meta 
            ],
            'semana' => $data_semana, 
            'mes_semanas' => $data_mes_semana,
            'kpi' => [
                'total' => number_format($total_gasto, 2, ',', '.'),
                'media' => ($qtd_registros > 0) ? number_format($total_gasto/$qtd_registros, 2, ',', '.') : '0,00',
                'qtd' => $qtd_registros
            ],
            'meta' => [
                'total_periodo' => number_format($total_meta_acumulada, 2, ',', '.'),
                'perc' => $perc_uso,
                'tem_meta' => ($total_meta_acumulada > 0)
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