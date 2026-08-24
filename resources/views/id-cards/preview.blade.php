<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card['student']->full_name }} — ID Card Browser Preview</title>
    <style>
        @include('id-cards._card-styles')

        /* TEMPORARY browser-only preview shell. Do not copy these shell rules
           into the PDF template; the actual card CSS above is shared. */
        html, body { min-height: 100%; }
        body {
            background: #eef2f0;
            padding: 32px;
        }
        .preview-toolbar {
            max-width: 900px;
            margin: 0 auto 28px;
            padding: 16px 18px;
            background: #fff;
            border: 1px solid #d8dfdc;
            border-radius: 10px;
            font-family: Arial, sans-serif;
            color: #1a1a1a;
        }
        .preview-toolbar strong { color: #1F5C4A; }
        .preview-toolbar code {
            background: #f3f5f4;
            padding: 2px 5px;
            border-radius: 4px;
        }
        .preview-stage {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            gap: 42px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .preview-side { display: flex; flex-direction: column; gap: 10px; }
        .preview-label {
            font: 700 13px/1.2 Arial, sans-serif;
            color: #52605a;
            text-align: center;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .preview-card {
            width: 242.65pt;
            height: 153.07pt;
            position: relative;
            box-sizing: border-box;
            flex: 0 0 auto;
            background: #fff;
            border-radius: 10pt;
            box-shadow: 0 10px 28px rgba(0,0,0,.13);
        }
        .preview-card .id-card-inner {
            box-sizing: border-box;
        }
        @media (max-width: 700px) {
            body { padding: 16px; }
            .preview-stage { transform-origin: top center; }
        }
    </style>
</head>
<body>
    <div class="preview-toolbar">
        <strong>Temporary ID Card Editor Preview</strong><br>
        This is the same front/back card markup and card CSS used by the PDF renderer,
        but rendered by the browser so you can use <b>Inspect → Elements</b> and
        <b>Inspect → Styles</b> without Dompdf pagination getting in the way.<br><br>
        Edit the card, then send me the changed HTML/CSS (or screenshots + the changed
        rules). <code>.id-card-inner</code> is the CR80 card boundary.
    </div>

    <div class="preview-stage">
        <div class="preview-side">
            <div class="preview-label">Front</div>
            <div class="preview-card">
                @include('id-cards._card-front', ['card' => $card])
            </div>
        </div>

        <div class="preview-side">
            <div class="preview-label">Back</div>
            <div class="preview-card">
                @include('id-cards._card-back', ['card' => $card])
            </div>
        </div>
    </div>
</body>
</html>
