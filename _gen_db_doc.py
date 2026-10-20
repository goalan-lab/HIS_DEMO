# -*- coding: utf-8 -*-
# 產生 技術文件/資料庫說明.docx（python-docx）
# 用法：python _gen_db_doc.py
import os
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
from _db_doc_tables import TABLES

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '技術文件', '資料庫說明.docx')

NAVY = RGBColor(0x1F, 0x3B, 0x6D)
GRAY = RGBColor(0x55, 0x55, 0x55)

def ea(run, size=None, bold=None, color=None, font='微軟正黑體'):
    run.font.name = 'Calibri'
    if size: run.font.size = Pt(size)
    if bold is not None: run.bold = bold
    if color: run.font.color.rgb = color
    rpr = run._r.get_or_add_rPr()
    rf = rpr.find(qn('w:rFonts'))
    if rf is None:
        rf = OxmlElement('w:rFonts'); rpr.append(rf)
    rf.set(qn('w:eastAsia'), font)

def shade(cell, hexcolor):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear'); shd.set(qn('w:fill'), hexcolor)
    tcPr.append(shd)

def cell_text(cell, text, size=10, bold=False, color=None, center=False):
    cell.text = ''
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(2); p.paragraph_format.space_before = Pt(2)
    if center: p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run(text if text else '—')
    ea(r, size=size, bold=bold, color=color)

def h1(doc, text):
    p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(18)
    r = p.add_run(text); ea(r, size=15, bold=True, color=NAVY)
    p2 = p; # underline bar
    pPr = p._p.get_or_add_pPr()
    pBdr = OxmlElement('w:pBdr'); bottom = OxmlElement('w:bottom')
    bottom.set(qn('w:val'), 'single'); bottom.set(qn('w:sz'), '12')
    bottom.set(qn('w:space'), '2'); bottom.set(qn('w:color'), '1F3B6D')
    pBdr.append(bottom); pPr.append(pBdr)
    return p

def h2(doc, text):
    p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(12)
    r = p.add_run(text); ea(r, size=12, bold=True, color=RGBColor(0x2E, 0x50, 0x88))
    return p

def note(doc, text):
    p = doc.add_paragraph()
    r = p.add_run(text); ea(r, size=9.5, color=GRAY)
    p.paragraph_format.left_indent = Cm(0.3)
    return p

def bullet(doc, text, size=10.5):
    p = doc.add_paragraph(style='List Bullet')
    r = p.add_run(text); ea(r, size=size)
    return p

def field_table(doc, rows):
    t = doc.add_table(rows=1, cols=4)
    t.style = 'Table Grid'; t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, htxt in enumerate(['欄位', '型別', '限制／預設', '說明']):
        cell_text(t.rows[0].cells[i], htxt, size=10, bold=True, color=NAVY, center=True)
        shade(t.rows[0].cells[i], 'D9E2F3')
    for row in rows:
        c = t.add_row().cells
        for i, v in enumerate(row):
            cell_text(c[i], v, size=10, center=(i in (1, 2)))
    for row in t.rows:
        row.cells[0].width = Cm(3.6); row.cells[1].width = Cm(3.6)
        row.cells[2].width = Cm(4.4); row.cells[3].width = Cm(5.0)
    doc.add_paragraph()
    return t

# ---------- 文件開始 ----------
doc = Document()
normal = doc.styles['Normal']
normal.font.name = 'Calibri'; normal.font.size = Pt(10.5)
rf = normal.element.get_or_add_rPr().find(qn('w:rFonts'))
if rf is None:
    rf = OxmlElement('w:rFonts'); normal.element.get_or_add_rPr().append(rf)
rf.set(qn('w:eastAsia'), '微軟正黑體')

# 標題頁
p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
for _ in range(4): p.add_run().add_break()
r = p.add_run('HIS_DEMO 醫院管理系統'); ea(r, size=28, bold=True, color=NAVY)
p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('資料庫結構說明文件'); ea(r, size=22)
p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('（MariaDB / MySQL 10.x ・ his_demo）'); ea(r, size=12, color=GRAY)
p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('版本：MVP 2026-10　｜　日期：2026-10-20\n來源：database/schema.sql、install.php、config/permissions.php'); ea(r, size=10.5, color=GRAY)
doc.add_page_break()

# 一、概覽
h1(doc, '一、資料庫概覽')
p = doc.add_paragraph()
r = p.add_run('HIS_DEMO 使用單一資料庫 his_demo，字元集 utf8mb4 / utf8mb4_unicode_ci，'
                       '引擎 InnoDB（支援外鍵與交易）。MVP 共 22 張表，依功能分六群：')
ea(r, size=10.5)
for s in ['帳號與角色：users',
          '病歷與門診掛號：patients、doctors、schedules、registrations',
          '批價、收據與退費：bills、refunds',
          '藥局與庫存：drugs、prescriptions、stock_txns、stocktakes、stocktake_items',
          '住院與護理：wards、beds、admissions、ipd_orders、nursing_notes',
          '手術：op_rooms、surgeries',
          '檢驗檢查：exam_items、exam_orders、exam_results']:
    bullet(doc, s)
note(doc, '建立方式：第一次開啟 install.php 會自動建庫建表；亦可直接匯入 database/schema.sql。'
          '舊安裝補分角色 demo 帳號：執行 _migrate_roles.php 一次即可。'
          '角色權限邏輯在 config/permissions.php（ROLE_PAGE_ACCESS、can()），與本文件第八節對照。')

# 1.1 表清單
h2(doc, '1.1 表清單（22 張）')
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
for i, htxt in enumerate(['表名', '用途', '狀態機／關鍵約束']):
    cell_text(t.rows[0].cells[i], htxt, size=10, bold=True, color=NAVY, center=True)
    shade(t.rows[0].cells[i], 'D9E2F3')
for name, label, desc, rows, _ in TABLES:
    c = t.add_row().cells
    cell_text(c[0], name, size=10)
    cell_text(c[1], label, size=10)
    # 從欄位抓 ENUM status 或 UNIQUE 提示
    hint = []
    for f in rows:
        if 'ENUM' in (f[1] or '') and f[0] in ('status', 'txn_type', 'anesthesia', 'category', 'slot'):
            vals = f[1].split('ENUM')[1]
            hint.append(f[0] + ': ' + vals.strip("()").replace("'", ''))
    if hint:
        cell_text(c[2], '； '.join(hint[:2]), size=9)
    else:
        cell_text(c[2], '—', size=9)
for row in t.rows:
    row.cells[0].width = Cm(4.0); row.cells[1].width = Cm(6.0); row.cells[2].width = Cm(6.0)

# ===== 二〜八：分群表詳細說明 =====
GROUPS = [
    ('二', '帳號與角色（RBAC）',
     'users 記錄所有可登入帳號。role 四選一：主管（全權）／醫師／護理／收銀；'
     '舊值「掛號」「管理」在程式層 normalize_role() 自動視為主管。權限表集中於 config/permissions.php。',
     ['users']),
    ('三', '病歷與門診掛號',
     '掛號流程：選病人＋班表→寫入 registrations（status=掛號）→診間叫號（queue_no/called_at）→'
     '看診開處方（status=待批價）。診間號碼由 register.php 依就診日＋時段同科別 queue_no 最大值＋1 產生。',
     ['patients', 'doctors', 'schedules', 'registrations']),
    ('四', '批價、收據與退費',
     '批價（billing.php）把 掛號費＋診察費＋藥費＋檢驗檢查＋門診手術 合計寫入 bills；'
     '收據（receipt.php）可隨時重印；退費把 bills 標記 voided 並寫 refunds 紀錄（同步取消門診手術）。',
     ['bills', 'refunds']),
    ('五', '藥局、處方與庫存',
     '處方（prescriptions）是掛號單與藥品間的中繼表；每次庫存異動（進貨/發藥/退藥回庫/盤點調整/報廢）'
     '都記一筆 stock_txns 流水並記錄前後結存供稽核。盤點（stocktakes＋stocktake_items）比對系統量與實量，'
     '入帳時差額寫回 drugs.stock。',
     ['drugs', 'prescriptions', 'stock_txns', 'stocktakes', 'stocktake_items']),
    ('六', '住院與護理',
     '住院：選空床→admissions（bed=佔床）→醫囑（ipd_orders）→護理紀錄（nursing_notes）→'
     '出院結算（病床日數×bed_fee＋醫囑費＋住院手術費，併入 bills；bed 轉清潔中→清潔完回空床）。',
     ['wards', 'beds', 'admissions', 'ipd_orders', 'nursing_notes']),
    ('七', '手術排程',
     '手術可掛「住院單」（admission_id，手術費併出院結算）或「門診掛號單」（reg_id，手術費併批價），'
     '兩者擇一避免雙重計費。已併入帳單的門診手術不可直接取消，必須走退費流程（退費會同步作廢手術）。',
     ['op_rooms', 'surgeries']),
    ('八', '檢驗檢查',
     '檢驗與影像檢查同表管理（exam_items.category）。流程：開立→檢體採集（護理）→檢驗中→'
     '已發報告（醫師，result.php 可覆寫），全程可取消（取消不計費）。',
     ['exam_items', 'exam_orders', 'exam_results']),
]
TMAP = {t[0]: t for t in TABLES}
for num, gtitle, gintro, names in GROUPS:
    h1(doc, f'{num}、{gtitle}')
    doc.add_paragraph(gintro)
    for nm in names:
        tname, tlabel, tdesc, rows, tnote = TMAP[nm]
        h2(doc, f'{tname}（{tlabel}）')
        doc.add_paragraph(tdesc)
        field_table(doc, rows)
        if tnote:
            note(doc, '說明：' + tnote)

# 九、實體關係
h1(doc, '九、實體關係總覽')
doc.add_paragraph('主要外鍵鏈（由主到從）：')
for x in [
    'patients 1─N registrations 1─1 bills；bills 1─N refunds',
    'doctors 1─N schedules；registrations N─1 doctors',
    'drugs 1─N prescriptions（經 registrations）；drugs 1─N stock_txns',
    'stocktakes 1─N stocktake_items N─1 drugs',
    'wards 1─N beds；beds 1─N admissions；admissions N─1 patients / doctors',
    'admissions 1─N ipd_orders / nursing_notes / surgeries',
    'registrations 1─N surgeries（門診手術）；op_rooms 1─N surgeries',
    'exam_items 1─N exam_orders 1─1 exam_results；exam_orders 可掛 registrations 或 admissions']:
    bullet(doc, x)
note(doc, '全部表為 InnoDB；外鍵未指定 ON DELETE 動作（預設 RESTRICT），刪病人前須先清掛號單等從表。')

# 十、命名慣例與流水號
h1(doc, '十、命名慣例與流水號')
for x in [
    '單號規則統一由 includes/functions.php 之 gen_no($pdo, prefix, table, col) 產生：前綴＋yyyymmdd＋當日 3 碼流水（查該表當日筆數＋1）。',
    '前綴對照：P=病歷 patients.chart_no；R=掛號 registrations.reg_no；B=帳單 bills.bill_no；A=住院 admissions.adm_no；O=手術 surgeries.op_no；S=盤點 stocktakes.st_no。',
    '金額一律 INT 元（無小數）；日期時間用 DATE / DATETIME / TIMESTAMP；布林用 TINYINT(1) 0/1。',
    '業務表皆帶 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP；操作人記 VARCHAR(50) username（純文字，非外鍵，方便稽核）。']:
    bullet(doc, x)

# 十一、種子資料
h1(doc, '十一、種子資料（schema.sql / install.php）')
for x in [
    'users：admin（主管）／doctor（醫師）／nurse（護理）／cashier（收銀），密碼皆 his1234（install.php 以 bcrypt 重設）。',
    'doctors：王大明（家醫科 101）、李小華（內科 102）、陳美玲（小兒科 103）。',
    'drugs：普拿疼、感冒糖漿、胃藥、抗生素、眼藥水 5 項。',
    'install.php 另補病房/病床、刀房、檢驗檢查項目與班表，使 demo 各流程可直接操作。']:
    bullet(doc, x)

# 頁尾頁碼
sec = doc.sections[0]
fp = sec.footer.paragraphs[0]
fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
fld = OxmlElement('w:fldSimple'); fld.set(qn('w:instr'), 'PAGE')
r = OxmlElement('w:r'); fld.append(r); fp._p.append(fld)
r2 = OxmlElement('w:r'); t2 = OxmlElement('w:t'); t2.text = ' / '
r2.append(t2); fp._p.append(r2)
fld2 = OxmlElement('w:fldSimple'); fld2.set(qn('w:instr'), 'NUMPAGES')
r3 = OxmlElement('w:r'); fld2.append(r3); fp._p.append(fld2)
rr = OxmlElement('w:r'); ft = OxmlElement('w:t'); ft.text = '頁'
rr.append(ft); fld2.append(rr)

os.makedirs(os.path.dirname(OUT), exist_ok=True)
doc.save(OUT)
print('OK:', OUT)

