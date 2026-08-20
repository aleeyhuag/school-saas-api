{{-- Shared styling for id-cards.single and id-cards.print-sheet.
     Card is CR80 size (85.6mm x 54mm ≈ 242.65pt x 153.07pt) — a real
     physical ID card is small, so font sizes here are deliberately
     tiny (matching how actual printed ID cards look), not a mistake.
     Brand green matches the color already used on report cards
     (#1F5C4A), so every document Skulag generates for a school looks
     like it belongs to the same family. --}}
body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; margin: 0; padding: 0; }

.id-card, .id-card-inner { width: 242.65pt; height: 153.07pt; }
.id-card { border-collapse: collapse; }
.id-card-inner {
    border: 1.5pt solid #1F5C4A;
    border-radius: 6pt;
    vertical-align: top;
    position: relative;
}

/* ---------- FRONT ---------- */
.card-front-header { width: 100%; background-color: #1F5C4A; }
.card-front-header td { padding: 6pt 8pt; vertical-align: middle; }
.card-front-logo { max-height: 24pt; max-width: 26pt; }
.card-front-school-name { font-size: 10pt; font-weight: bold; color: #ffffff; padding-left: 6pt; }
.card-front-tagline { font-size: 5.5pt; color: #D4AF6A; letter-spacing: 0.4pt; margin-top: 1pt; }

.card-front-body { width: 100%; padding: 7pt 8pt 0; }
.card-front-photo-cell { width: 62pt; vertical-align: top; }
.card-front-photo { width: 58pt; height: 68pt; object-fit: cover; border: 1pt solid #ddd; border-radius: 2pt; }
.card-front-photo-placeholder {
    width: 56pt; height: 66pt; border: 1pt dashed #ccc; color: #999;
    font-size: 6pt; text-align: center; padding-top: 28pt;
}

.card-front-details { vertical-align: top; padding-left: 7pt; }
.card-front-name { font-size: 10.5pt; font-weight: bold; color: #1F5C4A; line-height: 1.15; }
.card-front-role { font-size: 6pt; color: #888; letter-spacing: 0.5pt; margin: 2pt 0 4pt; text-transform: uppercase; }
.card-front-facts { font-size: 6.8pt; }
.card-front-facts td { padding: 1.2pt 0; }
.card-front-fact-label { color: #777; width: 40pt; font-weight: bold; }

.card-front-session {
    display: inline-block; background-color: #1F5C4A; color: #fff;
    font-size: 6pt; font-weight: bold; padding: 2pt 7pt; border-radius: 8pt; margin-top: 5pt;
}

.card-front-signoff { width: 100%; padding: 4pt 8pt 0; }
.card-front-brand { font-size: 6pt; color: #999; vertical-align: bottom; }
.card-front-signature-cell { text-align: center; vertical-align: bottom; }
.card-front-signature-img { max-height: 16pt; max-width: 60pt; }
.card-front-signature-line { border-top: 0.75pt solid #999; width: 62pt; margin: 0 auto; }
.card-front-signature-label { font-size: 5pt; color: #888; text-align: center; margin-top: 1.5pt; }

.card-front-footer { width: 100%; background-color: #D4AF6A; color: #3a2f00; font-size: 4.8pt; text-align: center; padding: 3pt 6pt; position: absolute; bottom: 0; left: 0; border-radius: 0 0 5pt 5pt; }

/* ---------- BACK ---------- */
.card-back-header { width: 100%; background-color: #1F5C4A; color: #fff; text-align: center; padding: 6pt; font-size: 9pt; font-weight: bold; letter-spacing: 0.5pt; }
.card-back-body { padding: 6pt 9pt 0; }
.card-back-pledge-title {
    display: inline-block; background-color: #1F5C4A; color: #fff; font-size: 5.8pt;
    font-weight: bold; padding: 2pt 6pt; border-radius: 7pt; margin-bottom: 4pt;
}
.card-back-pledge { font-size: 5.6pt; color: #333; line-height: 1.5; }
.card-back-pledge ul { margin: 3pt 0 0; padding-left: 9pt; }
.card-back-pledge li { margin-bottom: 1.5pt; }

.card-back-emergency-title {
    display: inline-block; background-color: #1F5C4A; color: #fff; font-size: 5.8pt;
    font-weight: bold; padding: 2pt 6pt; border-radius: 7pt; margin: 5pt 0 3pt;
}
.card-back-emergency { font-size: 6.2pt; }
.card-back-emergency td { padding: 1pt 0; }
.card-back-emergency-label { color: #777; width: 32pt; font-weight: bold; }

.card-back-qr-row { width: 100%; margin-top: 5pt; }
.card-back-qr { width: 34pt; height: 34pt; }
.card-back-qr-label { font-size: 5pt; color: #777; }

.card-back-footer { width: 100%; position: absolute; bottom: 0; left: 0; padding: 4pt 9pt 5pt; }
.card-back-sign-row { width: 100%; border-top: 0.5pt solid #ddd; padding-top: 3pt; font-size: 5pt; color: #777; text-align: center; }
.card-back-sign-line { border-top: 0.75pt solid #999; width: 90pt; display: inline-block; margin-bottom: 1.5pt; }
.card-back-notice { font-size: 4.8pt; color: #999; text-align: center; margin-top: 3pt; }
