# PostVisit Urology — 泌尿科化整合規格書

> **作者：** 袁倫祥 醫師/博士 (Dr. Lun-Hsiang Yuan)
> **基底專案：** [mnedoszytko/postvisit](https://github.com/mnedoszytko/postvisit) (MIT License)
> **目標：** 將 PostVisit.ai 改裝為泌尿科專用 AI 術後/診後伴侶，整合至 [yuanuro.com](https://www.yuanuro.com)

---

## 1. 整體策略

```
yuanuro.com (Vue SPA)                postvisit-urology (Laravel + Vue)
┌─────────────────────┐              ┌────────────────────────────────┐
│  /#/projects        │──── 連結 ───►│  postvisit.yuanuro.com         │
│  /#/psa-calculator  │              │  /demo  (泌尿科 demo)           │
│  AI 助理            │              │  /api/v1/...                    │
└─────────────────────┘              └────────────────────────────────┘
```

**部署方案：** PostVisit fork 作為獨立服務，域名 `postvisit.yuanuro.com`（或子路徑），個人網站透過連結或 iframe 整合。

---

## 2. Demo Scenarios 改裝（最優先）

### 原版（心臟科）→ 泌尿科改裝對照表

| 原版 | 泌尿科版 | 優先序 |
|------|---------|--------|
| Cardiology (PVCs + propranolol) | **BOO (膀胱出口阻塞) + alpha-blocker** | 🔴 P0 |
| Heart failure | **攝護腺癌 (PCa) + 荷爾蒙治療** | 🔴 P0 |
| Hypertension | **BPH + 複合藥物治療 (5-ARI + alpha-blocker)** | 🟡 P1 |
| Endocrinology (diabetes) | **泌尿道感染 (UTI) 反覆發作** | 🟡 P1 |
| Gastroenterology | **膀胱癌 + 膀胱鏡術後** | 🟢 P2 |
| Pulmonology | **腎結石 + ESWL/PCNL 術後衛教** | 🟢 P2 |
| Arrhythmia | **攝護腺切片 (biopsy) 術前術後** | 🟢 P2 |
| —— | **達文西機器人根治性攝護腺切除術後** | 🟢 P2 |

### P0 Demo：BOO 診斷後衛教（對應您的研究）

**患者設定：**
- 姓名：王先生（65歲）
- 主訴：夜尿 × 3、排尿困難、尿流速下降
- 診斷：BPH 合併 BOO（依您的 Two-Stage Model）
- 用藥：Tamsulosin 0.4mg QD（alpha-blocker）
- 檢查：Uroflowmetry Qmax 8.2 mL/s, PVR 120mL, PSA 2.8 ng/mL
- 術式建議：若藥物治療失敗 → TURP / HoLEP

**AI 應回答的問題（測試用）：**
1. "我的攝護腺指數 PSA 2.8 正常嗎？"
2. "這顆藥 Tamsulosin 有什麼副作用？"
3. "我需要開刀嗎？"
4. "飲食上要注意什麼？"
5. "什麼時候要回診？"

---

## 3. 醫學術語資料庫（需新增）

### `demo/scenarios/boo-scenario.json` 結構

```json
{
  "scenario_id": "boo_001",
  "specialty": "urology",
  "condition": "Bladder Outlet Obstruction (BOO)",
  "patient": {
    "name": "王志明",
    "age": 65,
    "gender": "male"
  },
  "visit_context": {
    "diagnosis": ["BPH", "BOO", "LUTS"],
    "medications": [
      {
        "name": "Tamsulosin",
        "brand": "Harnalidge",
        "dose": "0.4mg QD",
        "rxnorm_id": "77492"
      }
    ],
    "investigations": {
      "uroflowmetry": {"qmax": 8.2, "unit": "mL/s"},
      "pvr": {"value": 120, "unit": "mL"},
      "psa": {"value": 2.8, "unit": "ng/mL"}
    }
  },
  "medical_terms": ["BPH", "BOO", "LUTS", "Tamsulosin", "Uroflowmetry", "Qmax", "PVR", "PSA", "TURP", "HoLEP", "alpha-blocker", "5-ARI"]
}
```

### 泌尿科醫學術語解釋清單（需加入 `TermExtractor`）

| 術語 | 解釋（病患語言） |
|------|----------------|
| BPH | 良性攝護腺增生（攝護腺變大但非癌症） |
| BOO | 膀胱出口阻塞（尿流被擋住） |
| PSA | 攝護腺特異性抗原（血液指數，用來偵測攝護腺問題） |
| Qmax | 最大尿流速（測量排尿力道） |
| PVR | 餘尿（排尿後膀胱裡剩下的尿量） |
| TURP | 經尿道攝護腺切除手術 |
| HoLEP | 鈥激光攝護腺剜除術 |
| Tamsulosin | 甲型交感神經阻斷劑（放鬆攝護腺和膀胱頸讓尿液更易流出） |
| Finasteride | 5-甲型還原酶抑制劑（縮小攝護腺體積） |
| Alpha-blocker | 讓攝護腺肌肉放鬆的藥物 |
| Hematuria | 血尿 |
| Cystoscopy | 膀胱鏡檢查 |
| Urodynamics | 尿路動力學檢查 |
| Nephrolithiasis | 腎結石 |
| ESWL | 體外震波碎石術 |
| PCNL | 經皮腎臟鏡碎石術 |
| Radical prostatectomy | 根治性攝護腺切除手術 |
| RALP | 達文西機器人輔助根治性攝護腺切除術 |

---

## 4. 臨床指引資料庫（需新增）

### 建議整合的泌尿科指引

| 指引 | 來源 | 格式 | 優先序 |
|------|------|------|--------|
| EAU Guidelines on BPH/LUTS | European Association of Urology | PDF | 🔴 P0 |
| EAU Guidelines on Prostate Cancer | EAU | PDF | 🔴 P0 |
| AUA Guideline on BPH | American Urological Association | PDF | 🟡 P1 |
| EAU Guidelines on Urolithiasis | EAU | PDF | 🟡 P1 |
| EAU Guidelines on Bladder Cancer | EAU | PDF | 🟢 P2 |
| EAU Guidelines on UTI | EAU | PDF | 🟢 P2 |

**放置路徑：** `demo/guidelines/urology/`

---

## 5. 介面個人化（符合 yuanuro.com 設計系統）

### 色彩系統對應

```css
/* 原版 PostVisit 顏色 → yuanuro.com 品牌色 */
--primary: #1A365D;        /* Navy（原版藍）→ 相同 */
--accent:  #4A6741;        /* Forest Green（yuanuro 強調色） */
--background: #FAF5F0;     /* Warm white（yuanuro 背景色） */
--font-heading: 'Playfair Display', 'Noto Serif TC', serif;
--font-body: 'Source Sans Pro', 'Noto Sans TC', sans-serif;
```

### 需修改的文字內容

| 位置 | 原版 | 泌尿科版 |
|------|------|---------|
| Landing tagline | "The bridge between your visit and your health" | "您與健康之間的橋樑：泌尿科智慧診後伴侶" |
| Demo specialty | Cardiology | Urology |
| Doctor name | Dr. Sarah Chen | 袁倫祥 醫師 |
| Institution | — | 臺大醫院雲林分院泌尿部 |
| Affiliation | — | 教育部部定助理教授 |

---

## 6. 個人網站整合方案

### 方案 A：專案頁面連結（最簡單，P0）

在 `yuanuro.com/#/projects` 新增卡片：

```
┌─────────────────────────────────────┐
│  🤖 PostVisit Urology Demo          │
│  AI 驅動的泌尿科診後伴侶            │
│  幫助患者理解診斷、追蹤用藥         │
│  [查看 Demo →]                       │
└─────────────────────────────────────┘
連結至 postvisit.yuanuro.com/demo
```

### 方案 B：iframe 嵌入（中等複雜度，P1）

```html
<!-- yuanuro.com 的 Vue component -->
<iframe
  src="https://postvisit.yuanuro.com/demo"
  width="100%"
  height="800px"
  frameborder="0"
/>
```

### 方案 C：API 整合（最完整，P2）

yuanuro.com 的 AI 助理直接調用 PostVisit API：

```
yuanuro.com AI 助理
    │
    ├── 一般問診諮詢 → 本地 Claude API
    └── 需要臨床推理 → postvisit.yuanuro.com/api/v1/chat
                         (完整 ContextAssembler + Tool Use)
```

---

## 7. 部署架構

```
Domain: postvisit.yuanuro.com
┌─────────────────────────────────────────┐
│  Docker Compose                         │
│  ├── nginx (port 80/443)                │
│  ├── PHP-FPM (Laravel 12)               │
│  ├── PostgreSQL 17                      │
│  └── Queue Worker (Whisper STT)         │
│                                         │
│  Environment:                           │
│  ANTHROPIC_API_KEY=sk-ant-...           │
│  DEMO_LOGIN_ENABLED=true                │
│  AI_TIER=balanced                       │
└─────────────────────────────────────────┘
```

**推薦部署平台：** Hetzner VPS (€4-8/月) 或 DigitalOcean Droplet

---

## 8. 實作優先序

### Phase 0 — Fork & 驗證（1 天）
- [x] Fork `mnedoszytko/postvisit` → `lunhsiangyuan/postvisit-urology`
- [ ] 本地 Docker 啟動驗證
- [ ] 確認 `patient@demo` / `doctor@demo` 可登入

### Phase 1 — 泌尿科化（2-3 天）
- [ ] 新增 BOO demo scenario（`DemoSeeder`）
- [ ] 更新醫學術語清單（`TermExtractor`）
- [ ] 替換品牌文字（Landing, Layout）
- [ ] 套用 yuanuro.com 色彩系統

### Phase 2 — 臨床內容（1-2 天）
- [ ] 整合 EAU Guidelines（BPH + PCa）
- [ ] 新增攝護腺癌 demo scenario
- [ ] 中文化主要介面文字

### Phase 3 — 整合 yuanuro.com（1 天）
- [ ] 部署至 `postvisit.yuanuro.com`
- [ ] yuanuro.com `/#/projects` 新增連結卡片
- [ ] 測試完整 demo 流程

---

## 9. 關鍵技術文件

- **原版 API 文件：** `docs/api.md`（111 個 endpoints）
- **AI 架構深挖：** `docs/opus-4.6-deep-dive.md`
- **Demo 腳本：** `docs/demo-guide.md`
- **健康法規合規：** `docs/healthcare-compliance.md`
- **Demo 情境設定：** `database/seeders/DemoSeeder.php`
- **AI Prompts：** `prompts/` 目錄（14 個 markdown 檔案）

---

## 10. 注意事項

1. **不要放真實病患資料** — 全部使用虛構情境（原版設計已考慮）
2. **免責聲明** — 保留原版「AI 不替代醫師判斷」的聲明
3. **Rate Limiting** — 公開 demo 保留原版 10 req/min 限制
4. **ANTHROPIC_API_KEY** — 需要自行申請 Anthropic API key

---

*本規格書由 Claude Sonnet 4.6 協助撰寫。*
*Last updated: 2026-02-24*
