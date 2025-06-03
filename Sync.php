<?php
require __DIR__ . '/vendor/autoload.php';
use Google\Cloud\Firestore\FirestoreClient;

// credenciais e cliente Firestore
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/serviceAccountKey.json');
$firestore = new FirestoreClient([
    'projectId' => 'conecctaapp',
]);

// conexão MySQL
$mysqli = new mysqli('127.0.0.1', 'root', '', 'dbconeccta');
if ($mysqli->connect_errno) {
    die("MySQL error: " . $mysqli->connect_error);
}

// preparar statements
$checkUidStmt   = $mysqli->prepare('SELECT id FROM usuarios WHERE firebase_uid = ? LIMIT 1');
$checkEmailStmt = $mysqli->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
$updateStmt     = $mysqli->prepare('
    UPDATE usuarios SET 
      firebase_uid = ?, 
      nome = ?, 
      username = ?, 
      genero = ?, 
      idade = ?, 
      telefone = ?, 
      setor = ?, 
      descricao = ?, 
      experiencia_profissional = ?, 
      formacao_academica = ?, 
      certificados = ?, 
      imagem_perfil = ?
    WHERE email = ?
');
$insertStmt     = $mysqli->prepare("
    INSERT INTO usuarios (
      firebase_uid,
      nome,
      username,
      genero,
      idade,
      telefone,
      email,
      setor,
      descricao,
      experiencia_profissional,
      formacao_academica,
      certificados,
      imagem_perfil
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// percorre todos os documentos da coleção “users”
$collection = $firestore->collection('users');
foreach ($collection->documents() as $doc) {
    if (! $doc->exists()) continue;

    // 1) Campos básicos
    $uid   = $doc->id();
    $data  = $doc->data();
    $nome  = $data['nome']    ?? '';
    $email = $data['email']   ?? '';

    // 2) Username: pega do Firestore, ou parte antes do '@' do e-mail
    $username = $data['username'] ?? '';
    if (empty($username) && ! empty($email)) {
        $username = explode('@', $email, 2)[0];
    }

    // 3) Converte arrays para JSON
    $experiencias = $data['experiencias'] ?? [];
    $formacoes    = $data['formacoes']    ?? [];
    $certsArr     = $data['certificados'] ?? [];
    $experiencia_profissional = is_array($experiencias)
        ? json_encode($experiencias, JSON_UNESCAPED_UNICODE)
        : (string) $experiencias;
    $formacao_academica = is_array($formacoes)
        ? json_encode($formacoes, JSON_UNESCAPED_UNICODE)
        : (string) $formacoes;
    $certificados = is_array($certsArr)
        ? json_encode($certsArr, JSON_UNESCAPED_UNICODE)
        : (string) $certsArr;

    // 4) Outros campos
    $genero        = $data['genero']        ?? '';
    $idade         = $data['idade']         ?? null;
    $telefone      = $data['telefone']      ?? '';
    $setor         = $data['setor']         ?? '';
    $descricao     = $data['descricao']     ?? '';
    $imagem_perfil = $data['imagem_perfil'] ?? '';

    // 5) Se já existe UID, pula
    $checkUidStmt->bind_param('s', $uid);
    $checkUidStmt->execute();
    $checkUidStmt->store_result();
    if ($checkUidStmt->num_rows > 0) {
        continue;
    }

    // 6) Se já existe e-mail, faz UPDATE
    $checkEmailStmt->bind_param('s', $email);
    $checkEmailStmt->execute();
    $checkEmailStmt->store_result();
    if ($checkEmailStmt->num_rows > 0) {
        $updateStmt->bind_param(
            'ssssissssssss', 
            $uid,
            $nome,
            $username,
            $genero,
            $idade,
            $telefone,
            $setor,
            $descricao,
            $experiencia_profissional,
            $formacao_academica,
            $certificados,
            $imagem_perfil,
            $email
        );
        $updateStmt->execute();
        continue;
    }

    // 7) Senão, INSERE novo registro
    $insertStmt->bind_param(
        'ssssissssssss',
        $uid,
        $nome,
        $username,
        $genero,
        $idade,
        $telefone,
        $email,
        $setor,
        $descricao,
        $experiencia_profissional,
        $formacao_academica,
        $certificados,
        $imagem_perfil
    );
    $insertStmt->execute();
}

// fecha statements e conexão
foreach ([$checkUidStmt, $checkEmailStmt, $updateStmt, $insertStmt] as $st) {
    $st->close();
}
$mysqli->close();

echo "Sincronização Firestore → MySQL concluída com sucesso!\n";
