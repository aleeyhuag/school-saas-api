{{-- Shared styling for id-cards.single and id-cards.print-sheet.
     Card is CR80 size (85.6mm x 54mm ≈ 242.65pt x 153.07pt) — a real
     physical ID card is small, so font sizes here are deliberately
     tiny (matching how actual printed ID cards look), not a mistake. --}}
body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; margin: 0; padding: 0; }

.id-card {
    width: 242.65pt;
    height: 153.07pt;
    border-collapse: collapse;
}
.id-card-inner {
    width: 242.65pt;
    height: 153.07pt;
    border: 1.5pt solid #1F5C4A;
    padding: 8pt;
    vertical-align: top;
}

.id-card-header { width: 100%; border-bottom: 1pt solid #1F5C4A; padding-bottom: 4pt; margin-bottom: 6pt; }
.id-card-logo-cell { width: 26pt; vertical-align: middle; }
.id-card-logo { max-height: 22pt; max-width: 24pt; }
.id-card-school-name { font-size: 9pt; font-weight: bold; color: #1F5C4A; vertical-align: middle; padding-left: 6pt; }
.id-card-subtitle { font-size: 6pt; font-weight: normal; color: #555; letter-spacing: 0.5pt; margin-top: 1pt; }

.id-card-body { width: 100%; margin-top: 4pt; }
.id-card-photo-cell { width: 56pt; vertical-align: top; }
.id-card-photo { width: 52pt; height: 62pt; object-fit: cover; border: 1pt solid #ccc; }
.id-card-photo-placeholder {
    width: 50pt; height: 60pt; border: 1pt dashed #ccc; color: #999;
    font-size: 6pt; text-align: center; padding-top: 24pt;
}

.id-card-details { vertical-align: top; padding-left: 6pt; width: 110pt; }
.id-card-student-name { font-size: 9.5pt; font-weight: bold; margin-bottom: 4pt; }
.id-card-facts { font-size: 7pt; }
.id-card-facts td { padding: 1.5pt 0; }
.id-card-fact-label { color: #666; width: 42pt; }

.id-card-qr-cell { width: 50pt; text-align: center; vertical-align: top; }
.id-card-qr { width: 42pt; height: 42pt; }
.id-card-qr-label { font-size: 5pt; color: #777; margin-top: 2pt; }

.id-card-footer { width: 100%; border-top: 0.5pt solid #ddd; margin-top: 6pt; padding-top: 3pt; font-size: 5.5pt; color: #888; }
.id-card-footer-right { text-align: right; }
