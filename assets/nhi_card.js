/* 健保卡 WebUSB 讀取（CCID 介面讀卡機）＋ 手動卡號查詢 ＋ 病歷選取/建檔
   - MODE='form'     ：patients.php，讀到資料「填入新增病人表單」
   - MODE='register'：register.php，查到既有病歷「選取掛號」；查不到「建立病歷並選取」
   讀卡機用標準 CCID 協定（bRequest=0x3F vendor request 送 APDU），Chrome / Edge 支援 WebUSB
   （需 localhost 或 HTTPS 安全情境）。
   卡號/生日採「全掃描自動偵測」，不綁死欄位移；性別字節仍是估算值，
   若解錯請改 LAYOUT.sexOff（界面有顯示原始 hex 供比對）。 */
(function () {
  const MODE = window.NHI_MODE || 'form';
  const $ = id => document.getElementById(id);
  let dev = null, iface = 0, pollTimer = null;
  let foundPatient = null;   // 查到的既有病歷
  let cardData = null;       // 卡上/手動輸入的欄位

  const NHI_APDU = {
    select: [0x00, 0xA4, 0x01, 0x0C, 0x07, 0xDF, 0x51, 0x23, 0x00, 0x00, 0x00, 0x00], // SELECT 個人資訊檔（P2=0x0C：無回應模式）
    read:   [0x00, 0xB0, 0x00, 0x00, 0x45],                                          // READ BINARY 69 bytes
  };
  const LAYOUT = { sexOff: 1 }; // 性別字位移（估算值；卡號/生日已改全掃描，不依賴固定位移）
  const SEX = { 1: '男', 2: '女', 3: '其他' };

  function status(msg, cls) {
    const el = $('nhiStatus');
    el.textContent = msg;
    el.className = 'form-text small ' + (cls || 'text-muted');
  }

  function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

  /* ---------- WebUSB CCID ---------- */
  async function connect() {
    stopPoll();
    if (!('usb' in navigator)) {
      status('此瀏覽器不支援 WebUSB：請用 Chrome/Edge 開「localhost 或 HTTPS」的網址；或直接用下方「手動輸入卡號」查詢', 'text-danger');
      return;
    }
    try {
      // 不設 filter：各廠讀卡機 vendor 不同，讓使用者自己選（選「智慧卡讀取器」那台）
      dev = await navigator.usb.requestDevice({ filters: [] });
      try {
        await dev.open();
      } catch (openErr) {
        const m = String(openErr && openErr.message || openErr);
        if (m.indexOf('denied') !== -1 || /Failed to execute 'open'/i.test(m)) {
          dev = null;
          status('該讀卡機被 Windows「智慧卡」系統佔用（CCID 讀卡機常見）：請依「停用 Windows 智慧卡」說明停用後，再按一次連線', 'text-danger');
          return;
        }
        dev = null;
        status('連線失敗：' + m, 'text-danger');
        return;
      }
      try {
        const conf = dev.configurations[0];
        await dev.selectConfiguration(conf.configurationValue);
        const itf = conf.interfaces.find(i => i.interfaceClass === 0x0B) || conf.interfaces[0];
        await dev.claimInterface(itf.interfaceNumber);
        iface = itf.interfaceNumber;
      } catch (claimErr) {
        try { await dev.close(); } catch (_) { }
        dev = null;
        status('介面占用失敗：' + claimErr.message + '（讀卡機可能被其他程式使用）', 'text-danger');
        return;
      }
      status('已連線讀卡機，請插入健保卡', 'text-success');
    } catch (e) {
      const m = String(e.message || e);
      if (m.indexOf('not found') !== -1) {
        status('連線失敗：讀卡機已斷開、或瀏覽器權限已被撤銷（瀏覽器右上角網站設定清除該裝置後，重新選「連線讀卡機」再選一次）', 'text-danger');
      } else {
        status('連線失敗：' + m, 'text-danger');
      }
    }
  }

  async function apdu(bytes) {
    const res = await dev.controlTransfer(
      { bmRequestType: 0x41, bRequest: 0x3f, wValue: 0x0000, wIndex: iface },
      new Uint8Array(bytes)
    );
    const buf = new Uint8Array(await res.buffer);
    const total = buf[0];                          // CCID 回應總長（含首 byte 自身）
    const rest = Array.from(buf.slice(1, total > 0 ? total : buf.length)); // APDU 回應 byte
    const swBytes = rest.slice(-2);
    const sw = swBytes.length === 2 ? swBytes[0] * 256 + swBytes[1] : 0;
    const body = rest.slice(0, Math.max(0, rest.length - 2)); // APDU 資料體（SW 前全部）
    return { sw, body };
  }

  /* ---------- 解析（全掃描，不綁死欄位移） ---------- */
  // 找生日：6 byte DDMMYY，或 8 byte DD/MM/YY、DD MM YY（月 01-12、日 01-31 驗證；century 自動取「未超過今年」的那個）
  function findBdate(raw) {
    const toS = (off, len) => {
      let s = '';
      for (let i = 0; i < len; i++) {
        const c = raw[off + i];
        s += (c >= 0x30 && c <= 0x39 || c === 0x2f || c === 0x20) ? String.fromCharCode(c) : '';
      }
      return s;
    };
    const fmt = (y, mm, dd) => {
      const y20 = 2000 + y;
      const year = y20 > new Date().getFullYear() ? 1900 + y : y20;
      return year + '-' + mm + '-' + dd;
    };
    for (let off = 0; off + 6 <= raw.length; off++) {
      const m = toS(off, 6).match(/^(\d{2})(\d{2})(\d{2})$/);
      if (m && +m[2] >= 1 && +m[2] <= 12 && +m[1] >= 1 && +m[1] <= 31) return fmt(+m[3], m[2], m[1]);
    }
    for (let off = 0; off + 8 <= raw.length; off++) {
      const m = toS(off, 8).match(/^(\d{2})[/ ](\d{2})[/ ](\d{2})$/);
      if (m && +m[2] >= 1 && +m[2] <= 12 && +m[1] >= 1 && +m[1] <= 31) return fmt(+m[3], m[2], m[1]);
    }
    return '';
  }

  // 在回傳 byte 中找「大寫字母+8 位數字」的健保卡號（位元組級：先 ASCII，找不到再試 EBCDIC）
  function findNhiNo(raw) {
    for (let off = 0; off + 9 <= raw.length; off++) {
      if (raw[off] >= 0x41 && raw[off] <= 0x5a) {
        let ok = true;
        for (let i = 1; i < 9; i++) if (raw[off + i] < 0x30 || raw[off + i] > 0x39) { ok = false; break; }
        if (ok) {
          let s = String.fromCharCode(raw[off]);
          for (let i = 1; i < 9; i++) s += String.fromCharCode(raw[off + i]);
          return s;
        }
      }
    }
    for (let off = 0; off + 9 <= raw.length; off++) {
      if (raw[off] >= 0xc1 && raw[off] <= 0xf0) {
        let ok = true;
        for (let i = 1; i < 9; i++) if (raw[off + i] < 0xf0 || raw[off + i] > 0xf9) { ok = false; break; }
        if (ok) {
          let s = String.fromCharCode(raw[off] - 0x80);
          for (let i = 1; i < 9; i++) s += String.fromCharCode((raw[off + i] - 0xf0) + 0x30);
          return s;
        }
      }
    }
    return '';
  }

  function parseNhi(body) {
    const d = { nhi_no: '', sex: '', bdate: '', name: '', id_no: '', raw: body };
    d.nhi_no = findNhiNo(body);
    d.bdate = findBdate(body);
    if (body.length > LAYOUT.sexOff) d.sex = SEX[body[LAYOUT.sexOff]] || ''; // 估算值
    return d;
  }

  function showRaw(body) {
    const el = $('nhiRaw');
    if (!body || !body.length) { el.textContent = ''; return; }
    el.textContent = '原始資料：' + body.map(b => b.toString(16).padStart(2, '0')).join(' ');
  }

  async function readCard() {
    if (!dev) { status('請先「連線讀卡機」再讀取', 'text-danger'); return; }
    try {
      status('讀取中…');
      // SELECT P2=0x0C 為「無回應」模式：SW 不是 0x9000 不代表沒卡，仍繼續 READ 試一次
      const sel = await apdu(NHI_APDU.select);
      const rd = await apdu(NHI_APDU.read);
      if (rd.sw !== 0x9000 && rd.sw !== 0x9001) {
        status('未讀到卡資料（SW=' + rd.sw.toString(16).padStart(4, '0') + '）：確認卡已插入讀卡機；卡代不同則看下方原始資料調整 NHI_APDU，或改手動輸入卡號', 'text-warning');
        if (sel.body && sel.body.length) showRaw(sel.body);
        return;
      }
      cardData = parseNhi(rd.body);
      showRaw(rd.body);
      stopPoll();
      if (cardData.nhi_no) {
        status('讀卡成功，卡號 ' + cardData.nhi_no, 'text-success');
        lookup(cardData.nhi_no, cardData);
      } else {
        status('讀到卡資料但解析不出卡號（SW=' + rd.sw.toString(16).padStart(4, '0') + '，性別=' + (cardData.sex || '?') + ' 生日=' + (cardData.bdate || '?') + '）：看原始 hex 調整，或手動輸入卡號再查詢', 'text-warning');
        $('nhiManualInput').focus();
      }
    } catch (e) {
      status('讀卡機通訊錯誤：' + e.message + '（可重新「連線讀卡機」再試）', 'text-danger');
    }
  }

  function startPoll() { stopPoll(); readCard(); pollTimer = setInterval(readCard, 2000); }

  /* ---------- 查詢病歷 ---------- */
  async function lookup(cardNo, data) {
    cardData = Object.assign({}, data || {}, { nhi_no: cardNo });
    status('查詢病歷…');
    let res;
    try {
      res = await fetch('api/nhi_lookup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          card_no: cardNo,
          name: cardData.name, sex: cardData.sex, bdate: cardData.bdate, id_no: cardData.id_no,
        }),
      }).then(r => r.json());
    } catch (e) { status('查詢失敗：' + e.message, 'text-danger'); return; }

    const panel = $('nhiResult');
    panel.classList.remove('d-none');
    if (res.found) {
      foundPatient = res.patient;
      panel.className = 'alert alert-success small mb-2';
      panel.textContent = '✅ 找到病歷 ' + res.patient.chart_no + '（' + res.patient.name + '）';
      $('nhiCreateWrap').classList.add('d-none');
      const selBtn = $('nhiSelectBtn');
      if (selBtn) selBtn.classList.remove('d-none');
    } else {
      foundPatient = null;
      const d = Object.assign({}, res.demo || res.extra || {}, cardData);
      panel.className = 'alert alert-warning small mb-2';
      panel.textContent = '⚠ 查無此卡號病歷' + (res.source === 'demo' ? '（demo 樣本資料）' : '') + '，可建立新病歷';
      if ($('nhiSelectBtn')) $('nhiSelectBtn').classList.add('d-none');
      $('nhiCreateWrap').classList.remove('d-none');
      $('cName').value = d.name || '';
      $('cSex').value = d.sex && ['男', '女', '其他'].includes(d.sex) ? d.sex : '男';
      $('cBdate').value = d.bdate || '';
      $('cId').value = d.id_no || '';
      $('cNhi').value = cardNo;
      $('nhiCreateMsg').textContent = '卡號 ' + cardNo + (d.name ? '，卡上/查得姓名 ' + d.name : '；姓名請確認後建檔。') + '（性別為估算，建檔前請確認）';
    }
  }

  /* ---------- 建檔／選取 ---------- */
  async function createPatient() {
    const cardNo = ($('cNhi').value || '').toUpperCase().trim();
    if (!$('cName').value.trim()) { status('姓名必填', 'text-danger'); return; }
    status('建立病歷…');
    let res;
    try {
      res = await fetch('api/patient_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: $('cName').value.trim(),
          sex: $('cSex').value,
          bdate: $('cBdate').value,
          id_no: $('cId').value.trim(),
          nhi_no: cardNo,
        }),
      }).then(r => r.json());
    } catch (e) { status('建檔失敗：' + e.message, 'text-danger'); return; }

    if (!res.ok) { status(res.error || '建檔失敗', 'text-danger'); return; }
    if (res.existing) { lookup(res.existing.nhi_no || cardNo, cardData); return; }

    if (MODE === 'register') {
      const sel = $('regPatientSelect');
      if (sel) {
        const opt = document.createElement('option');
        opt.value = res.id;
        opt.textContent = res.chart_no + ' ' + res.name + '（掃卡建檔）';
        sel.appendChild(opt);
        sel.value = res.id;
      }
      $('nhiResult').className = 'alert alert-success small mb-2';
      $('nhiResult').textContent = '✅ ' + res.message + '，已選取，可確認掛號';
      $('nhiCreateWrap').classList.add('d-none');
      status(res.message + '，可確認掛號', 'text-success');
    } else {
      fillForm({ chart_no: res.chart_no, name: res.name, nhi_no: cardNo }, 'created');
      status('已建檔 ' + res.chart_no + '（表單已填，按存檔即可）', 'text-success');
    }
  }

  function selectFound() {
    if (!foundPatient) return;
    if (MODE === 'register') {
      const sel = $('regPatientSelect');
      if (sel) {
        sel.value = foundPatient.id;
        if (sel.selectedIndex < 0) {
          const opt = document.createElement('option');
          opt.value = foundPatient.id;
          opt.textContent = foundPatient.chart_no + ' ' + foundPatient.name;
          sel.appendChild(opt);
          sel.value = foundPatient.id;
        }
      }
      status('已選取 ' + foundPatient.chart_no + ' ' + foundPatient.name + '，可確認掛號', 'text-success');
    } else {
      fillForm(foundPatient, 'exists');
      status('病歷已存在 ' + foundPatient.chart_no + '，無需重複建檔', 'text-warning');
    }
  }

  /* form mode：填入 patients.php「新增病人」表單 */
  function fillForm(p, how) {
    const set = (name, val) => {
      const el = document.querySelector('input[name="' + name + '"], select[name="' + name + '"]');
      if (el) el.value = val || '';
    };
    set('name', p.name);
    set('gender', p.gender || p.sex);
    set('birth_date', p.birth_date || p.bdate);
    set('id_no', p.id_no);
    set('nhi_no', p.nhi_no);
  }

  /* ---------- 綁定 ---------- */
  $('nhiConnectBtn').addEventListener('click', connect);
  $('nhiScanBtn').addEventListener('click', startPoll);
  $('nhiManualBtn').addEventListener('click', () => {
    const v = $('nhiManualInput').value.toUpperCase().trim();
    if (v === '') { status('請輸入卡號', 'text-warning'); return; }
    lookup(v, { nhi_no: v });
  });
  $('nhiManualInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); $('nhiManualBtn').click(); }
  });
  $('nhiCreateBtn').addEventListener('click', createPatient);
  if ($('nhiSelectBtn')) $('nhiSelectBtn').addEventListener('click', selectFound);
  $('nhiModal').addEventListener('hidden.bs.modal', stopPoll);
})();