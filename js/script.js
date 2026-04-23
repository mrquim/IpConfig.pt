const IPINFO_TOKEN = '4e8af62f798d23';

const ipv4El = document.getElementById('ipv4-value');
const ipv6El = document.getElementById('ipv6-value');
const countryEl = document.getElementById('country-value');
const orgEl = document.getElementById('org-value');
const hostnameEl = document.getElementById('hostname-value');
const timezoneEl = document.getElementById('timezone-value');
const extraInfoEl = document.getElementById('extra-info');

document.getElementById('year').textContent = new Date().getFullYear();

function setButtonValue(el, value) {
  el.textContent = value;
  el.dataset.copyValue = value;
}

async function fetchText(url) {
  const response = await fetch(url, { cache: 'no-store' });
  if (!response.ok) throw new Error('Erro ao obter dados');
  return await response.text();
}

async function fetchJson(url) {
  const response = await fetch(url, { cache: 'no-store' });
  if (!response.ok) throw new Error('Erro ao obter JSON');
  return await response.json();
}

async function getIPv4() {
  try {
    const ip = await fetchText('https://api.ipify.org');
    return ip.trim();
  } catch {
    return null;
  }
}

async function getIPv6() {
  try {
    const ip = await fetchText('https://api6.ipify.org');
    return ip.trim();
  } catch {
    return null;
  }
}

async function getIpInfo(ip) {
  try {
    const data = await fetchJson(`https://ipinfo.io/${encodeURIComponent(ip)}/json?token=${IPINFO_TOKEN}`);
    return {
      ip: data.ip || ip,
      country: data.country || 'N/A',
      region: data.region || 'N/A',
      city: data.city || 'N/A',
      org: data.org || 'N/A',
      hostname: data.hostname || 'N/A',
      timezone: data.timezone || 'N/A'
    };
  } catch {
    return null;
  }
}

function renderDeviceInfo() {
  const items = [
    ['Navegador', navigator.userAgent || 'N/A'],
    ['Idioma', navigator.language || 'N/A'],
    ['Resolução', `${window.screen.width} x ${window.screen.height}`],
    ['Plataforma', navigator.platform || 'N/A'],
    ['Cookies ativados', navigator.cookieEnabled ? 'Sim' : 'Não'],
    ['Memória do dispositivo', navigator.deviceMemory ? `${navigator.deviceMemory} GB` : 'N/A'],
    ['CPU lógica', navigator.hardwareConcurrency || 'N/A'],
    ['Janela visível', `${window.innerWidth} x ${window.innerHeight}`]
  ];

  extraInfoEl.innerHTML = items.map(([label, value]) => `
    <div class="info-block">
      <span class="label"><strong>${label}:</strong></span>
      <span>${value}</span>
    </div>
  `).join('');
}

async function init() {
  renderDeviceInfo();

  const [ipv4, ipv6] = await Promise.all([getIPv4(), getIPv6()]);

  setButtonValue(ipv4El, ipv4 || 'Não detetado');
  setButtonValue(ipv6El, ipv6 || 'Não detetado');

  const bestIp = ipv6 || ipv4;

  if (!bestIp) {
    countryEl.textContent = 'Não foi possível detetar';
    orgEl.textContent = 'Não foi possível detetar';
    hostnameEl.textContent = 'Não foi possível detetar';
    timezoneEl.textContent = 'Não foi possível detetar';
    return;
  }

  const info = await getIpInfo(bestIp);

  if (!info) {
    countryEl.textContent = 'Não foi possível obter dados';
    orgEl.textContent = 'Não foi possível obter dados';
    hostnameEl.textContent = 'Não foi possível obter dados';
    timezoneEl.textContent = 'Não foi possível obter dados';
    return;
  }

  countryEl.textContent = `${info.country} (${info.region}, ${info.city})`;
  orgEl.textContent = info.org;
  hostnameEl.textContent = info.hostname;
  timezoneEl.textContent = info.timezone;
}

document.addEventListener('click', async (event) => {
  const btn = event.target.closest('.copy-ip');
  if (!btn) return;

  const value = btn.dataset.copyValue || '';
  if (!value || value === 'A verificar...' || value === 'Não detetado') return;

  try {
    await navigator.clipboard.writeText(value);
    const originalText = btn.textContent;
    btn.textContent = 'Copiado!';
    setTimeout(() => {
      btn.textContent = value;
    }, 1200);
  } catch {}
});

init();