<?php
header('Content-Type: text/html; charset=UTF-8');

function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function extractValidIpsFromHeader(string $value): array {
    $parts = array_map('trim', explode(',', $value));
    $ips = [];

    foreach ($parts as $part) {
        if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
            $ips[] = $part;
        }
    }

    return array_values(array_unique($ips));
}

function getCandidateIps(): array {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    $ips = [];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        foreach (extractValidIpsFromHeader((string) $_SERVER[$header]) as $ip) {
            $ips[] = $ip;
        }
    }

    return array_values(array_unique($ips));
}

function splitIpsByVersion(array $ips): array {
    $ipv4 = null;
    $ipv6 = null;

    foreach ($ips as $ip) {
        if ($ipv4 === null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipv4 = $ip;
        }

        if ($ipv6 === null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipv6 = $ip;
        }
    }

    return [
        'ipv4' => $ipv4,
        'ipv6' => $ipv6,
    ];
}

function getPreferredIp(array $splitIps): ?string {
    return $splitIps['ipv6'] ?? $splitIps['ipv4'] ?? null;
}

function fetchJson(string $url): ?array {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'user_agent' => 'Mozilla/5.0 IP-Checker',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function getIpInfo(string $ip, string $token): ?array {
    $providers = [
        "https://ipinfo.io/{$ip}/json?token={$token}",
        "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,isp,org,reverse,timezone,query",
    ];

    foreach ($providers as $url) {
        $data = fetchJson($url);
        if (!is_array($data)) {
            continue;
        }

        if (($data['status'] ?? '') === 'success' || isset($data['ip']) || isset($data['org']) || isset($data['hostname'])) {
            return [
                'ip' => $data['ip'] ?? $data['query'] ?? $ip,
                'country' => $data['country'] ?? 'N/A',
                'region' => $data['region'] ?? $data['regionName'] ?? 'N/A',
                'city' => $data['city'] ?? 'N/A',
                'org' => $data['org'] ?? $data['isp'] ?? 'N/A',
                'hostname' => $data['hostname'] ?? $data['reverse'] ?? 'N/A',
                'timezone' => $data['timezone'] ?? 'N/A',
            ];
        }
    }

    return null;
}

$token = '4e8af62f798d23';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'ipinfo') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $ip = trim((string) ($_GET['ip'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'IP inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $info = getIpInfo($ip, $token);
    if (!$info) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Não foi possível obter os dados do IP'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['ok' => true, 'info' => $info], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$candidateIps = getCandidateIps();
$splitIps = splitIpsByVersion($candidateIps);
$displayIpv4 = $splitIps['ipv4'] ?? 'A verificar...';
$displayIpv6 = $splitIps['ipv6'] ?? 'A verificar...';
$bestIp = getPreferredIp($splitIps);
$info = $bestIp ? getIpInfo($bestIp, $token) : null;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'ipconfig.pt';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/novo/', '?') ?: '/novo/';
$canonicalUrl = $scheme . '://' . $host . $path;
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IpConfig.pt - Qual é o meu IP? IPv4, IPv6, localização e navegador</title>
  <meta name="description" content="IpConfig.pt: descobre rapidamente o teu endereço IP público IPv4 e IPv6, ISP, localização aproximada, hostname, fuso horário, navegador e resolução do ecrã.">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <meta name="theme-color" content="#1f1f47">
  <link rel="canonical" href="<?= h($canonicalUrl) ?>">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_PT">
  <meta property="og:title" content="IpConfig.pt - Qual é o meu IP? IPv4, IPv6, localização e navegador">
  <meta property="og:description" content="IpConfig.pt mostra o teu IPv4, IPv6, ISP, localização aproximada e dados do navegador numa página rápida e simples.">
  <meta property="og:url" content="<?= h($canonicalUrl) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="dns-prefetch" href="//api.ipify.org">
  <link rel="dns-prefetch" href="//api6.ipify.org">
  <link rel="dns-prefetch" href="//ipinfo.io">
  <link rel="preconnect" href="https://api.ipify.org" crossorigin>
  <link rel="preconnect" href="https://api6.ipify.org" crossorigin>
  <link rel="stylesheet" href="css/ver_ip_mrquim.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon-192.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon-180.png">
</head>
<body>
  <header class="glass-banner">
    <div class="brand-lockup">
      <img src="assets/logo-ipconfig.png" alt="IpConfig.pt" class="site-logo" width="575" height="185">
    </div>
    <p class="eyebrow">Ferramenta online</p>
    <h1>Descobre o teu endereço IP</h1>
    <p>Consulta o teu IPv4, IPv6 e informações públicas de rede e sistema no IpConfig.pt.</p>
  </header>

  <main class="container">
    <section class="ip-section" aria-label="Informações do IP">
      <div class="info-block"><span class="label">🔢 <strong>IPv4:</strong></span> <button type="button" id="ipv4-value" class="copy-ip" data-copy-value="<?= h($displayIpv4) ?>" title="Clicar para copiar"><?= h($displayIpv4) ?></button></div>
      <div class="info-block"><span class="label">🔡 <strong>IPv6:</strong></span> <button type="button" id="ipv6-value" class="copy-ip" data-copy-value="<?= h($displayIpv6) ?>" title="Clicar para copiar"><?= h($displayIpv6) ?></button></div>
      <div class="info-block info-block--subtle">ℹ️ Se o teu ISP suportar IPv6 mas aqui aparecer “Não detetado”, o navegador ou a rota atual podem estar a usar apenas IPv4 nessa ligação.</div>

      <?php if ($info): ?>
        <div class="info-block"><span class="label">🌍 <strong>País:</strong></span> <span id="country-value"><?= h(($info['country'] ?? 'N/A') . ' (' . ($info['region'] ?? 'N/A') . ', ' . ($info['city'] ?? 'N/A') . ')') ?></span></div>
        <div class="info-block"><span class="label">🏢 <strong>ISP:</strong></span> <span id="org-value"><?= h($info['org'] ?? 'N/A') ?></span></div>
        <div class="info-block"><span class="label">🖥️ <strong>Hostname:</strong></span> <span id="hostname-value"><?= h($info['hostname'] ?? 'N/A') ?></span></div>
        <div class="info-block"><span class="label">🕐 <strong>Fuso Horário:</strong></span> <span id="timezone-value"><?= h($info['timezone'] ?? 'N/A') ?></span></div>
      <?php else: ?>
        <div class="info-block"><span class="label">🌍 <strong>País:</strong></span> <span id="country-value">A verificar...</span></div>
        <div class="info-block"><span class="label">🏢 <strong>ISP:</strong></span> <span id="org-value">A verificar...</span></div>
        <div class="info-block"><span class="label">🖥️ <strong>Hostname:</strong></span> <span id="hostname-value">A verificar...</span></div>
        <div class="info-block"><span class="label">🕐 <strong>Fuso Horário:</strong></span> <span id="timezone-value">A verificar...</span></div>
      <?php endif; ?>
    </section>

    <section class="content-card" aria-label="Sobre esta página">
      <h2>O que esta página mostra</h2>
      <p>Esta ferramenta deteta o teu endereço IP público, tenta identificar separadamente IPv4 e IPv6 e apresenta dados adicionais como ISP, localização aproximada, hostname, fuso horário, navegador, idioma e resolução do ecrã.</p>
    </section>

    <section id="extra-info" class="device-grid" aria-label="Informações do dispositivo"></section>
  </main>

  <footer class="footer">
    <p>&copy; <?= date('Y') ?> IpConfig.pt - Qual é o meu IP?</p>
  </footer>

  <script src="js/script.js" defer></script>
</body>
</html>
