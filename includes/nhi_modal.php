<?php
// 健保卡讀取 Modal（patients.php / register.php 共用）
// 用法：頁面尾端 require 此檔；先設 $nhi_mode = 'form'（填表）或 'register'（選取／自動建檔）
?>
<div class="modal fade" id="nhiModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">📇 健保卡查尋／建檔</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="small text-muted mb-2">用 CCID 介面讀卡機（USB 插上即免驅動）。插卡後按「開始讀取」；沒有硬體可用下方「手動輸入卡號」（demo：A12345678、N01234567）。</div>
<div class="row g-2 mb-1">
<div class="col"><button type="button" class="btn btn-outline-primary btn-sm w-100" id="nhiConnectBtn">🖥 連線讀卡機</button></div>
<div class="col"><button type="button" class="btn btn-primary btn-sm w-100" id="nhiScanBtn">▶ 開始讀取</button></div>
</div>
<div id="nhiStatus" class="form-text small text-muted"></div>
<div id="nhiRaw" class="small text-muted mt-1"></div>
<div class="row g-2 mt-2">
<div class="col"><input id="nhiManualInput" class="form-control form-control-sm" maxlength="9" placeholder="手動輸入健保卡號" autocomplete="off"></div>
<div class="col-auto"><button type="button" class="btn btn-outline-secondary btn-sm" id="nhiManualBtn">查詢</button></div>
</div>
<hr>
<div id="nhiResult" class="alert alert-info small d-none mb-2"></div>
<div id="nhiCreateWrap" class="d-none">
<div class="alert alert-warning small mb-2" id="nhiCreateMsg"></div>
<div class="row g-2 small">
<div class="col-6"><input id="cName" class="form-control form-control-sm" placeholder="姓名"></div>
<div class="col-3"><select id="cSex" class="form-select form-select-sm"><option>男</option><option>女</option><option>其他</option></select></div>
<div class="col-3"><input id="cBdate" type="date" class="form-control form-control-sm"></div>
<div class="col-4"><input id="cId" class="form-control form-control-sm" placeholder="身分證（選填）"></div>
<div class="col-8"><input id="cNhi" class="form-control form-control-sm" placeholder="健保卡號" maxlength="9"></div>
</div>
<button type="button" class="btn btn-success btn-sm w-100 mt-2" id="nhiCreateBtn">➕ <?php echo ($nhi_mode ?? '') === 'register' ? '建立病歷並選取' : '填入表單'; ?></button>
</div>
<?php if (($nhi_mode ?? '') === 'register'): ?>
<button type="button" class="btn btn-outline-success btn-sm w-100 mt-2 d-none" id="nhiSelectBtn">✅ 選取此病人掛號</button>
<?php endif; ?>
</div>
</div></div>
</div>
<script>window.NHI_MODE = <?= json_encode($nhi_mode ?? 'form') ?>;</script>
<script src="assets/nhi_card.js"></script>
