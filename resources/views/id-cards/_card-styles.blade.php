{{--
    Absolute-positioning layout, replacing the previous stacked-table
    design. Why: HTML table `height` is a MINIMUM, never a cap -- any
    row needing slightly more room than declared (padding, borders, an
    uncontrolled default line-height) makes the whole table taller
    than stated, and with five separate stacked tables per card, any
    one of them creeping past its budget pushes the total past
    153.07pt -- which is exactly what was splitting each card across
    two PDF pages. `overflow: hidden` doesn't reliably stop this in
    dompdf specifically around table structures.

    Absolute positioning sidesteps the whole problem: every element
    gets an explicit top/left/width/height inside one fixed-size
    `position: relative` container. Nothing can push the total card
    height past 153.07pt, because nothing here depends on content flow
    to determine size -- nothing to "accumulate". If text is too long
    for its box, it clips within THAT element only (each has its own
    overflow: hidden), never causing a new page.

    Same CR80 dimensions, same color scheme and content as before --
    only the positioning mechanism changed.
--}}
body {
    margin: 0;
    padding: 0;
    font-family: 'DejaVu Sans', sans-serif;
    color: #17221f;
    background: #ffffff;
}

.id-card-page {
    width: 242.65pt;
    height: 153.07pt;
    margin: 0;
    padding: 0;
    page-break-after: always;
    page-break-inside: avoid;
}
.id-card-page:last-child { page-break-after: auto; }

.id-card-inner {
    position: relative;
    width: 242.65pt;
    height: 153.07pt;
    border: 1.1pt solid #0a4d3b;
    border-radius: 10pt;
    background: #ffffff;
    overflow: hidden;
}

.abs { position: absolute; overflow: hidden; }

/* ---------- FRONT ---------- */
.front-header { top: 0; left: 0; width: 242.65pt; height: 34pt; background: #064b3a; border-bottom: 2pt solid #d9a52b; }
.front-logo-wrap { top: 3pt; left: 6pt; width: 28pt; height: 28pt; text-align: center; }
.front-logo { width: 28pt; height: 28pt; object-fit: contain; }
.front-logo-placeholder { width: 26pt; height: 26pt; border: 1pt solid #d9a52b; border-radius: 50%; color: #fff; font-size: 10pt; font-weight: bold; line-height: 26pt; text-align: center; }
.front-school-name { top: 3pt; left: 40pt; width: 198pt; height: 11pt; color: #fff; font-size: 9.6pt; line-height: 11pt; font-weight: bold; letter-spacing: 0.2pt; text-transform: uppercase; }
.front-subtitle { top: 15pt; left: 40pt; width: 198pt; height: 6pt; color: #fff; font-size: 5pt; line-height: 6pt; letter-spacing: 1.4pt; }
.front-card-title { top: 23pt; left: 40pt; width: 198pt; height: 6pt; color: #d9a52b; font-size: 4.8pt; line-height: 6pt; letter-spacing: 0.6pt; font-weight: bold; }

.front-photo-frame { top: 40pt; left: 8pt; width: 60pt; height: 62pt; border: 1pt solid #d9a52b; border-radius: 5pt; background: #f4f6f5; text-align: center; }
.front-photo { width: 60pt; height: 62pt; object-fit: cover; border-radius: 4pt; }
.front-photo-placeholder { width: 100%; color: #a1aaa6; font-size: 6pt; line-height: 62pt; text-align: center; }

.front-name { top: 41pt; left: 74pt; width: 162pt; height: 12pt; color: #07533f; font-size: 9.6pt; line-height: 11pt; font-weight: bold; text-transform: uppercase; }
.front-name-rule { top: 55pt; left: 74pt; width: 46pt; height: 1pt; border-top: 0.8pt solid #d9a52b; }
.front-role { top: 58pt; left: 74pt; width: 162pt; height: 7pt; color: #07533f; font-size: 5.6pt; line-height: 7pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }

.front-fact-row { left: 74pt; width: 162pt; height: 7pt; font-size: 6pt; line-height: 7pt; }
.front-fact-label { position: absolute; left: 0; width: 40pt; color: #3f4d48; font-weight: bold; text-transform: uppercase; }
.front-fact-value { position: absolute; left: 42pt; width: 118pt; color: #1b2622; }

.front-session { top: 90pt; left: 74pt; width: 90pt; height: 12pt; background: #07533f; color: #fff; font-size: 5.8pt; line-height: 12pt; font-weight: bold; padding-left: 7pt; border-radius: 8pt; }

.front-brand { top: 108pt; left: 8pt; width: 100pt; height: 10pt; color: #07533f; font-size: 8.5pt; line-height: 10pt; font-weight: bold; }
.front-brand .ag { color: #d9a52b; font-size: 5.5pt; }

.front-signature-img { top: 100pt; left: 160pt; width: 66pt; height: 15pt; object-fit: contain; }
.front-signature-line { top: 108pt; left: 168pt; width: 58pt; height: 1pt; border-bottom: 0.7pt solid #53635d; }
.front-signature-label { top: 111pt; left: 160pt; width: 66pt; height: 6pt; color: #07533f; font-size: 4.8pt; line-height: 6pt; font-weight: bold; letter-spacing: 0.6pt; text-align: center; }

.front-contact { top: 124pt; left: 0; width: 242.65pt; height: 12pt; background: #064b3a; color: #fff; font-size: 4.3pt; line-height: 12pt; }
.front-contact-address { position: absolute; left: 8pt; top: 0; width: 150pt; height: 12pt; white-space: nowrap; }
.front-contact-phone { position: absolute; right: 8pt; top: 0; width: 76pt; height: 12pt; text-align: right; white-space: nowrap; }

.front-footer { top: 137pt; left: 0; width: 242.65pt; height: 16.07pt; background: #d9a52b; color: #1e2a24; font-size: 4.4pt; line-height: 16.07pt; font-weight: bold; text-align: center; text-transform: uppercase; }

/* ---------- BACK ---------- */
.back-header { top: 0; left: 0; width: 242.65pt; height: 24pt; background: #064b3a; border-bottom: 2pt solid #d9a52b; color: #fff; font-size: 8.8pt; line-height: 24pt; font-weight: bold; letter-spacing: 0.4pt; text-align: center; text-transform: uppercase; }

.back-pledge-title { top: 29pt; left: 9pt; width: 150pt; height: 10pt; background: #07533f; color: #fff; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
.back-pledge { top: 41pt; left: 9pt; width: 225pt; height: 12pt; font-size: 4.8pt; line-height: 6.4pt; color: #24302c; }
.back-pledge-list { top: 54pt; left: 9pt; width: 225pt; height: 26pt; font-size: 4.6pt; line-height: 6.2pt; color: #24302c; }
.back-pledge-list div { margin-bottom: 0.6pt; }

.back-emergency-title { top: 82pt; left: 9pt; width: 150pt; height: 10pt; background: #07533f; color: #fff; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
.back-emergency-row { left: 9pt; width: 150pt; height: 7pt; font-size: 5.6pt; line-height: 7pt; }
.back-emergency-label { position: absolute; left: 0; width: 28pt; color: #4b5a54; font-weight: bold; }
.back-emergency-value { position: absolute; left: 30pt; width: 118pt; color: #1b2622; }

.back-qr { top: 66pt; left: 180pt; width: 40pt; height: 40pt; object-fit: contain; }
.back-qr-label { top: 107pt; left: 172pt; width: 56pt; height: 6pt; color: #53615b; font-size: 4.4pt; line-height: 6pt; text-align: center; }

.back-footer { top: 130pt; left: 0; width: 242.65pt; height: 23.07pt; background: #064b3a; border-top: 1pt solid #d9a52b; }
.back-sign-cell-l { top: 3pt; left: 12pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-cell-r { top: 3pt; left: 130pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-divider { top: 4pt; left: 121pt; width: 1pt; height: 13pt; border-left: 0.6pt solid #d9a52b; }
.back-signature-img { width: 60pt; height: 11pt; object-fit: contain; display: block; margin: 0 auto; }
.back-signature-line { width: 66pt; height: 1pt; border-bottom: 0.7pt solid #d9e0dc; margin: 4pt auto 0; }
.back-signature-label { color: #fff; font-size: 4.3pt; line-height: 6pt; margin-top: 1pt; }
.back-notice { top: 18pt; left: 0; width: 242.65pt; height: 5pt; background: #d9a52b; color: #1e2a24; font-size: 4pt; line-height: 5pt; font-weight: bold; text-align: center; text-transform: uppercase; }
