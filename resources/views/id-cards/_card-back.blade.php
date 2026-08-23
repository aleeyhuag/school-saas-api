{{-- Fully flattened -- see _card-front.blade.php's docblock for why. --}}
<div class="id-card-inner">
    <div class="abs back-header">{{ strtoupper($card['school']->name) }}</div>

    <div class="abs back-pledge-title">STUDENT CODE OF CONDUCT</div>
    <div class="abs back-pledge">
        As a student of {{ $card['school']->name }}, I pledge to uphold the
        values of discipline, respect, honesty and hard work.
    </div>
    <div class="abs back-pledge-list">
        <div>&bull; I will be punctual, diligent and respectful.</div>
        <div>&bull; I will wear my uniform neatly at all times.</div>
        <div>&bull; I will care for school property and the environment.</div>
    </div>

    <div class="abs back-emergency-title">IN CASE OF EMERGENCY, CONTACT</div>
    <div class="abs back-emergency-label" style="top: 92pt;">Name</div>
    <div class="abs back-emergency-value" style="top: 92pt;">{{ $card['student']->guardian_name ?: '—' }}</div>
    <div class="abs back-emergency-label" style="top: 100pt;">Phone</div>
    <div class="abs back-emergency-value" style="top: 100pt;">{{ $card['student']->guardian_phone ?: '—' }}</div>

    <img src="{{ $card['qr_data_uri'] }}" class="abs back-qr" alt="">
    <div class="abs back-qr-label">SCAN TO VERIFY</div>

    <div class="abs back-footer"></div>
    <div class="abs back-sign-cell-l">
        <div class="back-signature-line"></div>
        <div class="back-signature-label">HOLDER'S SIGNATURE</div>
    </div>
    <div class="abs back-sign-divider"></div>
    <div class="abs back-sign-cell-r">
        @if($card['signature_data_uri'])
            <img src="{{ $card['signature_data_uri'] }}" class="back-signature-img" alt="">
        @else
            <div class="back-signature-line"></div>
        @endif
        <div class="back-signature-label">PRINCIPAL'S SIGNATURE</div>
    </div>
    <div class="abs back-notice">This ID card must be presented on demand. Not transferable.</div>
</div>
