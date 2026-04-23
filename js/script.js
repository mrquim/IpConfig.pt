const text = {
  checking: 'A verificar...',
  notDetected: 'Não detetado',
  notAvailable: 'N/A',
  clickToCopy: 'Clicar para copiar',
  copied: 'Copiado!'
};

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = value;
    if (el.classList.contains('copy-ip')) {
      el.dataset.copyValue = value;
      el.title = value && value !== text.notDetected && value !== text.checking ? text.clickToCopy : '';
      el.disabled = !value || value === text.notDetected || value === text.checking;
    }
  }
}

async function fetchJson(url) {
  const response = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    cache: 'no-store'
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

async function detectPublicIps() {
  const results = await Promise.allSettled([
    fetchJson('https://api.ipify.org?format=json'),
    fetchJson('https://api6.ipify.org?format=json')
  ]);

  const ipv4 = results[0].status === 'fulfilled' && results[0].value?.ip ? results[0].value.ip : text.notDetected;
  const ipv6 = results[1].status === 'fulfilled' && results[1].value?.ip ? results[1].value.ip : text.notDetected;

  setText('ipv4-value', ipv4);
  setText('ipv6-value', ipv6);

  return {
    ipv4: ipv4 !== text.notDetected ? ipv4 : null,
    ipv6: ipv6 !== text.notDetected ? ipv6 : null
  };
}

async function updateIpMetadata(preferredIp) {
  if (!preferredIp) {
    setText('country-value', text.notAvailable);
    setText('org-value', text.notAvailable);
    setText('hostname-value', text.notAvailable);
    setText('timezone-value', text.notAvailable);
    return;
  }

  try {
    const url = `index.php?ajax=ipinfo&ip=${encodeURIComponent(preferredIp)}`;
    const data = await fetchJson(url);

    if (!data?.ok || !data.info) {
      throw new Error('Resposta inválida');
    }

    const info = data.info;
    setText('country-value', `${info.country ?? text.notAvailable} (${info.region ?? text.notAvailable}, ${info.city ?? text.notAvailable})`);
    setText('org-value', info.org ?? text.notAvailable);
    setText('hostname-value', info.hostname ?? text.notAvailable);
    setText('timezone-value', info.timezone ?? text.notAvailable);
  } catch (error) {
    setText('country-value', text.notAvailable);
    setText('org-value', text.notAvailable);
    setText('hostname-value', text.notAvailable);
    setText('timezone-value', text.notAvailable);
  }
}

function renderDeviceInfo() {
  const target = document.querySelector('#extra-info');
  if (!target) {
    return;
  }

  const ua = navigator.userAgent || text.notAvailable;
  const lang = navigator.language || text.notAvailable;
  const cores = navigator.hardwareConcurrency || text.notAvailable;
  const memory = navigator.deviceMemory ? `${navigator.deviceMemory} GB` : text.notAvailable;
  const screenSize = `${window.screen.width}x${window.screen.height}`;

  target.innerHTML = `
    <div class="info-block"><span class="label">🌐 <strong>Navegador:</strong></span> <span>${navigator.appName} (${navigator.appVersion})</span></div>
    <div class="info-block"><span class="label">🖥️ <strong>Resolução:</strong></span> <span>${screenSize}</span></div>
    <div class="info-block"><span class="label">🗣️ <strong>Idioma:</strong></span> <span>${lang}</span></div>
    <div class="info-block"><span class="label">⚙️ <strong>CPU lógica:</strong></span> <span>${cores}</span></div>
    <div class="info-block"><span class="label">💾 <strong>Memória estimada:</strong></span> <span>${memory}</span></div>
    <div class="info-block info-block--full"><span class="label">🧭 <strong>User-Agent:</strong></span> <span class="ua-text">${ua}</span></div>
  `;
}

window.addEventListener('DOMContentLoaded', async () => {
  bindCopyButtons();
  renderDeviceInfo();
  const ips = await detectPublicIps();
  await updateIpMetadata(ips.ipv6 || ips.ipv4);
});


async function copyTextToClipboard(value) {
  if (!navigator.clipboard?.writeText) {
    const tempInput = document.createElement('input');
    tempInput.value = value;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    tempInput.remove();
    return;
  }

  await navigator.clipboard.writeText(value);
}

function bindCopyButtons() {
  document.querySelectorAll('.copy-ip').forEach((button) => {
    button.addEventListener('click', async (event) => {
      if (event.button !== 0) {
        return;
      }

      const value = button.dataset.copyValue || button.textContent.trim();
      if (!value || value === text.notDetected || value === text.checking) {
        return;
      }

      const originalText = button.textContent;
      const originalTitle = button.title;

      try {
        await copyTextToClipboard(value);
        button.classList.add('copy-ip--copied');
        button.textContent = text.copied;
        button.title = text.copied;
      } catch (error) {
        button.classList.add('copy-ip--copied');
        button.textContent = 'Falha ao copiar';
        button.title = 'Falha ao copiar';
      }

      window.setTimeout(() => {
        button.classList.remove('copy-ip--copied');
        button.textContent = originalText;
        button.title = originalTitle || text.clickToCopy;
      }, 1200);
    });
  });
}
