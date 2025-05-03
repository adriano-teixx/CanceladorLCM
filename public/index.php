<?php
session_start();

// Flush em tempo real para barra de progresso
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', 'off');
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Exceptions.php';
require __DIR__ . '/../src/ServiceLayerClient.php';
require __DIR__ . '/../src/CSVReader.php';

use App\ServiceLayerClient;
use App\CSVReader;
use App\Exceptions\{
    AuthenticationException,
    UnauthorizedException,
    ForbiddenException,
    NotFoundException,
    AlreadyCanceledException,
    BusinessException,
    ServiceLayerException
};

$config    = require __DIR__ . '/../config.php';
$slVersion = $config['sl']['version'];
$logFile   = $config['log_file'];

$error      = null;
$docEntries = [];

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- Se não estiver autenticado, mostra login ---
if (!isset($_SESSION['sessionId'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $host      = trim($_POST['host']      ?? '');
        $companyDB = trim($_POST['companyDB'] ?? '');
        $username  = trim($_POST['username']  ?? '');
        $password  = trim($_POST['password']  ?? '');

        if (!$host || !$companyDB || !$username || !$password) {
            $error = 'Preencha todos os campos de login.';
        } else {
            $slConfig = [
                'host'      => $host,
                'version'   => $slVersion,
                'companyDB' => $companyDB,
                'username'  => $username,
                'password'  => $password,
            ];
            try {
                $sl = new ServiceLayerClient($slConfig, $logFile);
                $sessionId = $sl->login();
                $_SESSION['sl_config'] = $slConfig;
                $_SESSION['sessionId'] = $sessionId;
                header('Location: index.php');
                exit;
            } catch (AuthenticationException $e) {
                $error = 'Login falhou: ' . $e->getMessage();
            } catch (ServiceLayerException $e) {
                $error = 'Erro no SL: ' . $e->getMessage();
            }
        }
    }
    // Tela de Login SAP B1
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login SAP B1 Cancelador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #0057A8 0%, #FFCB00 100%);
      color: #333;
    }
    .login-card {
      max-width: 420px;
      width: 100%;
      border-radius: 1rem;
    }
    .sap-logo {
      width: 120px;
      margin-bottom: 1rem;
    }
    .form-floating .form-control:focus {
      box-shadow: 0 0 0 .2rem rgba(0,87,168,.25);
    }
    .btn-sap {
      background-color: #0057A8;
      color: #fff;
      font-weight: 600;
      border: none;
    }
    .btn-sap:hover {
      background-color: #004080;
    }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card login-card shadow-lg">
    <div class="card-body px-5 py-4">
      <div class="text-center">
        <img src="https://upload.wikimedia.org/wikipedia/commons/5/59/SAP_2011_logo.svg" alt="SAP Logo" class="sap-logo">
      </div>
      <h3 class="text-center mb-4">Cancelador de LCMs</h3>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="host" name="host" placeholder="ex: teste.teste.com.br:50000" required>
          <label for="host">Host (com porta)</label>
        </div>
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="companyDB" name="companyDB" placeholder="ex: SBO_TESTE_prod" required>
          <label for="companyDB">CompanyDB</label>
        </div>
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="username" name="username" placeholder="Usuario SAP" required>
          <label for="username">Usuário</label>
        </div>
        <div class="form-floating mb-4">
          <input type="password" class="form-control" id="password" name="password" placeholder="Sua senha SAP" required>
          <label for="password">Senha</label>
        </div>
        <button type="submit" class="btn btn-sap w-100 mb-2">Conectar</button>
      </form>
      <div class="text-center">
        <small class="text-muted">Service Layer v<?= htmlspecialchars($slVersion) ?></small>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Spinner on submit -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        var originalText = btn.textContent.trim();
        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
          originalText;
      });
    });
  });
  </script>
</body>
</html>
<?php
    exit;
}

// --- Autenticado: processa CSV e cancela ---

$slConfig  = $_SESSION['sl_config'];
$sessionId = $_SESSION['sessionId'];

// Recebe CSV para cancelamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    try {
        $reader     = new CSVReader($_FILES['file']['tmp_name'], 'DocEntry');
        $docEntries = $reader->readColumn();
        if (empty($docEntries)) {
            throw new \Exception('Nenhum DocEntry no CSV.');
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Barra de progresso se houver DocEntries válidos
if ($docEntries && !$error) {
    set_time_limit(600);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Progresso do Cancelamento</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .progress-card {
      max-width: 600px;
      width: 100%;
      border-radius: 1rem;
    }
    .sap-navbar {
      background-color: #0057A8;
    }
    .sap-navbar .navbar-brand {
      color: #FFCB00;
      font-weight: bold;
    }
    .btn-sap {
      background-color: #0057A8;
      color: #fff;
      font-weight: 600;
      border: none;
    }
    .btn-sap:hover {
      background-color: #004080;
    }
  </style>
</head>
<body>
  <nav class="navbar sap-navbar navbar-dark shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="#">SAP B1 Cancelador</a>
      <a href="index.php?logout=1" class="btn btn-sap">Logout</a>
    </div>
  </nav>
  <div class="d-flex justify-content-center py-5">
    <div class="card progress-card shadow-lg p-4">
      <div class="d-flex align-items-center mb-3">
        <img src="https://upload.wikimedia.org/wikipedia/commons/5/59/SAP_2011_logo.svg" alt="SAP" style="height:40px; margin-right:1rem;">
        <h4 class="mb-0">Cancelando <?= count($docEntries) ?> documento(s)</h4>
      </div>
      <div class="progress mb-4" style="height:1.5rem;">
        <div id="progBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width:0%" aria-valuemin="0" aria-valuemax="<?= count($docEntries) ?>"></div>
      </div>
      <ul class="list-group list-group-flush" id="results">
        <?php
        $sl = new ServiceLayerClient($slConfig, $logFile);
        $i = 0;
        foreach ($docEntries as $docEntry) {
            $i++;
            try {
                $sl->cancel($sessionId, $docEntry);
                $type = 'success';
                $icon = '✅';
                $msg = 'Cancelado com sucesso';
            } catch (AlreadyCanceledException $e) {
                $type = 'warning'; $icon = '⚠️'; $msg = 'Já cancelado';
            } catch (UnauthorizedException $e) {
                $type = 'danger'; $icon = '❌'; $msg = 'Sessão inválida';
            } catch (ForbiddenException $e) {
                $type = 'danger'; $icon = '🚫'; $msg = 'Sem permissão';
            } catch (NotFoundException $e) {
                $type = 'danger'; $icon = '🔍'; $msg = 'Não encontrado';
            } catch (BusinessException $e) {
                $type = 'danger'; $icon = '💼'; $msg = 'Trava SAP';
            } catch (ServiceLayerException $e) {
                $type = 'danger'; $icon = '⚙️'; $msg = 'Erro SL';
            }
            echo "<li class='list-group-item d-flex justify-content-between'><span>{$icon} DocEntry {$docEntry}</span><span class='text-{$type}'>{$msg}</span></li>";
            echo "<script>document.getElementById('progBar').style.width = '" . intval(($i/count($docEntries))*100) . "%';</script>";
            flush();
        }
        ?>
      </ul>

      <!-- Mensagem única ao final -->
      <div class="text-center mt-4">
        <div class="alert alert-success mb-0">
          ✅ Processo de cancelamento concluído! Foram processados <?= count($docEntries) ?> documento(s).
        </div>
      </div>

    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Spinner on submit (não há forms nesta tela, mas mantido por consistência) -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        var originalText = btn.textContent.trim();
        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
          originalText;
      });
    });
  });
  </script>
</body>
</html>
<?php
    exit;
}

// View de upload CSV
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Importar CSV – Cancelador SAP B1</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #ffffff 0%, #ece9e6 100%); }
    .upload-card {
      max-width: 480px;
      width:100%;
      border-radius: 1rem;
    }
    .sap-logo-small { height: 32px; margin-bottom: 1rem; }
    .btn-sap {
      background-color: #0057A8;
      color: #fff;
      font-weight: 600;
      border: none;
    }
    .btn-sap:hover {
      background-color: #004080;
    }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card upload-card shadow-lg p-4">
    <div class="text-center mb-3">
      <img src="https://upload.wikimedia.org/wikipedia/commons/5/59/SAP_2011_logo.svg" alt="SAP" class="sap-logo-small">
      <h4>Importar CSV de DocEntry</h4>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="cancel">
      <div class="mb-3">
        <label for="file" class="form-label">Selecione o arquivo CSV</label>
        <input id="file" name="file" class="form-control" type="file" accept=".csv" required>
      </div>
      <button type="submit" class="btn btn-sap w-100">Cancelar LCMs</button>
    </form>
    <div class="text-center mt-3"><a href="index.php?logout=1" class="text-decoration-none" style="color: #0057A8;">Logout</a></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Spinner on submit -->
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        var originalText = btn.textContent.trim();
        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
          originalText;
      });
    });
  });
  </script>
</body>
</html>
