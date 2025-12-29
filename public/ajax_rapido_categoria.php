<?php
// Define que o retorno será sempre JSON (evita problemas de interpretação no JS)
header('Content-Type: application/json');

require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se o usuário está logado
    if (!isset($_SESSION['usuarioid'])) {
        echo json_encode(['status' => 'error', 'message' => 'Sessão expirada.']);
        exit;
    }

    $uid = $_SESSION['usuarioid'];
    $descricao = trim($_POST['categoriadescricao'] ?? '');
    $tipo_raw = $_POST['categoriatipo'] ?? 'Saída';

    if (empty($descricao)) {
        echo json_encode(['status' => 'error', 'message' => 'O nome da categoria é obrigatório.']);
        exit;
    }

    // 1. Tradução para os termos do Banco de Dados (ENUM)
    $tipo_db = ($tipo_raw == 'Entrada') ? 'Receita' : 'Despesa';

    try {
        // 2. Bloqueio de Duplicados
        $stmt_check = $pdo->prepare("SELECT categoriaid FROM categorias WHERE usuarioid = ? AND categoriadescricao = ? AND categoriatipo = ?");
        $stmt_check->execute([$uid, $descricao, $tipo_db]);
        
        if ($stmt_check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Você já possui uma categoria com este nome para este tipo!']);
            exit;
        }

        // 3. Inserção
        $stmt = $pdo->prepare("INSERT INTO categorias (usuarioid, categoriadescricao, categoriatipo) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $descricao, $tipo_db]);
        
        $novo_id = $pdo->lastInsertId();
        
        // Retorno de sucesso
        echo json_encode([
            'status' => 'success', 
            'id' => $novo_id, 
            'nome' => $descricao
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
}