<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar • Ponto Certo</title><link rel="stylesheet" href="/assets/app.css"><link rel="icon" href="/favicon.svg" type="image/svg+xml"></head>
<body class="login-body">
<main class="login-shell">
  <section class="login-brand">
    <div class="brand-mark">PC</div><p class="eyebrow">Gestão de jornada</p>
    <h1>Ponto confiável.<br>Decisões mais claras.</h1>
    <p>Centralize marcações, trate inconsistências e feche a folha com rastreabilidade.</p>
    <div class="legal-chip">Portaria MTP nº 671/2021</div>
  </section>
  <section class="login-card">
    <p class="eyebrow">Acesso seguro</p><h2>Bem-vindo de volta</h2><p class="muted">Entre com os dados da sua organização.</p>
    <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_token" value="<?= csrf_token() ?>">
      <label>E-mail<input type="email" name="email" value="admin@pontocerto.local" autocomplete="username" required></label>
      <label>Senha<input type="password" name="password" value="Admin@123" autocomplete="current-password" required></label>
      <button class="button primary wide">Entrar no sistema</button>
    </form>
    <p class="demo-note">Ambiente demonstrativo • admin@pontocerto.local</p>
  </section>
</main></body></html>

