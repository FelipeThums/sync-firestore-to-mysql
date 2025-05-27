<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

// 1) Configura o Firestore
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/serviceAccountKey.json');
$firestore = new FirestoreClient([
    'projectId' => 'conecctaapp',
]);

//importamos a biblioteca do Firestore e configuramos o caminho para o arquivo de credenciais

// 2) Conecta ao MySQL (XAMPP)
$mysqli = new mysqli('127.0.0.1', 'root', '', 'dbconeccta');
if ($mysqli->connect_errno) {
    die("MySQL error: " . $mysqli->connect_error);
}

//conexão com o banco de dados local

// 3) Prepara um SELECT para checar se o UID já existe
$checkStmt = $mysqli->prepare(
    'SELECT 1 FROM usuarios WHERE firebase_uid = ? LIMIT 1'
);

//acima preparamos um SELECT que verifica se o firebase_uid já existe na tabela usuarios

// 4) Prepara só o INSERT puro (sem ON DUPLICATE)
$insertStmt = $mysqli->prepare("


    INSERT INTO usuarios  (
      firebase_uid,
      nome_candidato,
      email_candidato
    ) VALUES (?, ?, ?)
");

//no codigo acima inserimos os campos desejados na tabela usuarios
// (ajuste os nomes dos campos conforme necessário)


// 5) Itera todos os documentos da coleção "users"
$collection = $firestore->collection('users');
foreach ($collection->documents() as $doc) {
    if (! $doc->exists()) {
        continue;
    }

    // Carrega os dados do doc
    $firebase_uid = $doc->id();
    $data         = $doc->data();
    $nome         = $data['nome']  ?? '';
    $email        = $data['email'] ?? '';

    //o uso do "?" é como um coringa para os valores que serão inseridos posteriormente

    // 6) Checa existência no banco
    $checkStmt->bind_param('s', $firebase_uid);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows === 0) {
        // 7) Só insere se não existir
        $insertStmt->bind_param('sss', $firebase_uid, $nome, $email);
        $insertStmt->execute();
    }
    // Se já existe, pula (ou aqui você poderia fazer um UPDATE separado, se quisesse)
}

// 8) Fecha statements e conexão
$checkStmt->close();
$insertStmt->close();
$mysqli->close();

echo "Sincronização Firestore → MySQL concluída sem duplicatas!\n";
