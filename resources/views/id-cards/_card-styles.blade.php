{{--
    Greenfield-reference design system for the default Skulag ID card.
    Keep dimensions fixed: CR80 = 85.6mm x 54mm = 242.65pt x 153.07pt.
    The same partial is used by both the individual PDF and A4 print sheet.
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
    overflow: hidden;
}

.id-card-page:last-child { page-break-after: auto; }

.id-card {
    width: 242.65pt;
    height: 153.07pt;
    border-collapse: collapse;
    border-spacing: 0;
    margin: 0;
    padding: 0;
    background: #ffffff;
}

.id-card-inner {
    width: 242.65pt;
    height: 153.07pt;
    padding: 0;
    margin: 0;
    vertical-align: top;
    border: 1.1pt solid #0a4d3b;
    border-radius: 10pt;
    overflow: hidden;
    background: #ffffff;
}

/* ---------- SHARED ---------- */
.green { color: #07533f; }
.gold { color: #d9a52b; }

.school-logo-wrap {
    width: 35pt;
    height: 35pt;
    text-align: center;
    vertical-align: middle;
}
.school-logo {
    width: 33pt;
    height: 33pt;
    object-fit: contain;
}
.school-logo-placeholder {
    width: 30pt;
    height: 30pt;
    border: 1pt solid #d9a52b;
    border-radius: 50%;
    color: #ffffff;
    font-size: 10pt;
    font-weight: bold;
    line-height: 30pt;
    text-align: center;
}

/* ---------- FRONT ---------- */
.card-front-header {
    width: 100%;
    height: 44pt;
    background: #064b3a;
    border-bottom: 2pt solid #d9a52b;
}
.card-front-header td { vertical-align: middle; }
.card-front-header-content { padding: 5pt 9pt 4pt 9pt; }
.card-front-school-name {
    color: #ffffff;
    font-size: 11.2pt;
    line-height: 1.05;
    font-weight: bold;
    letter-spacing: 0.3pt;
    text-transform: uppercase;
}
.card-front-school-subtitle {
    color: #ffffff;
    font-size: 5.5pt;
    letter-spacing: 1.6pt;
    margin-top: 2pt;
}
.card-front-title {
    color: #d9a52b;
    font-size: 5.2pt;
    letter-spacing: 0.8pt;
    font-weight: bold;
    margin-top: 3pt;
}
.card-front-header-rule {
    border-top: 0.8pt solid #d9a52b;
    width: 55pt;
    margin-top: 2pt;
}

.card-front-main {
    width: 100%;
    height: 66pt;
    border-collapse: collapse;
}
.card-front-photo-cell {
    width: 76pt;
    padding: 4pt 4pt 2pt 9pt;
    vertical-align: top;
}
.card-front-photo-frame {
    width: 67pt;
    height: 59pt;
    border: 1pt solid #d9a52b;
    border-radius: 7pt;
    overflow: hidden;
    background: #f4f6f5;
    text-align: center;
    vertical-align: middle;
}
.card-front-photo {
    width: 67pt;
    height: 59pt;
    object-fit: cover;
}
.card-front-photo-placeholder {
    color: #a1aaa6;
    font-size: 6pt;
    letter-spacing: 0.8pt;
    line-height: 59pt;
}

.card-front-details {
    width: 153pt;
    padding: 4pt 8pt 1pt 3pt;
    vertical-align: top;
}
.card-front-name {
    color: #07533f;
    font-size: 10.8pt;
    line-height: 1.05;
    font-weight: bold;
    text-transform: uppercase;
    max-width: 145pt;
}
.card-front-name-rule {
    border-top: 0.8pt solid #d9a52b;
    width: 55pt;
    margin-top: 3pt;
    margin-bottom: 3pt;
}
.card-front-role {
    color: #07533f;
    font-size: 6pt;
    font-weight: bold;
    letter-spacing: 1.1pt;
    text-transform: uppercase;
    margin-bottom: 3pt;
}
.card-front-facts {
    width: 100%;
    border-collapse: collapse;
    font-size: 6.1pt;
}
.card-front-facts td { padding: 1.1pt 0; vertical-align: top; }
.card-front-fact-label {
    width: 43pt;
    color: #3f4d48;
    font-weight: bold;
    text-transform: uppercase;
}
.card-front-fact-value { color: #1b2622; }
.card-front-session {
    display: inline-block;
    background: #07533f;
    color: #ffffff;
    font-size: 6pt;
    font-weight: bold;
    padding: 3pt 8pt;
    border-radius: 9pt;
    margin-top: 3pt;
}

.card-front-lower {
    width: 100%;
    height: 15pt;
    border-collapse: collapse;
}
.card-front-brand-cell {
    width: 115pt;
    padding: 0 0 1pt 9pt;
    vertical-align: bottom;
}
.card-front-brand {
    color: #07533f;
    font-size: 9pt;
    font-weight: bold;
    letter-spacing: -0.3pt;
}
.card-front-brand .ag { color: #d9a52b; font-size: 6pt; }
.card-front-signature-cell {
    width: 100pt;
    padding: 0 9pt 0 0;
    text-align: center;
    vertical-align: bottom;
}
.card-front-signature-img {
    width: 58pt;
    height: 13pt;
    object-fit: contain;
}
.card-front-signature-line {
    width: 60pt;
    height: 11pt;
    border-bottom: 0.7pt solid #53635d;
    margin: 0 auto;
}
.card-front-signature-label {
    color: #07533f;
    font-size: 5.3pt;
    font-weight: bold;
    letter-spacing: 0.8pt;
    margin-top: 1pt;
}

.card-front-contact {
    width: 100%;
    height: 13pt;
    background: #064b3a;
    color: #ffffff;
    border-collapse: collapse;
}
.card-front-contact td {
    padding: 2pt 4pt;
    font-size: 4.4pt;
    vertical-align: middle;
    white-space: nowrap;
}
.card-front-contact-address { width: 145pt; padding-left: 9pt !important; }
.card-front-contact-phone { width: 82pt; text-align: right; padding-right: 9pt !important; }

.card-front-footer {
    width: 100%;
    height: 13pt;
    background: #d9a52b;
    color: #1e2a24;
    font-size: 4.6pt;
    line-height: 13pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
}

/* ---------- BACK ---------- */
.card-back-header {
    width: 100%;
    height: 30pt;
    background: #064b3a;
    border-bottom: 2pt solid #d9a52b;
    color: #ffffff;
    text-align: center;
    font-size: 9.5pt;
    line-height: 30pt;
    font-weight: bold;
    letter-spacing: 0.5pt;
    text-transform: uppercase;
}
.card-back-body {
    width: 100%;
    height: 89pt;
    padding: 6pt 9pt 0 9pt;
    vertical-align: top;
}
.card-back-pledge-title,
.card-back-emergency-title {
    display: inline-block;
    background: #07533f;
    color: #ffffff;
    font-size: 5.5pt;
    font-weight: bold;
    letter-spacing: 0.3pt;
    padding: 3pt 8pt;
    border-radius: 8pt;
    text-transform: uppercase;
}
.card-back-pledge {
    font-size: 5.2pt;
    line-height: 1.35;
    margin-top: 3pt;
    color: #24302c;
}
.card-back-pledge p { margin: 0; }
.card-back-pledge ul { margin: 2pt 0 0 8pt; padding: 0; }
.card-back-pledge li { margin: 1pt 0; }
.card-back-emergency-title { margin-top: 4pt; }
.card-back-emergency-wrap {
    width: 100%;
    border-collapse: collapse;
    margin-top: 2pt;
}
.card-back-emergency-wrap td { vertical-align: middle; }
.card-back-emergency-info { width: 130pt; font-size: 5.6pt; }
.card-back-emergency-info td { padding: 1.2pt 0; }
.card-back-emergency-label { width: 27pt; color: #4b5a54; font-weight: bold; }
.card-back-qr-cell { width: 70pt; text-align: right; }
.card-back-qr { width: 43pt; height: 43pt; object-fit: contain; }
.card-back-qr-label { color: #53615b; font-size: 4.6pt; margin-top: 1pt; text-align: center; }

.card-back-footer {
    width: 100%;
    height: 32pt;
    background: #064b3a;
    color: #ffffff;
    border-top: 1pt solid #d9a52b;
}
.card-back-signatures {
    width: 100%;
    border-collapse: collapse;
    height: 22pt;
}
.card-back-signature-cell {
    width: 50%;
    text-align: center;
    vertical-align: bottom;
    padding: 1pt 8pt 0;
}
.card-back-signature-img {
    width: 56pt;
    height: 11pt;
    object-fit: contain;
}
.card-back-signature-line {
    width: 66pt;
    height: 9pt;
    border-bottom: 0.7pt solid #d9e0dc;
    margin: 0 auto;
}
.card-back-signature-label {
    color: #ffffff;
    font-size: 4.8pt;
    margin-top: 1pt;
}
.card-back-divider {
    border-left: 0.6pt solid #d9a52b;
    height: 15pt;
}
.card-back-notice {
    height: 10pt;
    background: #d9a52b;
    color: #1e2a24;
    font-size: 4.5pt;
    line-height: 10pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
}
