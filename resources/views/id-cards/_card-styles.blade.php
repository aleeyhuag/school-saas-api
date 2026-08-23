{{--
    Flattened, absolute-positioning layout -- every element is a
    direct child of .id-card-inner, none nested inside another
    positioned wrapper. That nesting was the real cause of the
    overlap bugs in earlier rounds: dompdf has a long-standing,
    still-open limitation where position:absolute inside another
    position:relative/absolute container doesn't reliably use that
    container as its reference point (confirmed across several
    dompdf GitHub issues going back over a decade) -- it was silently
    positioning things relative to the card or page instead of the
    intended parent row/cell, which is exactly the kind of scattered,
    hard-to-predict overlap that kept showing up. Every coordinate
    below is now each element's true position within the card,
    computed directly rather than relying on a parent's offset.

    Color palette matches resources/views/reports/report-card.blade.php
    exactly: #1F5C4A used the same restrained way the report card uses
    it (header border/title, and small value/emphasis accents) --
    everything else black/gray/white, same 'DejaVu Sans' font family.
--}}
body {
    margin: 0;
    padding: 0;
    font-family: 'DejaVu Sans', sans-serif;
    color: #1a1a1a;
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
    border: 1.1pt solid #1F5C4A;
    border-radius: 10pt;
    background: #ffffff;
    overflow: hidden;
}

.abs { position: absolute; overflow: hidden; }

/* ---------- FRONT ---------- */
.front-header { top: 0; left: 0; width: 242.65pt; height: 31pt; background: #ffffff; border-bottom: 2pt solid #1F5C4A; }
.front-logo-wrap { top: 3pt; left: 6pt; width: 28pt; height: 28pt; text-align: center; }
.front-logo { width: 28pt; height: 28pt; object-fit: contain; }
.front-logo-placeholder { width: 26pt; height: 26pt; border: 1pt solid #1F5C4A; border-radius: 50%; color: #1F5C4A; font-size: 10pt; font-weight: bold; line-height: 26pt; text-align: center; }
.front-school-name { top: 5pt; left: 40pt; width: 198pt; height: 11pt; color: #1F5C4A; font-size: 9.6pt; line-height: 11pt; font-weight: bold; letter-spacing: 0.2pt; text-transform: uppercase; white-space: nowrap; }
.front-card-title { top: 18pt; left: 40pt; width: 198pt; height: 8pt; color: #666; font-size: 5pt; line-height: 8pt; letter-spacing: 0.6pt; font-weight: bold; }

.front-photo-frame { top: 40pt; left: 8pt; width: 60pt; height: 62pt; border: 1pt solid #ccc; border-radius: 5pt; background: #f4f6f5; text-align: center; }
.front-photo { width: 60pt; height: 62pt; object-fit: cover; border-radius: 4pt; }
.front-photo-placeholder { width: 100%; color: #a1aaa6; font-size: 6pt; line-height: 62pt; text-align: center; }

.front-name { top: 41pt; left: 74pt; width: 162pt; height: 12pt; color: #1a1a1a; font-size: 9.2pt; line-height: 11pt; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
.front-name-rule { top: 55pt; left: 74pt; width: 46pt; height: 1pt; border-top: 0.8pt solid #ccc; }
.front-role { top: 58pt; left: 74pt; width: 162pt; height: 7pt; color: #555; font-size: 5.4pt; line-height: 7pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }

.front-fact-label { left: 74pt; width: 40pt; height: 7pt; color: #666; font-size: 5.8pt; line-height: 7pt; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
.front-fact-value { left: 116pt; width: 122pt; height: 7pt; color: #1a1a1a; font-size: 5.8pt; line-height: 7pt; white-space: nowrap; }

.front-session { top: 93pt; left: 74pt; width: 80pt; height: 12pt; background: #eaf2ef; border: 0.75pt solid #1F5C4A; color: #1F5C4A; font-size: 5.6pt; line-height: 12pt; font-weight: bold; padding-left: 7pt; border-radius: 8pt; }

.front-signature-img { top: 100pt; left: 160pt; width: 66pt; height: 15pt; object-fit: contain; }
.front-signature-line { top: 108pt; left: 168pt; width: 58pt; height: 1pt; border-bottom: 0.7pt solid #999; }
.front-signature-label { top: 111pt; left: 160pt; width: 66pt; height: 6pt; color: #555; font-size: 4.8pt; line-height: 6pt; font-weight: bold; letter-spacing: 0.6pt; text-align: center; }

.front-contact { top: 124pt; left: 0; width: 242.65pt; height: 12pt; background: #ffffff; border-top: 0.6pt solid #ddd; }
.front-contact-address { top: 124pt; left: 8pt; width: 150pt; height: 12pt; color: #555; font-size: 4.3pt; line-height: 12pt; white-space: nowrap; }
.front-contact-phone { top: 124pt; right: 8pt; width: 76pt; height: 12pt; color: #555; font-size: 4.3pt; line-height: 12pt; text-align: right; white-space: nowrap; }

.front-footer { top: 137pt; left: 0; width: 242.65pt; height: 16.07pt; background: #f5f5f5; border-top: 0.6pt solid #ddd; color: #555; font-size: 4.4pt; line-height: 16.07pt; font-weight: bold; text-align: center; text-transform: uppercase; }

/* ---------- BACK ---------- */
.back-header { top: 0; left: 0; width: 242.65pt; height: 24pt; background: #ffffff; border-bottom: 2pt solid #1F5C4A; color: #1F5C4A; font-size: 8.8pt; line-height: 24pt; font-weight: bold; letter-spacing: 0.4pt; text-align: center; text-transform: uppercase; }

.back-pledge-title { top: 29pt; left: 9pt; width: 150pt; height: 10pt; background: #eaf2ef; border: 0.75pt solid #1F5C4A; color: #1F5C4A; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
.back-pledge { top: 41pt; left: 9pt; width: 225pt; height: 14pt; font-size: 4.8pt; line-height: 6.4pt; color: #333; }
.back-pledge-list { top: 56pt; left: 9pt; width: 225pt; height: 24pt; font-size: 4.6pt; line-height: 6.2pt; color: #333; }
.back-pledge-list div { margin-bottom: 0.6pt; }

.back-emergency-title { top: 82pt; left: 9pt; width: 150pt; height: 10pt; background: #eaf2ef; border: 0.75pt solid #1F5C4A; color: #1F5C4A; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
.back-emergency-label { left: 9pt; width: 28pt; height: 7pt; color: #666; font-size: 5.4pt; line-height: 7pt; font-weight: bold; white-space: nowrap; }
.back-emergency-value { left: 39pt; width: 120pt; height: 7pt; color: #1a1a1a; font-size: 5.4pt; line-height: 7pt; white-space: nowrap; }

.back-qr { top: 66pt; left: 180pt; width: 40pt; height: 40pt; object-fit: contain; }
.back-qr-label { top: 107pt; left: 172pt; width: 56pt; height: 6pt; color: #777; font-size: 4.4pt; line-height: 6pt; text-align: center; }

.back-footer { top: 130pt; left: 0; width: 242.65pt; height: 23.07pt; background: #ffffff; border-top: 1pt solid #1F5C4A; }
.back-sign-cell-l { top: 133pt; left: 12pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-cell-r { top: 133pt; left: 130pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-divider { top: 134pt; left: 121pt; width: 1pt; height: 13pt; border-left: 0.6pt solid #ccc; }
.back-signature-img { width: 60pt; height: 11pt; object-fit: contain; display: block; margin: 0 auto; }
.back-signature-line { width: 66pt; height: 1pt; border-bottom: 0.7pt solid #999; margin: 4pt auto 0; }
.back-signature-label { color: #555; font-size: 4.3pt; line-height: 6pt; margin-top: 1pt; }
.back-notice { top: 148pt; left: 0; width: 242.65pt; height: 5pt; background: #f5f5f5; color: #555; font-size: 4pt; line-height: 5pt; font-weight: bold; text-align: center; text-transform: uppercase; }
