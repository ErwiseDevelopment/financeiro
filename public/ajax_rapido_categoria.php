<?php
require_once "../config/database.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['usuarioid'];
    $descricao = trim($_POST['categoriadescricao']);
    $tipo_raw = $_POST['categoriatipo'];

    // 1. Tradução para os termos do Banco de Dados (ENUM)
    $tipo_db = ($tipo_raw == 'Entrada') ? 'Receita' : 'Despesa';

    try {
        // 2. Bloqueio de Duplicados (Verifica se já existe para este usuário)
        $stmt_check = $pdo->prepare("SELECT categoriaid FROM categorias WHERE usuarioid = ? AND categoriadescricao = ?");
        $stmt_check->execute([$uid, $descricao]);
        
        if ($stmt_check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Você já possui uma categoria com este nome!']);
            exit;
        }

        // 3. Inserção
        $stmt = $pdo->prepare("INSERT INTO categorias (usuarioid, categoriadescricao, categoriatipo) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $descricao, $tipo_db]);
        
        $novo_id = $pdo->lastInsertId();
        echo json_encode(['status' => 'success', 'id' => $novo_id, 'nome' => $descricao]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}