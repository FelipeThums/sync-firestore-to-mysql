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

// preparar statements (agora com CNPJ e website)
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
      imagem_perfil = ?, 
      tipo = ?, 
      CNPJ = ?, 
      website = ?
    WHERE email = ?
');
$insertStmt = $mysqli->prepare("INSERT INTO usuarios (
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
    imagem_perfil,
    tipo,
    CNPJ,
    website
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$insertStmt) {
    die("Erro na preparação do INSERT: " . $mysqli->error);
}

// percorre todos os documentos da coleção “users”
$collection = $firestore->collection('users');
foreach ($collection->documents() as $doc) {
    if (! $doc->exists()) continue;

    $uid      = $doc->id();
    $data     = $doc->data();
    $nome     = $data['nome']    ?? '';
    $email    = $data['email']   ?? '';
    $username = $data['username'] ?? '';
    if (empty($username) && ! empty($email)) {
        $username = explode('@', $email, 2)[0];
    }

    // Extrair CNPJ e website (podem vir vazios)
    $CNPJ    = $data['CNPJ']    ?? '';
    $website = $data['website'] ?? '';

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

    $genero        = $data['genero']        ?? '';
    $idade         = $data['idade']         ?? null;
    $telefone      = $data['telefone']      ?? '';
    $setor         = $data['setor']         ?? '';
    $descricao     = $data['descricao']     ?? '';
    $imagem_perfil = $data['imagem_perfil'] ?? '';
    $tipo          = $data['tipo']          ?? '';

    // Se já existe firebase_uid, pula atualização/inserção
    $checkUidStmt->bind_param('s', $uid);
    $checkUidStmt->execute();
    $checkUidStmt->store_result();
    if ($checkUidStmt->num_rows > 0) {
        continue;
    }

    // Se já existe email, faz UPDATE (incluindo CNPJ e website)
    $checkEmailStmt->bind_param('s', $email);
    $checkEmailStmt->execute();
    $checkEmailStmt->store_result();
    if ($checkEmailStmt->num_rows > 0) {
        $updateStmt->bind_param(
            'ssssisssssssssss',
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
            $tipo,
            $CNPJ,
            $website,
            $email
        );
        $updateStmt->execute();
        continue;
    }

    // INSERE novo registro (incluindo CNPJ e website)
    $insertStmt->bind_param(
        'ssssisssssssssss',
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
        $imagem_perfil,
        $tipo,
        $CNPJ,
        $website
    );
    $insertStmt->execute();
}

// fecha tudo
foreach ([$checkUidStmt, $checkEmailStmt, $updateStmt, $insertStmt] as $st) {
    $st->close();
}
$mysqli->close();

echo "✅ Sincronização Firestore → MySQL concluída com sucesso!\n";
