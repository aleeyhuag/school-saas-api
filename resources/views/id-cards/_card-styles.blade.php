* { box-sizing: border-box; }
html, body { margin:0; padding:0; font-family:'DejaVu Sans',sans-serif; color:#1a1a1a; background:#fff; }

/* One positioned card per PDF page. Keeping the card slightly below the exact
   CR80 height avoids Dompdf sub-point rounding creating an extra blank page. */
.id-card-page { position:relative; width:242.65pt; height:152.55pt; margin:0; padding:0; border:1.1pt solid #1F5C4A; border-radius:10pt; background:#fff; overflow:hidden; page-break-after:always; }
.id-card-page:last-child { page-break-after:auto; }
.abs { position:absolute; overflow:hidden; }

/* Dompdf is more reliable with table-cell vertical alignment than with
   line-height on small pills/strips. */
.pill-text,.center-text { display:table-cell; width:100%; height:100%; vertical-align:middle; }
.center-text { text-align:center; }

/* FRONT */
.front-header { top:0; left:0; width:242.65pt; height:34pt; background:#fff; border-bottom:2pt solid #1F5C4A; }
.front-logo-wrap { top:3pt; left:6pt; width:28pt; height:28pt; text-align:center; }
.front-logo { width:28pt; height:28pt; object-fit:contain; }
.front-logo-placeholder { width:26pt; height:26pt; border:1pt solid #1F5C4A; border-radius:50%; color:#1F5C4A; font-size:10pt; font-weight:bold; line-height:26pt; text-align:center; }
.front-school-name { top:3pt; left:40pt; width:198pt; height:11pt; color:#1F5C4A; font-size:9.6pt; line-height:11pt; font-weight:bold; letter-spacing:.2pt; text-transform:uppercase; white-space:nowrap; }
.front-subtitle { top:15pt; left:40pt; width:198pt; height:7pt; color:#666; font-size:5pt; line-height:7pt; letter-spacing:1.4pt; }
.front-card-title { top:23pt; left:40pt; width:198pt; height:7pt; color:#666; font-size:4.8pt; line-height:7pt; letter-spacing:.6pt; font-weight:bold; }
.front-photo-frame { top:40pt; left:8pt; width:60pt; height:62pt; border:1pt solid #ccc; border-radius:5pt; background:#f4f6f5; text-align:center; }
.front-photo { width:58pt; height:60pt; object-fit:cover; border-radius:4pt; }
.front-photo-placeholder { width:100%; color:#a1aaa6; font-size:6pt; line-height:60pt; text-align:center; }
.front-name { top:41pt; left:74pt; width:162pt; height:12pt; color:#1a1a1a; font-size:9.2pt; line-height:11pt; font-weight:bold; text-transform:uppercase; white-space:nowrap; }
.front-name-rule { top:55pt; left:74pt; width:46pt; height:1pt; border-top:.8pt solid #ccc; }
.front-role { top:58pt; left:74pt; width:162pt; height:7pt; color:#555; font-size:5.4pt; line-height:7pt; font-weight:bold; letter-spacing:1pt; text-transform:uppercase; }
.front-fact-label { left:74pt; width:40pt; height:7pt; color:#666; font-size:5.8pt; line-height:7pt; font-weight:bold; text-transform:uppercase; white-space:nowrap; }
.front-fact-value { left:116pt; width:122pt; height:7pt; color:#1a1a1a; font-size:5.8pt; line-height:7pt; white-space:nowrap; }
.front-session { top:93pt; left:74pt; width:78pt; height:12pt; display:table; background:#eaf2ef; border:.75pt solid #1F5C4A; color:#1F5C4A; font-size:5.6pt; font-weight:bold; padding:0 7pt; border-radius:8pt; }
.front-signature-img { top:103pt; left:160pt; width:66pt; height:10pt; object-fit:contain; }
.front-signature-line { top:113pt; left:164pt; width:62pt; height:1pt; border-bottom:.7pt solid #999; }
.front-signature-label { top:115pt; left:160pt; width:66pt; height:6pt; color:#555; font-size:4.8pt; line-height:6pt; font-weight:bold; letter-spacing:.6pt; text-align:center; }
.front-contact { top:124pt; left:0; width:242.65pt; height:11.5pt; background:#fff; border-top:.6pt solid #ddd; }
.front-contact-address { top:124pt; left:8pt; width:150pt; height:11.5pt; display:table; color:#555; font-size:4.3pt; white-space:nowrap; }
.front-contact-phone { top:124pt; right:8pt; width:76pt; height:11.5pt; display:table; color:#555; font-size:4.3pt; text-align:right; white-space:nowrap; }
.front-footer { top:136pt; left:0; width:242.65pt; height:15.5pt; display:table; background:#f5f5f5; border-top:.6pt solid #ddd; color:#555; font-size:4.3pt; font-weight:bold; text-align:center; text-transform:uppercase; }

/* BACK */
.back-header { top:0; left:0; width:242.65pt; height:24pt; display:table; background:#fff; border-bottom:2pt solid #1F5C4A; color:#1F5C4A; font-size:8.8pt; font-weight:bold; letter-spacing:.4pt; text-align:center; text-transform:uppercase; }
.back-pledge-title { top:29pt; left:9pt; width:150pt; height:10pt; display:table; background:#eaf2ef; border:.75pt solid #1F5C4A; color:#1F5C4A; font-size:5pt; font-weight:bold; letter-spacing:.3pt; padding:0 6pt; border-radius:6pt; text-transform:uppercase; }
.back-pledge { top:41pt; left:9pt; width:225pt; height:14pt; font-size:4.8pt; line-height:6.4pt; color:#333; }
.back-pledge-list { top:56pt; left:9pt; width:162pt; height:24pt; font-size:4.6pt; line-height:6.2pt; color:#333; }
.back-pledge-list div { margin-bottom:.6pt; }
.back-emergency-title { top:82pt; left:9pt; width:150pt; height:10pt; display:table; background:#eaf2ef; border:.75pt solid #1F5C4A; color:#1F5C4A; font-size:5pt; font-weight:bold; letter-spacing:.3pt; padding:0 6pt; border-radius:6pt; text-transform:uppercase; }
.back-emergency-label { left:9pt; width:28pt; height:7pt; color:#666; font-size:5.4pt; line-height:7pt; font-weight:bold; white-space:nowrap; }
.back-emergency-value { left:39pt; width:120pt; height:7pt; color:#1a1a1a; font-size:5.4pt; line-height:7pt; white-space:nowrap; }
.back-emergency-label.name,.back-emergency-value.name { top:94pt; }
.back-emergency-label.phone,.back-emergency-value.phone { top:102pt; }
.back-qr { top:66pt; left:181pt; width:40pt; height:40pt; object-fit:contain; }
.back-qr-label { top:107pt; left:172pt; width:56pt; height:6pt; color:#777; font-size:4.4pt; line-height:6pt; text-align:center; }
.back-footer { top:129.5pt; left:0; width:242.65pt; height:22.5pt; background:#fff; border-top:1pt solid #1F5C4A; }
.back-holder-line { top:136pt; left:29pt; width:66pt; height:1pt; border-bottom:.7pt solid #999; }
.back-holder-label { top:138pt; left:12pt; width:100pt; height:6pt; color:#555; font-size:4.1pt; line-height:6pt; text-align:center; }
.back-sign-divider { top:132pt; left:121pt; width:1pt; height:13pt; border-left:.6pt solid #ccc; }
.back-principal-signature-img { top:131pt; left:151pt; width:58pt; height:8pt; object-fit:contain; }
.back-principal-line { top:140pt; left:147pt; width:66pt; height:1pt; border-bottom:.7pt solid #999; }
.back-principal-label { top:142pt; left:142pt; width:76pt; height:6pt; color:#555; font-size:4.1pt; line-height:6pt; text-align:center; }
.back-notice { top:148pt; left:0; width:242.65pt; height:4pt; display:table; background:#f5f5f5; color:#555; font-size:3.8pt; font-weight:bold; text-align:center; text-transform:uppercase; }
