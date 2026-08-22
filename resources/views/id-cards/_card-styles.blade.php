{{--
    Absolute-positioning layout (see the earlier delivery's notes on
    why: HTML table height is a minimum not a cap, which was splitting
    cards across pages -- this structure eliminates that entirely).

    Color palette rewritten to match the report card's approach
    (resources/views/reports/report-card.blade.php): mostly black/gray
    text on white, thin borders instead of solid color fills, no
    assumption that a school's brand is green -- same principle, same
    'DejaVu Sans' font family, deliberately restrained rather than
    colorful. Every top/left/width/height value is unchanged from the
    previous delivery EXCEPT two coordinate corrections that were
    genuine bugs, not style choices -- called out inline below.
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
    border: 1.1pt solid #1a1a1a;
    border-radius: 10pt;
    background: #ffffff;
    overflow: hidden;
}

.abs { position: absolute; overflow: hidden; }

/* ---------- FRONT ---------- */
.front-header { top: 0; left: 0; width: 242.65pt; height: 34pt; background: #ffffff; border-bottom: 1.6pt solid #1a1a1a; }
.front-logo-wrap { top: 3pt; left: 6pt; width: 28pt; height: 28pt; text-align: center; }
.front-logo { width: 28pt; height: 28pt; object-fit: contain; }
.front-logo-placeholder { width: 26pt; height: 26pt; border: 1pt solid #999; border-radius: 50%; color: #1a1a1a; font-size: 10pt; font-weight: bold; line-height: 26pt; text-align: center; }
.front-school-name { top: 3pt; left: 40pt; width: 198pt; height: 11pt; color: #1a1a1a; font-size: 9.6pt; line-height: 11pt; font-weight: bold; letter-spacing: 0.2pt; text-transform: uppercase; overflow: hidden; white-space: nowrap; }
.front-subtitle { top: 15pt; left: 40pt; width: 198pt; height: 6pt; color: #666; font-size: 5pt; line-height: 6pt; letter-spacing: 1.4pt; }
.front-card-title { top: 23pt; left: 40pt; width: 198pt; height: 6pt; color: #666; font-size: 4.8pt; line-height: 6pt; letter-spacing: 0.6pt; font-weight: bold; }

.front-photo-frame { top: 40pt; left: 8pt; width: 60pt; height: 62pt; border: 1pt solid #ccc; border-radius: 5pt; background: #f4f6f5; text-align: center; }
.front-photo { width: 60pt; height: 62pt; object-fit: cover; border-radius: 4pt; }
.front-photo-placeholder { width: 100%; color: #a1aaa6; font-size: 6pt; line-height: 62pt; text-align: center; }

.front-name { top: 41pt; left: 74pt; width: 162pt; height: 12pt; color: #1a1a1a; font-size: 9.2pt; line-height: 11pt; font-weight: bold; text-transform: uppercase; overflow: hidden; white-space: nowrap; }
.front-name-rule { top: 55pt; left: 74pt; width: 46pt; height: 1pt; border-top: 0.8pt solid #999; }
.front-role { top: 58pt; left: 74pt; width: 162pt; height: 7pt; color: #555; font-size: 5.4pt; line-height: 7pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }

.front-fact-row { left: 74pt; width: 162pt; height: 7pt; font-size: 5.8pt; line-height: 7pt; }
.front-fact-label { position: absolute; left: 0; width: 40pt; color: #666; font-weight: bold; text-transform: uppercase; overflow: hidden; white-space: nowrap; }
.front-fact-value { position: absolute; left: 42pt; width: 118pt; color: #1a1a1a; overflow: hidden; white-space: nowrap; }

{{-- Was top:90/width:90pt -- overlapped the D.O.B. row above it
     (which ends at 91pt) by 1pt, and came close to colliding with the
     signature block to its right. Moved down 3pt and narrowed 10pt;
     this is a coordinate bug fix, not a composition change. --}}
.front-session { top: 93pt; left: 74pt; width: 80pt; height: 12pt; background: #f0f0f0; border: 0.75pt solid #ccc; color: #1a1a1a; font-size: 5.6pt; line-height: 12pt; font-weight: bold; padding-left: 7pt; border-radius: 8pt; }

.front-brand { top: 108pt; left: 8pt; width: 100pt; height: 10pt; color: #1a1a1a; font-size: 8.5pt; line-height: 10pt; font-weight: bold; }
.front-brand .ag { color: #777; font-size: 5.5pt; }

.front-signature-img { top: 100pt; left: 160pt; width: 66pt; height: 15pt; object-fit: contain; }
.front-signature-line { top: 108pt; left: 168pt; width: 58pt; height: 1pt; border-bottom: 0.7pt solid #999; }
.front-signature-label { top: 111pt; left: 160pt; width: 66pt; height: 6pt; color: #555; font-size: 4.8pt; line-height: 6pt; font-weight: bold; letter-spacing: 0.6pt; text-align: center; }

.front-contact { top: 124pt; left: 0; width: 242.65pt; height: 12pt; background: #ffffff; border-top: 0.6pt solid #ddd; color: #555; font-size: 4.3pt; line-height: 12pt; }
.front-contact-address { position: absolute; left: 8pt; top: 0; width: 150pt; height: 12pt; white-space: nowrap; overflow: hidden; }
.front-contact-phone { position: absolute; right: 8pt; top: 0; width: 76pt; height: 12pt; text-align: right; white-space: nowrap; overflow: hidden; }

.front-footer { top: 137pt; left: 0; width: 242.65pt; height: 16.07pt; background: #f5f5f5; border-top: 0.6pt solid #ddd; color: #555; font-size: 4.4pt; line-height: 16.07pt; font-weight: bold; text-align: center; text-transform: uppercase; }

/* ---------- BACK ---------- */
.back-header { top: 0; left: 0; width: 242.65pt; height: 24pt; background: #ffffff; border-bottom: 1.6pt solid #1a1a1a; color: #1a1a1a; font-size: 8.8pt; line-height: 24pt; font-weight: bold; letter-spacing: 0.4pt; text-align: center; text-transform: uppercase; }

.back-pledge-title { top: 29pt; left: 9pt; width: 150pt; height: 10pt; background: #f0f0f0; border: 0.75pt solid #ccc; color: #1a1a1a; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
{{-- Was height:12pt -- the wrapped intro text needs ~12.8pt at this
     line-height and was getting its second line clipped. Raised to
     14pt and shifted everything below it down to match; total space
     used by pledge+list is unchanged (39pt either way), so nothing
     downstream (emergency section, footer) needed to move. --}}
.back-pledge { top: 41pt; left: 9pt; width: 225pt; height: 14pt; font-size: 4.8pt; line-height: 6.4pt; color: #333; }
.back-pledge-list { top: 56pt; left: 9pt; width: 225pt; height: 24pt; font-size: 4.6pt; line-height: 6.2pt; color: #333; }
.back-pledge-list div { margin-bottom: 0.6pt; }

.back-emergency-title { top: 82pt; left: 9pt; width: 150pt; height: 10pt; background: #f0f0f0; border: 0.75pt solid #ccc; color: #1a1a1a; font-size: 5pt; line-height: 10pt; font-weight: bold; letter-spacing: 0.3pt; padding-left: 6pt; border-radius: 6pt; text-transform: uppercase; }
.back-emergency-row { left: 9pt; width: 150pt; height: 7pt; font-size: 5.4pt; line-height: 7pt; }
.back-emergency-label { position: absolute; left: 0; width: 28pt; color: #666; font-weight: bold; overflow: hidden; white-space: nowrap; }
.back-emergency-value { position: absolute; left: 30pt; width: 118pt; color: #1a1a1a; overflow: hidden; white-space: nowrap; }

.back-qr { top: 66pt; left: 180pt; width: 40pt; height: 40pt; object-fit: contain; }
.back-qr-label { top: 107pt; left: 172pt; width: 56pt; height: 6pt; color: #777; font-size: 4.4pt; line-height: 6pt; text-align: center; }

.back-footer { top: 130pt; left: 0; width: 242.65pt; height: 23.07pt; background: #ffffff; border-top: 1pt solid #1a1a1a; }
.back-sign-cell-l { top: 3pt; left: 12pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-cell-r { top: 3pt; left: 130pt; width: 100pt; height: 15pt; text-align: center; }
.back-sign-divider { top: 4pt; left: 121pt; width: 1pt; height: 13pt; border-left: 0.6pt solid #ccc; }
.back-signature-img { width: 60pt; height: 11pt; object-fit: contain; display: block; margin: 0 auto; }
.back-signature-line { width: 66pt; height: 1pt; border-bottom: 0.7pt solid #999; margin: 4pt auto 0; }
.back-signature-label { color: #555; font-size: 4.3pt; line-height: 6pt; margin-top: 1pt; }
.back-notice { top: 18pt; left: 0; width: 242.65pt; height: 5pt; background: #f5f5f5; color: #555; font-size: 4pt; line-height: 5pt; font-weight: bold; text-align: center; text-transform: uppercase; }
